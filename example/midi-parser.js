/**
 * MidiParser provides static methods to decode binary MIDI file data into a structured JavaScript object.
 * It handles MIDI Format 0 and 1, Note On/Off events, Program Changes, and various Meta events.
 * 
 * @author Kamshory <kamshory@gmail.com>
 */
class MidiParser {
    /**
     * Parses an ArrayBuffer containing MIDI file data.
     * 
     * @param {ArrayBuffer} buffer - The binary content of the MIDI file.
     * @param {Object} [options] - Configuration for parsing.
     * @param {boolean} [options.normalize=false] - If true, shifts all events so the first note starts at tick 0.
     * @returns {Object} result - Structured MIDI data containing header, tracks, and instruments per channel.
     */
    static parse(buffer, options = { normalize: false, forceUpdateEvents: false }) {
        const view = new DataView(buffer);
        let offset = 0;

        const readUint8 = () => view.getUint8(offset++);
        const readUint16 = () => { const v = view.getUint16(offset); offset += 2; return v; };
        const readUint32 = () => { const v = view.getUint32(offset); offset += 4; return v; };

        /**
         * Reads a string of length 'l' from the current offset.
         * @param {number} l - Length of string to read.
         * @returns {string}
         */
        const readString = (l) => {
            let s = "";
            for (let i = 0; i < l; i++) s += String.fromCharCode(readUint8());
            return s;
        };

        /**
         * Reads a Variable Length Quantity (VLQ) used in MIDI for delta-times and event lengths.
         * MIDI VLQ uses 7 bits per byte, with the 8th bit indicating if another byte follows.
         * @returns {number}
         */
        const readVarInt = () => {
            let v = 0, b;
            do {
                b = readUint8();
                v = (v << 7) | (b & 0x7f);
            } while (b & 0x80);
            return v;
        };

        // ===== HEADER =====
        if (readString(4) !== "MThd") throw new Error("Not a valid MIDI file");

        const headerLen = readUint32();
        const format = readUint16();
        const numTracks = readUint16();
        const ppq = readUint16();

        offset = 14;
        if (headerLen > 6) offset += (headerLen - 6);

        const tempos = [];
        const meta = [];
        const tracks = [];
        const timeSignatures = [];
        const keySignatures = [];
        let maxTicks = 0;

        // Global state to track instruments assigned to each of the 16 MIDI channels
        const channelPrograms = new Array(16).fill(0);
        const channelProgramChanges = Array.from({ length: 16 }, () => []);
        const channelBankChanges = Array.from({ length: 16 }, () => []);

        // Loop through chunks to find MIDI tracks (MTrk)
        for (let i = 0; i < numTracks; i++) {
            if (offset >= view.byteLength) break;

            const chunkId = readString(4);
            if (chunkId !== "MTrk") {
                const skipLen = readUint32();
                offset += skipLen;
                continue;
            }

            const trackLen = readUint32();
            const trackEnd = offset + trackLen;

            let tick = 0;
            let lastStatus = 0;

            let trackName = "";
            const trackMeta = [];
            const notes = [];
            const lyrics = [];
            const controllers = [];
            const pitchBends = [];
            const activeNotes = {};

            // Tracking which channels are active within this specific track
            const channelSet = new Set();

            while (offset < trackEnd) {
                tick += readVarInt();

                let status = readUint8();
                // Handle Running Status (omitted status byte if same as previous)
                if (status < 0x80) {
                    offset--;
                    status = lastStatus;
                } else if (status < 0xF0) {
                    // Running status only applies to channel messages (0x80 - 0xEF)
                    lastStatus = status;
                }

                const type = status & 0xf0;
                const channel = status & 0x0f;

                // ===== NOTE ON / NOTE OFF =====
                if (type === 0x90 || type === 0x80) {
                    const pitch = readUint8();
                    const velocity = readUint8();
                    const key = `${channel}_${pitch}`;

                    channelSet.add(channel);
                    // Note On with velocity 0 is treated as Note Off

                    if (type === 0x80 || velocity === 0) {
                        if (activeNotes[key]) {
                            const n = activeNotes[key];
                            n.durationTicks = tick - n.ticks;
                            notes.push(n);
                            delete activeNotes[key];
                        }
                    } else {
                        activeNotes[key] = {
                            midi: pitch,
                            ticks: tick,
                            velocity: velocity / 127,
                            channel,
                            instrument: channelPrograms[channel]
                        };
                    }
                }

                // ===== PROGRAM CHANGE =====
                else if (type === 0xC0) {
                    const program = readUint8();

                    channelPrograms[channel] = program;

                    channelProgramChanges[channel].push({
                        ticks: tick,
                        program
                    });
                }

                // ===== META =====
                else if (status === 0xFF) {
                    const metaType = readUint8();
                    const len = readVarInt();

                    const dataStart = offset;

                    // Set Tempo (Microseconds per quarter note)
                    if (metaType === 0x51 && len === 3) {
                        const mpqn =
                            (readUint8() << 16) |
                            (readUint8() << 8) |
                            readUint8();

                        tempos.push({
                            ticks: tick,
                            bpm: 60000000 / mpqn
                        });
                    }
                    // Time Signature (4 bytes: numerator, denominator exp, clocks/tick, 32nds/quarter)
                    else if (metaType === 0x58 && len === 4) {
                        // Jangan ubah kode dalam blok ini
                        const numerator = readUint8();
                        const denominatorExp = readUint8();
                        readUint8(); // MIDI clocks per metronome click (tidak dipakai)
                        readUint8(); // 32nd notes per MIDI quarter note (tidak dipakai)
                        timeSignatures.push({
                            ticks: tick,
                            numerator: numerator,
                            denominator: 2 ** denominatorExp
                        });
                    }
                    // Key Signature (2 bytes: fifths, mode)
                    else if (metaType === 0x59 && len >= 2) {
                        let fifths = readUint8();
                        if (fifths > 7) {
                            fifths -= 256;
                        }
                        const modeVal = readUint8();
                        const mode = modeVal === 0 ? 'major' : 'minor';
                        keySignatures.push({
                            ticks: tick,
                            fifths: fifths,
                            mode: mode
                        });
                        if (len > 2) {
                            offset += (len - 2);
                        }
                    }
                    // Sequence/Track Name
                    else if (metaType === 0x03) {
                        trackName = readString(len);
                    }
                    // Lyric Event
                    else if (metaType === 0x05) {
                        lyrics.push({
                            ticks: tick,
                            text: readString(len)
                        });
                    }
                    else {
                        offset += len;
                    }

                    meta.push({
                        ticks: tick,
                        type: metaType,
                        data: buffer.slice(dataStart, dataStart + len)
                    });
                    trackMeta.push({
                        ticks: tick,
                        type: metaType,
                        data: buffer.slice(dataStart, dataStart + len)
                    });
                }

                // ===== SYSEX =====
                else if (status === 0xF0 || status === 0xF7) {
                    const len = readVarInt();
                    offset += len;
                }

                // ===== OTHER =====
                else if (type === 0xB0) {
                    const controller = readUint8();
                    const value = readUint8();
                    controllers.push({ ticks: tick, controller, value, channel });

                    if (controller === 0) { // Bank Select MSB
                        channelBankChanges[channel].push({ ticks: tick, value });
                    }
                }
                else if (type === 0xE0) {
                    const lsb = readUint8();
                    const msb = readUint8();
                    const value = (msb << 7) | lsb;
                    pitchBends.push({ ticks: tick, value, channel });
                }
                else if (type === 0xA0) offset += 2;
                else if (type === 0xD0) offset += 1;
            }

            // Close any notes that didn't receive a Note Off before the track end
            Object.values(activeNotes).forEach(n => {
                n.durationTicks = tick - n.ticks;
                notes.push(n);
            });

            // Track the highest tick seen across all tracks
            if (tick > maxTicks) maxTicks = tick;

            // Calculate the start tick of the first musical note in this track
            let firstNoteTick = Infinity;
            for (const n of notes) {
                if (n.ticks < firstNoteTick) firstNoteTick = n.ticks;
            }

            if (options.normalize && firstNoteTick !== Infinity) {
                for (const n of notes) n.ticks -= firstNoteTick;
                for (const l of lyrics) l.ticks -= firstNoteTick;
            }

            tracks.push({
                name: trackName || `Track ${i + 1}`,
                meta: trackMeta,
                notes,
                lyrics,
                controllers,
                pitchBends,
                startTick: firstNoteTick === Infinity ? 0 : firstNoteTick,

                // List of channels used in this track
                channels: Array.from(channelSet)
            });
        }

        if(options.forceUpdateEvents) {
            // Post-processing: Hitung nada pertama per channel terlebih dahulu
            const channelFirstNoteTick = new Array(16).fill(Infinity);
            tracks.forEach(t => {
                t.notes.forEach(n => {
                    if (n.ticks < channelFirstNoteTick[n.channel]) {
                        channelFirstNoteTick[n.channel] = n.ticks;
                    }
                });
            });

            // Adjust Bank Changes: Pindahkan inisialisasi awal ke tick 0
            channelBankChanges.forEach((changes, ch) => {
                const firstNoteTick = channelFirstNoteTick[ch];
                const before = changes.filter(bc => bc.ticks < firstNoteTick);
                const after = changes.filter(bc => bc.ticks >= firstNoteTick);

                if (before.length > 0) {
                    const lastBefore = before[before.length - 1];
                    lastBefore.ticks = 0;
                    changes.splice(0, changes.length, lastBefore, ...after);
                }
            });

            tracks.forEach(t => {
                // Adjust controllers: Panning (10), Volume (7), Reverb (91), Chorus (93), Bank Select (0, 32)
                t.controllers.forEach(c => {
                    if (c.ticks < channelFirstNoteTick[c.channel]) {
                        if ([0, 32, 7, 10, 91, 93].includes(c.controller)) {
                            c.ticks = 0;
                        }
                    }
                });
                // Adjust Pitch Bends
                t.pitchBends.forEach(pb => {
                    if (pb.ticks < channelFirstNoteTick[pb.channel]) {
                        pb.ticks = 0;
                    }
                });
            });

            // Adjust Program Changes: If multiple occur before the first note, keep only the last one and shift to tick 0
            channelProgramChanges.forEach((changes, ch) => {
                const firstNoteTick = channelFirstNoteTick[ch];
                const before = changes.filter(pc => pc.ticks < firstNoteTick);
                const after = changes.filter(pc => pc.ticks >= firstNoteTick);

                if (before.length > 0) {
                    // Pick the chronologically last program change before the first note
                    const lastBefore = before[before.length - 1];
                    lastBefore.ticks = 0;

                    // Replace the array contents to keep only the effective initialization event and subsequent events
                    changes.splice(0, changes.length, lastBefore, ...after);
                }
            });
        }

        // Find the earliest note tick across all tracks
        let globalFirstTick = Infinity;
        tracks.forEach(t => {
            if (t.startTick < globalFirstTick) globalFirstTick = t.startTick;
        });

        const preparedTimeSignatures =
        (timeSignatures.length ? [...timeSignatures] : [{
            ticks: 0,
            numerator: 4,
            denominator: 4
        }])
        .sort((a, b) => a.ticks - b.ticks);

        let totalMeasures = 0;

        for (let i = 0; i < preparedTimeSignatures.length; i++) {
            const current = preparedTimeSignatures[i];

            current.ticksPerMeasure =
                (current.numerator / current.denominator) * 4 * ppq;

            current.measureStart = totalMeasures + 1;

            if (i + 1 < preparedTimeSignatures.length) {
                const next = preparedTimeSignatures[i + 1];

                totalMeasures +=
                    (next.ticks - current.ticks) /
                    current.ticksPerMeasure;
            }
        }

        const header = {
            format,
            numTracks,
            ppq,
            tempos,
            meta,
            timeSignatures,
            keySignatures,
            maxTicks: maxTicks,
            firstNoteTick: globalFirstTick === Infinity ? 0 : globalFirstTick,
            channelProgramChanges,
            channelBankChanges,
            preparedTimeSignatures: preparedTimeSignatures,

            /**
             * Retrieves the MIDI program (instrument) assigned to a channel at a specific tick.
             * @param {number} channel - MIDI channel (0-15).
             * @param {number} tick - Current tick position.
             * @returns {number} MIDI Program ID (0-127).
             */
            getInstrumentAtTick(channel, tick = 0) {
                const changes = channelProgramChanges[channel] || [];
                let program = 0;

                for (let i = 0; i < changes.length; i++) {
                    if (changes[i].ticks <= tick) {
                        program = changes[i].program;
                    } else break;
                }

                return program;
            },

            /**
             * Converts a tick value to absolute seconds using the tempo map.
             * @param {number} t - Ticks.
             * @returns {number} Seconds.
             */
            ticksToSeconds(t) {
                let s = 0, curT = 0, curB = 120;
                const sorted = [...tempos].sort((a, b) => a.ticks - b.ticks);

                for (const o of sorted) {
                    if (o.ticks > t) break;
                    s += ((o.ticks - curT) / ppq) * (60 / curB);
                    curT = o.ticks;
                    curB = o.bpm;
                }

                return s + ((t - curT) / ppq) * (60 / curB);
            },

            /**
             * Converts absolute seconds to a tick value based on the tempo map.
             * @param {number} s - Seconds.
             * @returns {number} Ticks.
             */
            secondsToTicks(s) {
                let t = 0, curS = 0, curB = 120;
                const sorted = [...tempos].sort((a, b) => a.ticks - b.ticks);

                for (const o of sorted) {
                    const deltaS = ((o.ticks - t) / ppq) * (60 / curB);
                    if (curS + deltaS > s) break;
                    curS += deltaS;
                    t = o.ticks;
                    curB = o.bpm;
                }

                return t + (s - curS) * ppq * (curB / 60);
            },

            /**
             * Converts a tick value to its musical position based on PPQ (pulses per quarter note)
             * and active time signatures.
             *
             * This function determines the measure, beat, and tick offset of a given tick position
             * in the score. It accounts for multiple time signature changes by iterating through
             * the signature segments and calculating the local position.
             *
             * @param {number} targetTick - Absolute tick position (non-negative integer).
             * @returns {Object} Musical position object containing:
             * @returns {number} return.tick - The absolute tick value provided.
             * @returns {number} return.measure - The measure number (starting from 1).
             * @returns {number} return.beat - The beat number within the measure (starting from 1).
             * @returns {number} return.tickInBeat - Tick offset within the current beat.
             * @returns {number} return.tickInMeasure - Tick offset within the current measure.
             * @returns {number} return.numerator - Time signature numerator at this tick.
             * @returns {number} return.denominator - Time signature denominator at this tick.
             * @returns {number} return.ticksPerBeat - Number of ticks per beat under current signature.
             * @returns {number} return.ticksPerMeasure - Number of ticks per measure under current signature.
             *
             * @example
             * // Assuming PPQ = 480 and 4/4 time signature
             * tickToPosition(960);
             * // => {
             * //   tick: 960,
             * //   measure: 1,
             * //   beat: 3,
             * //   tickInBeat: 0,
             * //   tickInMeasure: 960,
             * //   numerator: 4,
             * //   denominator: 4,
             * //   ticksPerBeat: 480,
             * //   ticksPerMeasure: 1920
             * // }
             */

            tickToPosition(targetTick) {
                targetTick = Math.max(0, Math.round(targetTick));

                // Default time signature
                const signatures = this.timeSignatures.length
                    ? [...this.timeSignatures]
                    : [{
                        ticks: 0,
                        numerator: 4,
                        denominator: 4
                    }];

                signatures.sort((a, b) => a.ticks - b.ticks);

                let measure = 1;

                for (let i = 0; i < signatures.length; i++) {
                    const current = signatures[i];
                    const nextTick = (i + 1 < signatures.length) ? signatures[i + 1].ticks : Number.MAX_SAFE_INTEGER;
                    const ticksPerBeat = this.ppq * (4 / current.denominator);
                    const ticksPerMeasure = ticksPerBeat * current.numerator;

                    // Target berada pada segmen ini
                    if (targetTick < nextTick) {
                        const localTick = targetTick - current.ticks;
                        const measureOffset = Math.floor(localTick / ticksPerMeasure);
                        const tickInMeasure = localTick % ticksPerMeasure;
                        const beat = Math.floor(tickInMeasure / ticksPerBeat) + 1;
                        const tickInBeat = tickInMeasure % ticksPerBeat;

                        return {
                            tick: targetTick,
                            measure: measure + measureOffset,
                            beat,
                            tickInBeat,
                            tickInMeasure,
                            numerator: current.numerator,
                            denominator: current.denominator,
                            ticksPerBeat,
                            ticksPerMeasure
                        };
                    }

                    // Tambahkan jumlah measure pada segmen ini
                    const segmentTicks = nextTick - current.ticks;

                    measure += Math.floor(segmentTicks / ticksPerMeasure);
                }

                // Fallback
                const last = signatures[signatures.length - 1];
                const ticksPerBeat = this.ppq * (4 / last.denominator);
                const ticksPerMeasure = ticksPerBeat * last.numerator;
                const localTick = targetTick - last.ticks;

                return {
                    tick: targetTick,
                    measure: measure + Math.floor(localTick / ticksPerMeasure),
                    beat: Math.floor((localTick % ticksPerMeasure) / ticksPerBeat) + 1,
                    tickInBeat: localTick % ticksPerBeat,
                    tickInMeasure: localTick % ticksPerMeasure,
                    numerator: last.numerator,
                    denominator: last.denominator,
                    ticksPerBeat,
                    ticksPerMeasure
                };
            },

            /**
             * Converts a tick value to its corresponding measure number.
             * @param {number} targetTick - The tick value to convert.
             * @returns {number} The measure number (1-based).
             */
            tickToMeasure(targetTick) {
                const signatures = this.preparedTimeSignatures;

                let current = signatures[0];

                for (let i = 1; i < signatures.length; i++) {
                    if (targetTick < signatures[i].ticks) {
                        break;
                    }
                    current = signatures[i];
                }

                return current.measureStart +
                    Math.floor(
                        (targetTick - current.ticks) /
                        current.ticksPerMeasure
                    );
            }
        };

        // Generate a summary of instruments used on each channel
        const instruments = {};

        header.channelProgramChanges.forEach((changes, ch) => {
            if (!changes.length) {
                instruments[ch] = [0];
            } else {
                const set = new Set(changes.map(c => c.program));
                instruments[ch] = Array.from(set);
            }
        });

        return { header, tracks, instruments };
    }
}

// Export for Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MidiParser;
}
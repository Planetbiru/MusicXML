/**
 * MidiToMusicXML converts parsed MIDI data (using MidiParser) into a valid MusicXML 4.0 Partwise XML string.
 * It maps active MIDI channels to score parts, handles pitch calculations, drum notations, time signatures,
 * tempo changes, lyrics, dynamics, chords, and automatic rest filling.
 *
 * Compatible with Node.js and browser environments.
 * 
 * @author Antigravity
 */
class MidiToMusicXML {
    
    /**
     * Converts raw MIDI binary data (ArrayBuffer/Buffer) into a MusicXML string.
     * @param {ArrayBuffer|Buffer} midiBuffer 
     * @param {Object} [options] 
     * @returns {string} MusicXML String
     */
    static convert(midiBuffer, options = {}) {
        // Resolve Buffer to ArrayBuffer for MidiParser
        let buffer = midiBuffer;
        if (typeof Buffer !== 'undefined' && midiBuffer instanceof Buffer) {
            buffer = midiBuffer.buffer.slice(midiBuffer.byteOffset, midiBuffer.byteOffset + midiBuffer.byteLength);
        }
        
        const opts = Object.assign({
            title: "MIDI Export",
            creator: "Planetbiru MusicXML JS",
            divisions: 4,
            selectedChannels: null,
            useRestFilling: true,
            normalize: false,
            forceUpdateEvents: true
        }, options);
        
        // Parse MIDI binary using the project's MidiParser
        const parsed = MidiParser.parse(buffer, {
            normalize: opts.normalize,
            forceUpdateEvents: opts.forceUpdateEvents
        });
        
        return this.convertParsed(parsed, opts);
    }
    
    /**
     * Converts parsed MIDI object into a MusicXML string.
     * @param {Object} parsed - Parsed MIDI object from MidiParser
     * @param {Object} opts - Conversion options
     * @returns {string} MusicXML String
     */
    static convertParsed(parsed, opts) {
        const ppq = parsed.header.ppq;
        const divisions = opts.divisions;
        const title = opts.title;
        
        // 1. Group notes and controllers from all tracks by channel
        const channelNotes = Array.from({ length: 16 }, () => []);
        const ccVolumeMap = Array.from({ length: 16 }, () => ({}));
        const ccExpressionMap = Array.from({ length: 16 }, () => ({}));
        
        // Collect global lyrics
        const globalLyrics = {};
        parsed.tracks.forEach(track => {
            if (track.notes) {
                track.notes.forEach(n => {
                    channelNotes[n.channel].push(n);
                });
            }
            if (track.lyrics) {
                track.lyrics.forEach(l => {
                    globalLyrics[l.ticks] = l.text;
                });
            }
            if (track.controllers) {
                track.controllers.forEach(c => {
                    if (c.controller === 7) {
                        ccVolumeMap[c.channel][c.ticks] = c.value;
                    } else if (c.controller === 11) {
                        ccExpressionMap[c.channel][c.ticks] = c.value;
                    }
                });
            }
        });
        
        // Sort notes chronologically
        channelNotes.forEach(notes => {
            notes.sort((a, b) => a.ticks - b.ticks);
        });
        
        // Determine active channels
        let activeChannels = [];
        if (opts.selectedChannels) {
            activeChannels = opts.selectedChannels;
        } else {
            for (let ch = 0; ch < 16; ch++) {
                if (channelNotes[ch].length > 0) {
                    activeChannels.push(ch);
                }
            }
        }
        activeChannels.sort((a, b) => a - b);
        
        // Calculate note range per channel for clef assignment
        const channelMinNote = new Array(16).fill(127);
        const channelMaxNote = new Array(16).fill(0);
        activeChannels.forEach(ch => {
            channelNotes[ch].forEach(n => {
                if (n.midi < channelMinNote[ch]) channelMinNote[ch] = n.midi;
                if (n.midi > channelMaxNote[ch]) channelMaxNote[ch] = n.midi;
            });
        });
        
        // 2. Identify lyric carrier channel
        let lyricChannelId = -1;
        if (activeChannels.includes(3)) { // MIDI channel 4 (0-indexed 3)
            lyricChannelId = 3;
        } else if (activeChannels.length > 0) {
            lyricChannelId = activeChannels[0];
        }
        
        // Calculate total measures based on max ticks in header or notes
        const maxTicks = parsed.header.maxTicks || 0;
        const lastPosition = parsed.header.tickToPosition(maxTicks);
        const totalMeasures = Math.max(1, lastPosition.measure);
        
        // Cache time signature segments
        const preparedTimeSignatures = parsed.header.preparedTimeSignatures || [{
            ticks: 0,
            numerator: 4,
            denominator: 4,
            ticksPerMeasure: 4 * ppq,
            measureStart: 1
        }];
        
        function getTimeSigForMeasure(m) {
            let activeSig = preparedTimeSignatures[0];
            for (let i = 1; i < preparedTimeSignatures.length; i++) {
                if (m >= preparedTimeSignatures[i].measureStart) {
                    activeSig = preparedTimeSignatures[i];
                } else {
                    break;
                }
            }
            return activeSig;
        }
        
        function getMeasureStartTick(m) {
            const sig = getTimeSigForMeasure(m);
            const measuresInSig = m - sig.measureStart;
            return sig.ticks + measuresInSig * sig.ticksPerMeasure;
        }
        
        // 3. Build XML Part List
        let xmlPartList = "";
        activeChannels.forEach(ch => {
            const partId = "P" + (ch + 1);
            const progChanges = parsed.header.channelProgramChanges[ch] || [];
            const initialProgram = progChanges.length > 0 ? progChanges[0].program : 0;
            
            let partName, partAbbr, partSound;
            
            if (ch === 9) { // Drums (channel 10)
                partName = "Drum Kit";
                partAbbr = "D. Kit";
                partSound = "percussion";
                
                xmlPartList += `    <score-part id="${partId}">\n`;
                xmlPartList += `      <part-name>${partName}</part-name>\n`;
                xmlPartList += `      <part-abbreviation>${partAbbr}</part-abbreviation>\n`;
                
                // Add all possible score-instruments for channel 10 drum set mapping
                Object.keys(this.DRUM_SET).forEach(key => {
                    const midiCode = parseInt(key);
                    const drumDetails = this.DRUM_SET[midiCode];
                    const instId = `${partId}-I${midiCode + 1}`;
                    xmlPartList += `      <score-instrument id="${instId}">\n`;
                    xmlPartList += `        <instrument-name>${this.escapeXML(drumDetails[0])}</instrument-name>\n`;
                    if (drumDetails[2]) {
                        xmlPartList += `        <instrument-sound>${drumDetails[2]}</instrument-sound>\n`;
                    }
                    xmlPartList += `      </score-instrument>\n`;
                });
                
                xmlPartList += `      <midi-device port="1"/>\n`;
                
                Object.keys(this.DRUM_SET).forEach(key => {
                    const midiCode = parseInt(key);
                    const drumDetails = this.DRUM_SET[midiCode];
                    const instId = `${partId}-I${midiCode + 1}`;
                    xmlPartList += `      <midi-instrument id="${instId}">\n`;
                    xmlPartList += `        <midi-channel>10</midi-channel>\n`;
                    xmlPartList += `        <midi-program>1</midi-program>\n`;
                    xmlPartList += `        <midi-unpitched>${midiCode + 1}</midi-unpitched>\n`;
                    xmlPartList += `        <volume>80</volume>\n`;
                    xmlPartList += `      </midi-instrument>\n`;
                });
                
                xmlPartList += `    </score-part>\n`;
                
            } else { // Melodic channels
                const instInfo = this.INSTRUMENT_LIST[initialProgram] || ["Instrument " + (initialProgram + 1), "Instr.", "keyboard.piano"];
                partName = instInfo[0];
                partAbbr = instInfo[1];
                partSound = instInfo[2];
                
                const instId = `${partId}-I1`;
                xmlPartList += `    <score-part id="${partId}">\n`;
                xmlPartList += `      <part-name>${partName}</part-name>\n`;
                xmlPartList += `      <part-abbreviation>${partAbbr}</part-abbreviation>\n`;
                xmlPartList += `      <score-instrument id="${instId}">\n`;
                xmlPartList += `        <instrument-name>${partName}</instrument-name>\n`;
                xmlPartList += `        <instrument-sound>${partSound}</instrument-sound>\n`;
                xmlPartList += `      </score-instrument>\n`;
                xmlPartList += `      <midi-instrument id="${instId}">\n`;
                xmlPartList += `        <midi-channel>${ch + 1}</midi-channel>\n`;
                xmlPartList += `        <midi-program>${initialProgram + 1}</midi-program>\n`;
                xmlPartList += `        <volume>80</volume>\n`;
                xmlPartList += `      </midi-instrument>\n`;
                xmlPartList += `    </score-part>\n`;
            }
        });
        
        // Dynamic Controller Lookup Functions
        function getCCValue(map, ch, tick, defValue) {
            const channelMap = map[ch];
            if (!channelMap || Object.keys(channelMap).length === 0) return defValue;
            let lastVal = defValue;
            const ticks = Object.keys(channelMap).map(Number).sort((a,b) => a-b);
            for (let t of ticks) {
                if (t <= tick) {
                    lastVal = channelMap[t];
                } else {
                    break;
                }
            }
            return lastVal;
        }
        
        // 4. Generate parts scores
        let xmlPartsContent = "";
        
        activeChannels.forEach(ch => {
            const partId = "P" + (ch + 1);
            xmlPartsContent += `  <part id="${partId}">\n`;
            
            // Keep track of spilled notes: noteCode -> { remainingTicks, dynamics }
            let tieContinue = {};
            
            // Initial signature & clef state
            let currentNumerator = 0;
            let currentDenominator = 0;
            let currentFifths = null;
            let currentMode = "";
            
            for (let m = 1; m <= totalMeasures; m++) {
                const measureStartTick = getMeasureStartTick(m);
                const activeSig = getTimeSigForMeasure(m);
                const measureEndTick = measureStartTick + activeSig.ticksPerMeasure;
                const measureLengthTicks = activeSig.ticksPerMeasure;
                const xmlMeasureLength = (measureLengthTicks * divisions) / ppq;
                
                xmlPartsContent += `    <measure number="${m}">\n`;
                
                // Add <attributes> block if needed (always in measure 1, or when signatures change)
                let needAttr = (m === 1);
                let attrContent = "";
                
                if (m === 1) {
                    attrContent += `        <divisions>${divisions}</divisions>\n`;
                }
                
                // Key Signature
                // Look for key signatures matching current ticks
                const kSigList = parsed.header.keySignatures || [];
                let activeKSig = { fifths: 0, mode: "major" };
                kSigList.forEach(k => {
                    if (k.ticks <= measureStartTick) {
                        activeKSig = k;
                    }
                });
                
                if (m === 1 || activeKSig.fifths !== currentFifths || activeKSig.mode !== currentMode) {
                    currentFifths = activeKSig.fifths;
                    currentMode = activeKSig.mode;
                    if (ch !== 9) { // No key signatures for percussion
                        attrContent += `        <key>\n`;
                        attrContent += `          <fifths>${currentFifths}</fifths>\n`;
                        attrContent += `          <mode>${currentMode}</mode>\n`;
                        attrContent += `        </key>\n`;
                        needAttr = true;
                    }
                }
                
                // Time Signature
                if (m === 1 || activeSig.numerator !== currentNumerator || activeSig.denominator !== currentDenominator) {
                    currentNumerator = activeSig.numerator;
                    currentDenominator = activeSig.denominator;
                    attrContent += `        <time>\n`;
                    attrContent += `          <beats>${currentNumerator}</beats>\n`;
                    attrContent += `          <beat-type>${currentDenominator}</beat-type>\n`;
                    attrContent += `        </time>\n`;
                    needAttr = true;
                }
                
                // Clef
                if (m === 1) {
                    let clefSign = 'G', clefLine = 2;
                    if (ch === 9) {
                        clefSign = 'percussion';
                        clefLine = null;
                    } else {
                        const min = channelMinNote[ch];
                        const max = channelMaxNote[ch];
                        if (min < 48) {
                            clefSign = 'F'; clefLine = 4;
                        } else if (min < 57 && max <= 76 && (min + max) / 2 < 63) {
                            clefSign = 'C'; clefLine = 3;
                        }
                    }
                    attrContent += `        <clef>\n`;
                    attrContent += `          <sign>${clefSign}</sign>\n`;
                    if (clefLine !== null) {
                        attrContent += `          <line>${clefLine}</line>\n`;
                    }
                    attrContent += `        </clef>\n`;
                    needAttr = true;
                }
                
                if (needAttr) {
                    xmlPartsContent += `      <attributes>\n${attrContent}      </attributes>\n`;
                }
                
                // Tempo Direction changes (emitted on first active channel or first track)
                if (ch === activeChannels[0]) {
                    const tempos = parsed.header.tempos || [];
                    tempos.forEach(t => {
                        if (t.ticks >= measureStartTick && t.ticks < measureEndTick) {
                            xmlPartsContent += `      <direction placement="above">\n`;
                            xmlPartsContent += `        <direction-type>\n`;
                            xmlPartsContent += `          <metronome>\n`;
                            xmlPartsContent += `            <beat-unit>quarter</beat-unit>\n`;
                            xmlPartsContent += `            <per-minute>${Math.round(t.bpm)}</per-minute>\n`;
                            xmlPartsContent += `          </metronome>\n`;
                            xmlPartsContent += `        </direction-type>\n`;
                            xmlPartsContent += `        <sound tempo="${Math.round(t.bpm)}"/>\n`;
                            xmlPartsContent += `      </direction>\n`;
                        }
                    });
                }
                
                // Filter notes for this measure
                const notesInMeasure = channelNotes[ch].filter(n => n.ticks >= measureStartTick && n.ticks < measureEndTick);
                
                // Find lyrics in range for this channel if it's the lyric carrier
                const lyricCarrier = {};
                if (ch === lyricChannelId) {
                    Object.keys(globalLyrics).forEach(tickStr => {
                        const tick = parseInt(tickStr);
                        if (tick >= measureStartTick && tick < measureEndTick) {
                            lyricCarrier[tick] = globalLyrics[tick];
                        }
                    });
                }
                
                let lastNoteXmlEnd = 0;
                let lastNoteAbstime = -1;
                
                // Process tied notes continuing from previous measure
                const tieKeys = Object.keys(tieContinue).map(Number);
                if (tieKeys.length > 0) {
                    let firstTie = true;
                    tieKeys.forEach(noteCode => {
                        const tieInfo = tieContinue[noteCode];
                        const remaining = tieInfo.remainingTicks;
                        const continueTicks = Math.min(remaining, measureLengthTicks);
                        
                        let xmlDuration = Math.round((continueTicks * divisions) / ppq);
                        if (xmlDuration <= 0 && continueTicks > 0) xmlDuration = 1;
                        
                        const isStop = (remaining <= measureLengthTicks);
                        
                        xmlPartsContent += this.generateNoteXML({
                            isChord: !firstTie,
                            isRest: false,
                            ch,
                            partId,
                            noteCode,
                            durationDivs: xmlDuration,
                            divisions,
                            dynamics: tieInfo.dynamics,
                            tieType: isStop ? "stop" : "stop_start",
                            lyricText: null, // No lyric on tied continuations
                            beamType: null
                        });
                        
                        if (firstTie) lastNoteXmlEnd = xmlDuration;
                        lastNoteAbstime = measureStartTick;
                        
                        if (isStop) {
                            delete tieContinue[noteCode];
                        } else {
                            tieContinue[noteCode].remainingTicks -= continueTicks;
                        }
                        firstTie = false;
                    });
                }
                
                // Process regular notes in this measure
                notesInMeasure.forEach((note, index) => {
                    const offsetTicks = note.ticks - measureStartTick;
                    const xmlStart = Math.round((offsetTicks * divisions) / ppq);
                    
                    const chordTolerance = (ch === 9) ? 10 : 4;
                    const isChord = (index > 0 && Math.abs(note.ticks - notesInMeasure[index - 1].ticks) < chordTolerance);
                    
                    // Fill gap with rest
                    if (opts.useRestFilling && !isChord && xmlStart > lastNoteXmlEnd) {
                        const gapDivs = xmlStart - lastNoteXmlEnd;
                        const restPieces = this.splitIntoRepresentableDurations(gapDivs, divisions);
                        restPieces.forEach(restDivs => {
                            xmlPartsContent += this.generateNoteXML({
                                isChord: false,
                                isRest: true,
                                ch,
                                partId,
                                noteCode: 0,
                                durationDivs: restDivs,
                                divisions,
                                dynamics: 0,
                                tieType: null,
                                lyricText: null,
                                beamType: null
                            });
                        });
                    }
                    
                    const localEndTicks = offsetTicks + note.durationTicks;
                    let noteDurationTicks = note.durationTicks;
                    let isSpilled = false;
                    
                    // Volume dynamics formula
                    const velocity = Math.round(note.velocity * 127);
                    const volume = getCCValue(ccVolumeMap, ch, note.ticks, 100);
                    const expression = getCCValue(ccExpressionMap, ch, note.ticks, 127);
                    const dynamicsVal = Math.round((velocity / 127) * (volume / 127) * (expression / 127) * 100);
                    
                    if (localEndTicks > measureLengthTicks) {
                        noteDurationTicks = measureLengthTicks - offsetTicks;
                        isSpilled = true;
                        
                        tieContinue[note.midi] = {
                            remainingTicks: note.durationTicks - noteDurationTicks,
                            dynamics: dynamicsVal
                        };
                    }
                    
                    let noteDivs = Math.round((noteDurationTicks * divisions) / ppq);
                    if (noteDivs <= 0 && noteDurationTicks > 0) noteDivs = 1;
                    
                    // Retrieve lyric
                    const lyricText = lyricCarrier[note.ticks] || null;
                    
                    // Simple Beam detection: beam notes within the same beat (eighth notes or smaller)
                    let beamType = null;
                    const noteType = this.getNoteType(noteDivs, divisions);
                    const isBeamable = ['eighth', '16th', '32nd', '64th'].includes(noteType);
                    if (isBeamable && !isChord) {
                        const ticksPerBeat = ppq;
                        const beatIndex = Math.floor(offsetTicks / ticksPerBeat);
                        const beatStart = beatIndex * ticksPerBeat;
                        const beatEnd = (beatIndex + 1) * ticksPerBeat;
                        
                        // Gather notes in the same beat
                        const siblingNotes = notesInMeasure.filter(n => {
                            const off = n.ticks - measureStartTick;
                            return off >= beatStart && off < beatEnd;
                        });
                        
                        if (siblingNotes.length > 1) {
                            const siblingIndex = siblingNotes.indexOf(note);
                            if (siblingIndex === 0) {
                                beamType = "begin";
                            } else if (siblingIndex === siblingNotes.length - 1) {
                                beamType = "end";
                            } else {
                                beamType = "continue";
                            }
                        }
                    }
                    
                    // Split unrepresentable note duration into pieces and tie them together
                    if (noteDivs > 0) {
                        const notePieces = this.splitIntoRepresentableDurations(noteDivs, divisions);
                        if (notePieces.length > 1) {
                            notePieces.forEach((pieceDivs, pIdx) => {
                                const isFirstPiece = (pIdx === 0);
                                const isLastPiece = (pIdx === notePieces.length - 1);
                                
                                let pieceTie = null;
                                if (isSpilled) {
                                    pieceTie = "start"; // Spilled note will tie anyway
                                }
                                
                                if (isFirstPiece) {
                                    pieceTie = "start";
                                } else if (isLastPiece && !isSpilled) {
                                    pieceTie = "stop";
                                } else {
                                    pieceTie = "stop_start";
                                }
                                
                                xmlPartsContent += this.generateNoteXML({
                                    isChord: isChord && isFirstPiece, // Chord only applies to the first piece start
                                    isRest: false,
                                    ch,
                                    partId,
                                    noteCode: note.midi,
                                    durationDivs: pieceDivs,
                                    divisions,
                                    dynamics: dynamicsVal,
                                    tieType: pieceTie,
                                    lyricText: isFirstPiece ? lyricText : null, // Lyric on first piece
                                    beamType: isFirstPiece ? beamType : null
                                });
                            });
                        } else {
                            // Emit note normally
                            xmlPartsContent += this.generateNoteXML({
                                isChord,
                                isRest: false,
                                ch,
                                partId,
                                noteCode: note.midi,
                                durationDivs: noteDivs,
                                divisions,
                                dynamics: dynamicsVal,
                                tieType: isSpilled ? "start" : null,
                                lyricText,
                                beamType
                            });
                        }
                    }
                    
                    if (!isChord) {
                        lastNoteXmlEnd = xmlStart + noteDivs;
                    }
                    lastNoteAbstime = note.ticks;
                });
                
                // Fill end of measure with rests if needed
                if (opts.useRestFilling && lastNoteXmlEnd < xmlMeasureLength) {
                    const remainingDivs = xmlMeasureLength - lastNoteXmlEnd;
                    const restPieces = this.splitIntoRepresentableDurations(remainingDivs, divisions);
                    restPieces.forEach(restDivs => {
                        xmlPartsContent += this.generateNoteXML({
                            isChord: false,
                            isRest: true,
                            ch,
                            partId,
                            noteCode: 0,
                            durationDivs: restDivs,
                            divisions,
                            dynamics: 0,
                            tieType: null,
                            lyricText: null,
                            beamType: null
                        });
                    });
                }
                
                xmlPartsContent += `    </measure>\n`;
            }
            xmlPartsContent += `  </part>\n`;
        });
        
        // 5. Construct full MusicXML template
        let xml = `<?xml version="1.0" encoding="UTF-8" standalone="no"?>\n`;
        xml += `<!DOCTYPE score-partwise PUBLIC "-//Recordare//DTD MusicXML 4.0 Partwise//EN" "http://www.musicxml.org/dtds/partwise.dtd">\n`;
        xml += `<score-partwise version="4.0">\n`;
        xml += `  <work>\n`;
        xml += `    <work-title>${this.escapeXML(title)}</work-title>\n`;
        xml += `  </work>\n`;
        xml += `  <identification>\n`;
        xml += `    <creator type="composer">${this.escapeXML(opts.creator)}</creator>\n`;
        xml += `    <encoding>\n`;
        xml += `      <encoding-date>${new Date().toISOString().split('T')[0]}</encoding-date>\n`;
        xml += `      <software>Planetbiru MusicXML JS</software>\n`;
        xml += `    </encoding>\n`;
        xml += `  </identification>\n`;
        xml += `  <part-list>\n`;
        xml += xmlPartList;
        xml += `  </part-list>\n`;
        xml += xmlPartsContent;
        xml += `</score-partwise>\n`;
        
        return xml;
    }
    
    /**
     * Helper to create a single MusicXML <note> block
     */
    static generateNoteXML(params) {
        const { isChord, isRest, ch, partId, noteCode, durationDivs, divisions, dynamics, tieType, lyricText, beamType } = params;
        
        const type = this.getNoteType(durationDivs, divisions);
        const dots = this.getNoteDots(durationDivs, divisions);
        
        let xml = "      <note>\n";
        
        if (isChord) {
            xml += "        <chord/>\n";
        }
        
        if (isRest) {
            xml += "        <rest/>\n";
        } else if (ch === 9) { // Drum percussion mapping
            const visuals = this.getDrumVisuals(noteCode);
            xml += "        <unpitched>\n";
            xml += `          <display-step>${visuals.step}</display-step>\n`;
            xml += `          <display-octave>${visuals.octave}</display-octave>\n`;
            xml += "        </unpitched>\n";
            if (visuals.notehead !== 'normal') {
                xml += `        <notehead>${visuals.notehead}</notehead>\n`;
            }
        } else { // Pitched mapping
            const pitchStr = this.NOTE_LIST[noteCode];
            if (pitchStr) {
                const step = pitchStr.charAt(0);
                const isSharp = pitchStr.includes('s');
                const octave = parseInt(pitchStr.replace(/[^-\d]/g, ''), 10);
                
                xml += "        <pitch>\n";
                xml += `          <step>${step}</step>\n`;
                if (isSharp) {
                    xml += `          <alter>1</alter>\n`;
                }
                xml += `          <octave>${octave}</octave>\n`;
                xml += "        </pitch>\n";
            }
        }
        
        xml += `        <duration>${durationDivs}</duration>\n`;
        xml += "        <voice>1</voice>\n";
        xml += `        <type>${type}</type>\n`;
        
        for (let i = 0; i < dots; i++) {
            xml += "        <dot/>\n";
        }
        
        // Add stems for drums and pitched
        if (ch === 9) {
            const visuals = this.getDrumVisuals(noteCode);
            xml += `        <stem>${visuals.stem}</stem>\n`;
            xml += `        <instrument id="${partId}-I${noteCode + 1}"/>\n`;
        } else if (!isRest) {
            xml += `        <stem>up</stem>\n`;
        }
        
        // Tie notations
        if (tieType === "start") {
            xml += `        <tie type="start"/>\n`;
        } else if (tieType === "stop") {
            xml += `        <tie type="stop"/>\n`;
        } else if (tieType === "stop_start") {
            xml += `        <tie type="stop"/>\n`;
            xml += `        <tie type="start"/>\n`;
        }
        
        // Dynamic volume percentage mapping
        if (!isRest && dynamics > 0) {
            xml += `        <dynamics>${dynamics}</dynamics>\n`;
        }
        
        // Beam
        if (beamType) {
            xml += `        <beam number="1">${beamType}</beam>\n`;
        }
        
        // Lyric
        if (lyricText) {
            xml += `        <lyric>\n`;
            xml += `          <syllabic>single</syllabic>\n`;
            xml += `          <text>${this.escapeXML(lyricText)}</text>\n`;
            xml += `        </lyric>\n`;
        }
        
        // Tie stops/starts
        if (tieType) {
            xml += "        <notations>\n";
            if (tieType === "start") {
                xml += `          <tied type="start"/>\n`;
            } else if (tieType === "stop") {
                xml += `          <tied type="stop"/>\n`;
            } else if (tieType === "stop_start") {
                xml += `          <tied type="stop"/>\n`;
                xml += `          <tied type="start"/>\n`;
            }
            xml += "        </notations>\n";
        }
        
        xml += "      </note>\n";
        return xml;
    }
    
    /**
     * Splits non-standard duration in divisions to pieces of standard notation durations.
     */
    static splitIntoRepresentableDurations(duration, divisions) {
        const pieces = [];
        let remaining = duration;
        const typeValues = [
            { name: 'maxima', val: 8 },
            { name: 'long', val: 4 },
            { name: 'breve', val: 2 },
            { name: 'whole', val: 1 },
            { name: 'half', val: 0.5 },
            { name: 'quarter', val: 0.25 },
            { name: 'eighth', val: 0.125 },
            { name: '16th', val: 0.0625 },
            { name: '32nd', val: 0.03125 },
            { name: '64th', val: 0.015625 },
            { name: '128th', val: 0.0078125 },
            { name: '256th', val: 0.00390625 },
            { name: '512th', val: 0.001953125 },
            { name: '1024th', val: 0.0009765625 }
        ];

        while (remaining > 0.01) {
            const value = remaining / (4 * divisions);
            let foundPiece = 0;
            for (let i = 0; i < typeValues.length; i++) {
                const type = typeValues[i];
                if (value >= type.val - 0.001) {
                    const baseDuration = type.val * 4 * divisions;
                    const d3 = baseDuration * 1.875;
                    const d2 = baseDuration * 1.75;
                    const d1 = baseDuration * 1.5;

                    if (remaining >= d3 - 0.01) {
                        foundPiece = d3;
                    } else if (remaining >= d2 - 0.01) {
                        foundPiece = d2;
                    } else if (remaining >= d1 - 0.01) {
                        foundPiece = d1;
                    } else {
                        foundPiece = baseDuration;
                    }
                    break;
                }
            }
            if (foundPiece > 0) {
                pieces.push(Math.round(foundPiece));
                remaining -= foundPiece;
            } else {
                pieces.push(Math.round(remaining));
                break;
            }
        }
        return pieces;
    }
    
    static getNoteType(duration, divisions) {
        if (divisions <= 0 || duration <= 0) {
            return '1024th';
        }
        const value = duration / (4 * divisions);
        const typeValues = [
            { name: 'maxima', val: 8 },
            { name: 'long', val: 4 },
            { name: 'breve', val: 2 },
            { name: 'whole', val: 1 },
            { name: 'half', val: 0.5 },
            { name: 'quarter', val: 0.25 },
            { name: 'eighth', val: 0.125 },
            { name: '16th', val: 0.0625 },
            { name: '32nd', val: 0.03125 },
            { name: '64th', val: 0.015625 },
            { name: '128th', val: 0.0078125 },
            { name: '256th', val: 0.00390625 },
            { name: '512th', val: 0.001953125 },
            { name: '1024th', val: 0.0009765625 }
        ];
        for (let i = 0; i < typeValues.length; i++) {
            if (value >= typeValues[i].val - 0.0001) {
                return typeValues[i].name;
            }
        }
        return '1024th';
    }

    static getNoteDots(duration, divisions) {
        if (divisions <= 0 || duration <= 0) {
            return 0;
        }
        const value = duration / (4 * divisions);
        const typeValues = [
            { name: 'maxima', val: 8 },
            { name: 'long', val: 4 },
            { name: 'breve', val: 2 },
            { name: 'whole', val: 1 },
            { name: 'half', val: 0.5 },
            { name: 'quarter', val: 0.25 },
            { name: 'eighth', val: 0.125 },
            { name: '16th', val: 0.0625 },
            { name: '32nd', val: 0.03125 },
            { name: '64th', val: 0.015625 },
            { name: '128th', val: 0.0078125 },
            { name: '256th', val: 0.00390625 },
            { name: '512th', val: 0.001953125 },
            { name: '1024th', val: 0.0009765625 }
        ];
        for (let i = 0; i < typeValues.length; i++) {
            const typeVal = typeValues[i].val;
            if (value >= typeVal - 0.0001) {
                const baseDuration = typeVal * 4 * divisions;
                const ratio = duration / baseDuration;
                if (Math.abs(ratio - 1.5) < 0.01) {
                    return 1;
                }
                if (Math.abs(ratio - 1.75) < 0.01) {
                    return 2;
                }
                if (Math.abs(ratio - 1.875) < 0.01) {
                    return 3;
                }
                break;
            }
        }
        return 0;
    }

    static getDrumVisuals(noteCode) {
        let step = 'G', octave = 5, notehead = 'x', stem = 'up';
        switch (noteCode) {
            case 35:
            case 36:
                step = 'F'; octave = 4; notehead = 'normal'; stem = 'down';
                break;
            case 37:
                step = 'C'; octave = 5; notehead = 'x'; stem = 'up';
                break;
            case 38:
                step = 'C'; octave = 5; notehead = 'normal'; stem = 'up';
                break;
            case 40:
                step = 'C'; octave = 5; notehead = 'slash'; stem = 'up';
                break;
            case 39:
                step = 'A'; octave = 5; notehead = 'x'; stem = 'up';
                break;
            case 41:
            case 43:
                step = 'A'; octave = 4; notehead = 'normal'; stem = 'up';
                break;
            case 42:
            case 46:
                step = 'G'; octave = 5; notehead = 'x'; stem = 'up';
                break;
            case 44:
                step = 'D'; octave = 4; notehead = 'x'; stem = 'down';
                break;
            case 45:
            case 47:
                step = 'D'; octave = 5; notehead = 'normal'; stem = 'up';
                break;
            case 48:
            case 50:
                step = 'E'; octave = 5; notehead = 'normal'; stem = 'up';
                break;
            case 49:
            case 57:
                step = 'A'; octave = 5; notehead = 'x'; stem = 'up';
                break;
            case 51:
            case 59:
                step = 'F'; octave = 5; notehead = 'x'; stem = 'up';
                break;
            case 55:
                step = 'G'; octave = 5; notehead = 'x'; stem = 'up';
                break;
            default:
                step = 'G'; octave = 5; notehead = 'x'; stem = 'up';
                break;
        }
        return { step, octave, notehead, stem };
    }

    static escapeXML(str) {
        if (!str) return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }
}

// NOTE_LIST mapping
MidiToMusicXML.NOTE_LIST = [
    'C-1','Cs-1','D-1','Ds-1','E-1','F-1','Fs-1','G-1','Gs-1','A-1','As-1','B-1',
    'C0', 'Cs0', 'D0', 'Ds0', 'E0', 'F0', 'Fs0', 'G0', 'Gs0', 'A0', 'As0', 'B0',
    'C1', 'Cs1', 'D1', 'Ds1', 'E1', 'F1', 'Fs1', 'G1', 'Gs1', 'A1', 'As1', 'B1',
    'C2', 'Cs2', 'D2', 'Ds2', 'E2', 'F2', 'Fs2', 'G2', 'Gs2', 'A2', 'As2', 'B2',
    'C3', 'Cs3', 'D3', 'Ds3', 'E3', 'F3', 'Fs3', 'G3', 'Gs3', 'A3', 'As3', 'B3',
    'C4', 'Cs4', 'D4', 'Ds4', 'E4', 'F4', 'Fs4', 'G4', 'Gs4', 'A4', 'As4', 'B4',
    'C5', 'Cs5', 'D5', 'Ds5', 'E5', 'F5', 'Fs5', 'G5', 'Gs5', 'A5', 'As5', 'B5',
    'C6', 'Cs6', 'D6', 'Ds6', 'E6', 'F6', 'Fs6', 'G6', 'Gs6', 'A6', 'As6', 'B6',
    'C7', 'Cs7', 'D7', 'Ds7', 'E7', 'F7', 'Fs7', 'G7', 'Gs7', 'A7', 'As7', 'B7',
    'C8', 'Cs8', 'D8', 'Ds8', 'E8', 'F8', 'Fs8', 'G8', 'Gs8', 'A8', 'As8', 'B8',
    'C9', 'Cs9', 'D9', 'Ds9', 'E9', 'F9', 'Fs9', 'G9', 'Gs9', 'A9', 'As9', 'B9',
    'C10','Cs10','D10','Ds10','E10','F10','Fs10','G10'
];

// INSTRUMENT_LIST mapping
MidiToMusicXML.INSTRUMENT_LIST = [
    ['Acoustic Grand Piano',              'Pno.',        'keyboard.piano.grand'],
    ['Bright Acoustic Piano',             'B. Pno.',     'keyboard.piano.upright'],
    ['Electric Grand Piano',              'E. G. Pno.',  'keyboard.piano.electric'],
    ['Honky Tonk Piano',                  'H. T. Pno.',  'keyboard.piano.honky-tonk'],
    ['Electric Piano 1 (Rhodes Piano)',   'E. Pno1.',    'keyboard.piano.electric'],
    ['Electric Piano 2 (Chorused Piano)', 'E. Pno2.',    'keyboard.piano.electric'],
    ['Harpsichord',                       'Hpsch.',      'keyboard.harpsichord'],
    ['Clavinet',                          'Clav.',       'keyboard.clavichord'],
    ['Celesta',                           'Cel.',        'keyboard.celesta'],
    ['Glockenspiel',                      'Glock.',      'pitched-percussion.glockenspiel'],
    ['Music Box',                         'M. Box',      'pitched-percussion.music-box'],
    ['Vibraphone',                        'Vib.',        'pitched-percussion.vibraphone'],
    ['Marimba',                           'Mar.',        'pitched-percussion.marimba'],
    ['Xylophone',                         'Xyl.',        'pitched-percussion.xylophone'],
    ['Tubular Bell',                      'Tub. Bell',   'pitched-percussion.tubular-bells'],
    ['Dulcimer (Santur)',                 'Dulc.',       'pluck.dulcimer'],
    ['Drawbar Organ (Hammond)',           'Drwb. Org.',  'keyboard.organ.drawbar'],
    ['Percussive Organ',                  'Perc. Org.',  'keyboard.organ.percussive'],
    ['Rock Organ',                        'R. Org.',     'keyboard.organ.rotary'],
    ['Church Organ',                      'Ch. Org.',    'keyboard.organ.pipe'],
    ['Reed Organ',                        'Rd. Org.',    'keyboard.organ.pipe'], // Reed organ mapped to pipe
    ['Accordion (French)',                'Acc.',        'keyboard.accordion'],
    ['Harmonica',                         'Harm.',       'wind.reed.harmonica'],
    ['Tango Accordion (Bandoneon)',       'Band.',       'keyboard.bandoneon'],
    ['Acoustic Guitar (nylon)',           'A. N. Guit.', 'pluck.guitar.nylon-string'],
    ['Acoustic Guitar (steel)',           'A. S. Guit.', 'pluck.guitar.steel-string'],
    ['Electric Guitar (jazz)',            'J. El. Guit.','pluck.guitar.electric'],
    ['Electric Guitar (clean)',           'El. Guit.',   'pluck.guitar.electric'],
    ['Electric Guitar (muted)',           'M. El. Guit.','pluck.guitar.electric'],
    ['Overdriven Guitar',                 'Ovr. Guit.',  'pluck.guitar.electric'],
    ['Distortion Guitar',                 'Dist. Guit.', 'pluck.guitar.electric'],
    ['Guitar Harmonics',                  'Guit. Harm.', 'pluck.guitar'],
    ['Acoustic Bass',                     'A. Bs.',      'pluck.bass.acoustic'],
    ['Electric Bass (fingered)',          'El. Bs.',     'pluck.bass.electric'],
    ['Electric Bass (picked)',            'B. Guit.',    'pluck.bass.electric'],
    ['Fretless Bass',                     'Frtl. Bs.',   'pluck.bass.fretless'],
    ['Slap Bass 1',                       'Slp. Bs1.',   'pluck.bass.electric'],
    ['Slap Bass 2',                       'Slp. Bs2.',   'pluck.bass.electric'],
    ['Syn Bass 1',                        'Syn. Bs1.',   'pluck.bass.synth'],
    ['Syn Bass 2',                        'Syn. Bs2.',   'pluck.bass.synth.lead'],
    ['Violin',                            'Vln.',        'strings.violin'],
    ['Viola',                             'Vla.',        'strings.viola'],
    ['Cello',                             'Vc.',         'strings.cello'],
    ['Contrabass',                        'Cb.',         'strings.contrabass'],
    ['Tremolo Strings',                   'Tr. Str.',    'strings.group'],
    ['Pizzicato Strings',                 'Pizz. Str.',  'strings.group'],
    ['Harp',                              'Hrp.',        'pluck.harp'],
    ['Timpani',                           'Timp.',       'drum.timpani'],
    ['Violins (section)',                 'Vlns.',       'strings.group'],
    ['Strings',                           'Str.',        'strings.group'],
    ['Synth Strings 1',                   'Syn. Str1.',  'strings.group.synth'],
    ['Synth Strings 2',                   'Syn. Str2.',  'strings.group.synth'],
    ['Choir Aahs',                        'Ch. Aah.',    'voice.vocals'],
    ['Boy Soprano',                       'B. S.',       'voice.child'],
    ['Syn Choir',                         'Syn. Ch.',    'voice.synth'],
    ['Brass Synthesizer',                 'Synth.',      'brass.group.synth'],
    ['Trumpet',                           'Tpt.',        'brass.trumpet'],
    ['Trombone',                          'Tbn.',        'brass.trombone'],
    ['Tuba',                              'Tba.',        'brass.tuba'],
    ['Muted Trumpet',                     'M. Tpt.',     'brass.trumpet'],
    ['French Horn',                       'Fr. Hn.',     'brass.french-horn'],
    ['Brass Ensemble',                    'Brs. Ens.',   'brass.group'],
    ['Syn Brass 1',                       'Syn. Brs1.',  'brass.group.synth'],
    ['Syn Brass 2',                       'Syn. Brs2.',  'brass.group.synth'],
    ['Soprano Sax',                       'Sop. Sax.',   'wind.reed.saxophone.soprano'],
    ['Alto Sax',                          'Alt. Sax.',   'wind.reed.saxophone.alto'],
    ['Tenor Sax',                         'Ten. Sax.',   'wind.reed.saxophone.tenor'],
    ['Baritone Sax',                      'Bar. Sax.',   'wind.reed.saxophone.baritone'],
    ['Oboe',                              'Ob.',         'wind.reed.oboe'],
    ['English Horn',                      'Eng. Hn.',    'wind.reed.english-horn'],
    ['Bassoon',                           'Bsn.',        'wind.reed.bassoon'],
    ['Clarinet',                          'Cl.',         'wind.reed.clarinet'],
    ['Piccolo',                           'Picc.',       'wind.flutes.flute.piccolo'],
    ['Flute',                             'Fl.',         'wind.flutes.flute'],
    ['Recorder',                          'Rec.',        'wind.flutes.recorder'],
    ['Pan Flute',                         'Pan Fl.',     'wind.flutes.panpipes'],
    ['Bottle Blow',                       'Btl. Blw.',   'wind.flutes.blown-bottle'],
    ['Shakuhachi',                        'Shak.',       'wind.flutes.shakuhachi'],
    ['Whistle',                           'Whis.',       'wind.flutes.whistle'],
    ['Ocarina',                           'Ocar.',       'wind.flutes.ocarina'],
    ['Syn Square Wave',                   'Sq. Wv.',     'synth.tone.square'],
    ['Syn Saw Wave',                      'Saw Wv.',     'synth.tone.sawtooth'],
    ['Syn Calliope',                      'Call.',       'wind.flutes.calliope'],
    ['Syn Chiffer',                       'Chiff.',      'synth.chiff'],
    ['Syn Charang',                       'Char.',       'synth.charang'],
    ['Syn Voice Solo',                    'V. Solo',     'voice.synth'],
    ['Syn Fifths Saw',                    '5th Saw',     'synth.group.fifths'],
    ['Syn Brass and Lead',                'Brs/Lead',    'synth.group'],
    ['Pad Fantasia',                      'Fant.',       'synth.pad'],
    ['Pad Warm Pad',                      'Warm.',       'synth.pad.warm'],
    ['Pad Polysynth',                     'Poly.',       'synth.pad.polysynth'],
    ['Pad Space Vox',                     'Spc. Vox',    'synth.pad.choir'],
    ['Pad Bowed Glass',                   'Bwd. Gl.',    'synth.pad.bowed'],
    ['Pad Metal',                         'Met.',        'synth.pad.metallic'],
    ['Pad Halo',                          'Halo',        'synth.pad.halo'],
    ['Pad Sweep',                         'Swp.',        'synth.pad.sweep'],
    ['Ice Rain',                          'Ice R.',      'synth.effects.rain'],
    ['Soundtrack',                        'Sndtrk.',     'synth.effects.soundtrack'],
    ['Crystal',                           'Cryst.',      'synth.effects.crystal'],
    ['Atmosphere',                        'Atm.',        'synth.effects.atmosphere'],
    ['Brightness',                        'Brght.',      'synth.effects.brightness'],
    ['Goblins',                           'Gob.',        'synth.effects.goblins'],
    ['Echo Drops',                        'Echo.',       'synth.effects.echoes'],
    ['Sci Fi',                            'SciFi',       'synth.effects.sci-fi'],
    ['Sitar',                             'Sit.',        'pluck.sitar'],
    ['Banjo',                             'Bnj.',        'pluck.banjo'],
    ['Shamisen',                          'Sham.',       'pluck.shamisen'],
    ['Koto',                              'Koto',        'pluck.koto'],
    ['Kalimba',                           'Kal.',        'pitched-percussion.kalimba'],
    ['Bag Pipe',                          'Bagp.',       'wind.pipes.bagpipes'],
    ['Fiddle',                            'Fid.',        'strings.fiddle'],
    ['Shanai',                            'Shan.',       'wind.reed.shenai'],
    ['Tinkle Bell',                       'Tnk. Bell',   'metal.bells.tinklebell'],
    ['Agogo',                             'Agog.',       'metal.bells.agogo'],
    ['Steel Drums',                       'St. Drm.',    'metal.steel-drums'],
    ['Woodblock',                         'Wblk.',       'wood.wood-block'],
    ['Taiko Drum',                        'Taiko',       'drum.taiko'],
    ['Melodic Tom',                       'Mel. Tom',    'drum.tom-tom'],
    ['Synth Drum',                        'Syn. Drm.',   'drum.tom-tom.synth'],
    ['Reverse Cymbal',                    'Rev. Cym.',   'metal.cymbal.reverse'],
    ['Guitar Fret Noise',                 'Fret N.',     'effect.guitar-fret'],
    ['Breath Noise',                      'Brth. N.',    'effect.breath'],
    ['Seashore',                          'Seash.',      'effect.seashore'],
    ['Bird',                              'Bird',        'effect.bird.tweet'],
    ['Telephone',                         'Tel.',        'effect.telephone-ring'],
    ['Helicopter',                        'Heli.',       'effect.helicopter'],
    ['Applause',                          'Appl.',       'effect.applause'],
    ['Gunshot',                           'Gunsh.',      'effect.gunshot']
];

// DRUM_SET percussion mapping
MidiToMusicXML.DRUM_SET = {
    25: ['Automobile Brake Drums', 'Aut. Brk. Dr.', 'metal.brake-drums'],
    35: ['Acoustic Bass Drum',     'Ac. B. Dr.',    'drum.bass-drum'],
    36: ['Bass Drum 1',            'B. Dr1.',       'drum.bass-drum'],
    37: ['Side Stick',             'Sd. St.',       'drum.side-stick'],
    38: ['Acoustic Snare',         'Ac. Sn.',       'drum.snare-drum'],
    39: ['Hand Clap',              'H. Clap',       'effect.hand-clap'],
    40: ['Electric Snare',         'El. Sn.',       'drum.snare-drum.electric'],
    41: ['Low Floor Tom',          'L. Fl. Tom',    'drum.tom-tom'],
    42: ['Closed Hi-Hat',          'Cl. Hh.',       'metal.hi-hat'],
    43: ['High Floor Tom',         'H. Fl. Tom',    'drum.tom-tom'],
    44: ['Pedal Hi-Hat',           'Pd. Hh.',       'metal.hi-hat'],
    45: ['Low Tom',                'L. Tom',        'drum.tom-tom'],
    46: ['Open Hi-Hat',            'Op. Hh.',       'metal.hi-hat'],
    47: ['Low Mid Tom',            'L. M. Tom',     'drum.tom-tom'],
    48: ['High Mid Tom',           'H. M. Tom',     'drum.tom-tom'],
    49: ['Crash Cymbal 1',         'Cr. Cym1.',     'metal.cymbal.crash'],
    50: ['High Tom',               'H. Tom',        'drum.tom-tom'],
    51: ['Ride Cymbal 1',          'Rd. Cym1.',     'metal.cymbal.ride'],
    52: ['Chinese Cymbal',         'Ch. Cym.',      'metal.cymbal.chinese'],
    53: ['Ride Bell',              'Rd. Bell',      'metal.bells.cowbell'],
    54: ['Tambourine',             'Tamb.',         'drum.tambourine'],
    55: ['Splash Cymbal',          'Spl. Cym.',     'metal.cymbal.splash'],
    56: ['Cowbell',                'Cowb.',         'metal.bells.cowbell'],
    57: ['Crash Cymbal 2',         'Cr. Cym2.',     'metal.cymbal.crash'],
    58: ['Vibraslap',              'Vibr.',         'rattle.vibraslap'],
    59: ['Ride Cymbal 2',          'Rd. Cym2.',     'metal.cymbal.ride'],
    60: ['High Bongo',             'H. Bongo',      'drum.bongo'],
    61: ['Low Bongo',              'L. Bongo',      'drum.bongo'],
    62: ['Mute High Conga',        'M. H. Cga.',    'drum.conga'],
    63: ['Open High Conga',        'Op. H. Cga.',   'drum.conga'],
    64: ['Low Conga',              'L. Cga.',       'drum.conga'],
    65: ['High Timbale',           'H. Timb.',      'drum.timbale'],
    66: ['Low Timbale',            'L. Timb.',      'drum.timbale'],
    67: ['High Agogo',             'H. Agog.',      'metal.bells.agogo'],
    68: ['Low Agogo',              'L. Agog.',      'metal.bells.agogo'],
    69: ['Cabasa',                 'Cab.',          'rattle.cabasa'],
    70: ['Maracas',                'Marac.',        'rattle.maraca'],
    71: ['Short Whistle',          'Sh. Whis.',     'effect.whistle'],
    72: ['Long Whistle',           'Lg. Whis.',     'effect.whistle'],
    73: ['Short Guiro',            'Sh. Gui.',      'wood.guiro'],
    74: ['Long Guiro',             'Lg. Gui.',      'wood.guiro'],
    75: ['Claves',                 'Clav.',         'wood.claves'],
    76: ['High Wood Block',        'H. Wblk.',      'wood.wood-block'],
    77: ['Low Wood Block',         'L. Wblk.',      'wood.wood-block'],
    78: ['Mute Cuica',             'M. Cuic.',      'drum.cuica'],
    79: ['Open Cuica',             'Op. Cuic.',     'drum.cuica'],
    80: ['Mute Triangle',          'M. Tri.',       'metal.triangle'],
    81: ['Open Triangle',          'Op. Tri.',      'metal.triangle']
};

// Export for Node.js and Browser
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MidiToMusicXML;
} else {
    window.MidiToMusicXML = MidiToMusicXML;
}

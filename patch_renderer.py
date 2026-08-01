import sys

with open('D:/MagicServer/www/MusicXML/example/custom-renderer.js', 'r', encoding='utf-8') as f:
    content = f.read()

old_logic = '''                let lastBaseDiv = 0; // For chord alignment
                let currentStaffDivisions = state.divisions;

                measureNode.querySelectorAll("note, backup, forward").forEach(child => {
                    if (child.tagName === "backup") {
                        const d = parseInt(child.querySelector("duration")?.textContent || "0");
                        currentDiv = Math.max(0, currentDiv - d);
                        return;
                    }
                    if (child.tagName === "forward") {
                        const d = parseInt(child.querySelector("duration")?.textContent || "0");
                        currentDiv += d;
                        return;
                    }

                    const isChord = child.querySelector("chord") !== null;
                    const isRest = child.querySelector("rest") !== null;
                    const originalNoteNode = child;

                    let originalDuration = parseInt(child.querySelector("duration")?.textContent || "0");
                    let pieces = [originalDuration];

                    // Automatically split notes that are not representable by standard symbols
                    if (!isRest && originalDuration > 0 &&
                        !MusicXMLSvgRenderer.isStandardDuration(originalDuration, currentStaffDivisions)) {
                        pieces = MusicXMLSvgRenderer.splitDurationIntoRepresentablePieces(originalDuration, currentStaffDivisions);
                    }

                    let pieceCurrentDiv = isChord ? lastBaseDiv : currentDiv;

                    pieces.forEach((pieceDuration, pIdx) => {
                        const isFirstPiece = (pIdx === 0);
                        const isLastPiece = (pIdx === pieces.length - 1);

                        // Determine original tie types from the XML node
                        let lyricText = null;
                        if (isFirstPiece) {
                            lyricText = originalNoteNode.querySelector("lyric text")?.textContent;
                        }

                        let originalTieStart = false;
                        let originalTieStop = false;
                        originalNoteNode.querySelectorAll("tie, tied").forEach(t => {
                            const type = t.getAttribute("type");
                            if (type === "start") originalTieStart = true;
                            if (type === "stop") originalTieStop = true;
                        });

                        // Calculate tie types for each piece
                        let pieceTieStart = false;
                        let pieceTieStop = false;

                        if (pieces.length === 1) {
                            pieceTieStart = originalTieStart;
                            pieceTieStop = originalTieStop;
                        } else {
                            if (isFirstPiece) {
                                pieceTieStop = true;
                                pieceTieStart = originalTieStart;
                            } else if (isLastPiece) {
                                pieceTieStart = true;
                                pieceTieStop = originalTieStop;
                            } else {
                                pieceTieStart = true;
                                pieceTieStop = true;
                            }
                        }

                        for (let s = 1; s <= staffs; s++) {
                            const noteData = {
                                node: originalNoteNode, // Keep reference for other attributes
                                isRest: isRest,
                                staff: s,
                                step: originalNoteNode.querySelector("pitch step, unpitched display-step")?.textContent || "C",
                                octave: parseInt(originalNoteNode.querySelector("pitch octave, unpitched display-octave")?.textContent || "4") || 4,
                                alter: parseInt(originalNoteNode.querySelector("pitch alter")?.textContent || "0") || 0,
                                accidental: originalNoteNode.querySelector("accidental")?.textContent,
                                type: MusicXMLSvgRenderer.getNoteType(pieceDuration, currentStaffDivisions),
                                stem: originalNoteNode.querySelector("stem")?.textContent,
                                lyric: isFirstPiece ? lyricText : null, // Only first piece gets the lyric
                                onsetDiv: pieceCurrentDiv,
                                duration: pieceDuration,
                                articulations: {
                                    staccato: originalNoteNode.querySelector("articulations staccato") !== null,
                                    accent: originalNoteNode.querySelector("articulations accent") !== null,
                                    tenuto: originalNoteNode.querySelector("articulations tenuto") !== null,
                                    fermata: originalNoteNode.querySelector("fermata") !== null
                                },
                                tieStart: pieceTieStart,
                                tieStop: pieceTieStop,
                                divisions: currentStaffDivisions
                            }
                            allNotesInMeasure.push(noteData);

                            if (!isChord) {
                                pieceCurrentDiv += pieceDuration;
                            }
                        }
                    });

                    if (!isChord) {
                        currentDiv = pieceCurrentDiv; // Update main cursor for the next original note
                    }
                    lastBaseDiv = currentDiv; // For chord alignment
                });'''

new_logic = '''                let lastNoteOnset = 0; // For chord alignment
                let currentStaffDivisions = state.divisions;

                measureNode.querySelectorAll("note, backup, forward").forEach(child => {
                    if (child.tagName === "backup") {
                        const d = parseInt(child.querySelector("duration")?.textContent || "0");
                        currentDiv = Math.max(0, currentDiv - d);
                        return;
                    }
                    if (child.tagName === "forward") {
                        const d = parseInt(child.querySelector("duration")?.textContent || "0");
                        currentDiv += d;
                        return;
                    }

                    const isChord = child.querySelector("chord") !== null;
                    const isRest = child.querySelector("rest") !== null;
                    const originalNoteNode = child;

                    let originalDuration = parseInt(child.querySelector("duration")?.textContent || "0");
                    let pieces = [originalDuration];

                    // Automatically split notes that are not representable by standard symbols
                    if (!isRest && originalDuration > 0 &&
                        !MusicXMLSvgRenderer.isStandardDuration(originalDuration, currentStaffDivisions)) {
                        pieces = MusicXMLSvgRenderer.splitDurationIntoRepresentablePieces(originalDuration, currentStaffDivisions);
                    }

                    if (!isChord) {
                        lastNoteOnset = currentDiv;
                    }
                    let pieceCurrentDiv = lastNoteOnset;

                    pieces.forEach((pieceDuration, pIdx) => {
                        const isFirstPiece = (pIdx === 0);
                        const isLastPiece = (pIdx === pieces.length - 1);

                        // Determine original tie types from the XML node
                        let lyricText = null;
                        if (isFirstPiece) {
                            lyricText = originalNoteNode.querySelector("lyric text")?.textContent;
                        }

                        let originalTieStart = false;
                        let originalTieStop = false;
                        originalNoteNode.querySelectorAll("tie, tied").forEach(t => {
                            const type = t.getAttribute("type");
                            if (type === "start") originalTieStart = true;
                            if (type === "stop") originalTieStop = true;
                        });

                        // Calculate tie types for each piece
                        let pieceTieStart = false;
                        let pieceTieStop = false;

                        if (pieces.length === 1) {
                            pieceTieStart = originalTieStart;
                            pieceTieStop = originalTieStop;
                        } else {
                            if (isFirstPiece) {
                                pieceTieStop = true;
                                pieceTieStart = originalTieStart;
                            } else if (isLastPiece) {
                                pieceTieStart = true;
                                pieceTieStop = originalTieStop;
                            } else {
                                pieceTieStart = true;
                                pieceTieStop = true;
                            }
                        }

                        // Parse step, octave, alter outside staff loop to avoid redundancy
                        const step = originalNoteNode.querySelector("pitch step, unpitched display-step")?.textContent || "C";
                        const octave = parseInt(originalNoteNode.querySelector("pitch octave, unpitched display-octave")?.textContent || "4") || 4;
                        const alter = parseInt(originalNoteNode.querySelector("pitch alter")?.textContent || "0") || 0;

                        for (let s = 1; s <= staffs; s++) {
                            const noteData = {
                                node: originalNoteNode, // Keep reference for other attributes
                                isRest: isRest,
                                staff: s,
                                step: step,
                                octave: octave,
                                alter: alter,
                                accidental: originalNoteNode.querySelector("accidental")?.textContent,
                                type: MusicXMLSvgRenderer.getNoteType(pieceDuration, currentStaffDivisions),
                                stem: originalNoteNode.querySelector("stem")?.textContent,
                                lyric: isFirstPiece ? lyricText : null, // Only first piece gets the lyric
                                onsetDiv: pieceCurrentDiv,
                                duration: pieceDuration,
                                articulations: {
                                    staccato: originalNoteNode.querySelector("articulations staccato") !== null,
                                    accent: originalNoteNode.querySelector("articulations accent") !== null,
                                    tenuto: originalNoteNode.querySelector("articulations tenuto") !== null,
                                    fermata: originalNoteNode.querySelector("fermata") !== null
                                },
                                tieStart: pieceTieStart,
                                tieStop: pieceTieStop,
                                divisions: currentStaffDivisions
                            }
                            allNotesInMeasure.push(noteData);
                        }
                        
                        // Advance pieceCurrentDiv for the next piece of this SAME note
                        pieceCurrentDiv += pieceDuration;
                    });

                    // Update main cursor
                    if (!isChord) {
                        currentDiv = pieceCurrentDiv; 
                    } else {
                        currentDiv = Math.max(currentDiv, pieceCurrentDiv);
                    }
                });'''

content = content.replace(old_logic, new_logic)

with open('D:/MagicServer/www/MusicXML/example/custom-renderer.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("Patched custom-renderer.js logic")

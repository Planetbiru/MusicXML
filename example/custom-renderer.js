/**
 * Planetbiru Custom MusicXML SVG Renderer (OSMD-Style Engraver)
 * 
 * A high-precision, elegant vector sheet music renderer that matches the visual
 * standards, layout mechanics, and typography of OpenSheetMusicDisplay (OSMD).
 * 
 * Features:
 * - Multi-part & multi-staff full score rendering (solo, piano, orchestra)
 * - Multi-system responsive measure layout & continuous staff lines
 * - Publication-quality monochrome engraver styling by default (OSMD style)
 * - Optional Pitch Color-Coding toggle mode for music education
 * - Piano Grand Staff curly brace and multi-staff vertical system barlines
 * - Precision vector typography for Clefs (Treble/Bass/Alto), Key Signatures & Time Signatures
 * - Smart stem orientation, sloped multi-note beams (8th/16th/32nd notes) with exact stem endpoint alignment
 * - Vector accidentals (Sharp ♯, Flat ♭, Natural ♮, Double Sharp 𝄪, Double Flat 𝄠)
 * - Vector rests (Whole, Half, Quarter, 8th, 16th, 32nd)
 * - System boundary tie & slur curve handling (no diagonal page crossings)
 * - Articulations (Staccato dots, Accents, Tenuto, Fermatas)
 * - Measure numbers, Tempo markings, Lyrics, and Document Header formatting
 */
class MusicXMLSvgRenderer {
    constructor(containerId) {
        this.container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        this.svg = null;
        this.zoom = 1.0;
        
        // Render Mode: false = OSMD Classic Engraver Monochrome, true = Color-Coded Learning
        this.colorCoded = false;
        
        // Base Layout Metrics
        this.baseLineSpacing = 9.5;
        this.baseStaffSpacing = 90; // gap between staves
        this.basePartSpacing = 65; // additional gap between different parts within a system
        this.systemSpacing = 80; // Configurable gap between system rows (in pixels)
        this.baseRowSpacingSingle = 150;
        this.baseRowSpacingDouble = 220;
        this.measuresPerLine = 3;
        this.liricYOffset = 80;
        
        // Engraving Color Palette
        this.engraverColor = "#0f172a"; // Solid dark engraver ink
        this.staffLineColor = "#475569"; // Crisp staff line
        this.lightLineColor = "#cbd5e1"; // Measure divider
        this.paperBg = "#ffffff";
        
        // Educational Pitch Color Palette
        this.pitchColors = {
            'C': '#ef4444', // Red
            'D': '#f97316', // Orange
            'E': '#f59e0b', // Amber/Yellow
            'F': '#10b981', // Green
            'G': '#3b82f6', // Blue
            'A': '#6366f1', // Indigo
            'B': '#a855f7'  // Purple
        };
        
        this.stepOffsets = { 'C': 0, 'D': 1, 'E': 2, 'F': 3, 'G': 4, 'A': 5, 'B': 6 };
    }

    /**
     * Clear container and render MusicXML string
     * @param {string} xmlText 
     */
    render(xmlText) {
        if (!this.container) return;
        this.container.innerHTML = "";
        
        if (!xmlText || typeof xmlText !== 'string') {
            this.container.innerHTML = "<div style='color:#ef4444; padding:2rem; text-align:center;'>No MusicXML data provided.</div>";
            return;
        }

        const scale = this.zoom || 1.0;
        this.lineSpacing = this.baseLineSpacing * scale;
        this.staffSpacing = this.baseStaffSpacing * scale;
        
        // Parse MusicXML
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlText, "application/xml");
        
        const parserError = xmlDoc.querySelector("parsererror");
        if (parserError) {
            throw new Error("Invalid MusicXML structure: " + parserError.textContent);
        }

        // SVG Canvas dimensions
        const containerWidth = Math.max(this.container.clientWidth || 0, 850);
        this.svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        // FIX: Remove explicit width and height. Let viewBox control the aspect ratio and scaling.
        this.svg.style.backgroundColor = this.paperBg; // The container should control the width (e.g., via CSS `width: 100%`).
        this.svg.style.borderRadius = "12px";
        this.svg.style.boxShadow = "0 10px 30px rgba(0, 0, 0, 0.15)";
        this.svg.style.display = "block";
        this.svg.style.margin = "0 auto";
        this.container.appendChild(this.svg);

        // Find parts & measures across all parts
        const parts = Array.from(xmlDoc.querySelectorAll("part"));
        if (parts.length === 0) {
            this.container.innerHTML = "<div style='color:#ef4444; padding:2rem; text-align:center;'>No musical parts found in MusicXML.</div>";
            return;
        }

        const partMeasures = parts.map(p => Array.from(p.querySelectorAll("measure")));
        const maxMeasures = Math.max(...partMeasures.map(mList => mList.length));
        if (maxMeasures === 0) {
            this.container.innerHTML = "<div style='color:#ef4444; padding:2rem; text-align:center;'>No measures found in MusicXML.</div>";
            return;
        }

        // Map staves per part
        const partStaffMap = [];
        let totalSystemStaves = 0;

        parts.forEach((partNode, pIdx) => {
            const firstM = partMeasures[pIdx][0];
            let numStaves = 1;
            if (firstM) {
                const stavesNode = firstM.querySelector("attributes staves");
                if (stavesNode) {
                    numStaves = parseInt(stavesNode.textContent) || 1;
                } else {
                    const hasStaff2 = firstM.querySelector("note staff")?.textContent === "2";
                    if (hasStaff2) numStaves = 2;
                }
            }
            partStaffMap.push({
                partIndex: pIdx,
                partNode: partNode,
                numStaves: numStaves,
                startStaffId: totalSystemStaves + 1
            });
            totalSystemStaves += numStaves;
        });

        // Dynamic Line Wrapping (2 measures per line if lyrics present, else 3)
        const hasLyrics = xmlDoc.querySelector("lyric") !== null;
        this.measuresPerLine = hasLyrics ? 2 : 3;
        
        // FIX: Correctly calculate the total height of all staves in a system.
        // This calculation now accounts for additional spacing between different parts.
        let calculatedStaffSystemHeight = 0;
        const staffHeight = 4 * this.lineSpacing; // Height of one 5-line staff (4 spaces between 5 lines)

        for (let i = 0; i < partStaffMap.length; i++) {
            const pInfo = partStaffMap[i];
            calculatedStaffSystemHeight += pInfo.numStaves * staffHeight; // Height of staff lines for this part
            calculatedStaffSystemHeight += Math.max(0, pInfo.numStaves - 1) * this.staffSpacing; // Spacing *within* this part's staves

            if (i < partStaffMap.length - 1) { // If not the last part, add extra spacing between parts
                calculatedStaffSystemHeight += this.basePartSpacing * scale;
            }
        }
        // Ensure a minimum height if no staves are rendered (e.g., empty score)
        if (totalSystemStaves === 0) calculatedStaffSystemHeight = staffHeight;

        const systemGap = (this.systemSpacing ?? 80) * scale;
        this.rowSpacing = calculatedStaffSystemHeight + (hasLyrics ? 90 : 70) * scale + systemGap; // Add extra padding for lyrics/general system spacing

        // Metadata Header Details
        const songTitle = xmlDoc.querySelector("work-title")?.textContent || 
                          xmlDoc.querySelector("movement-title")?.textContent || 
                          xmlDoc.querySelector("credit-words")?.textContent || 
                          "Untitled Score";
        const composer = xmlDoc.querySelector("creator[type='composer']")?.textContent || 
                         xmlDoc.querySelector("creator")?.textContent || 
                         "";
        const initialY = 30 * scale; // Define the starting Y position for all content
        const subtitle = xmlDoc.querySelector("movement-title")?.textContent || "";
        const partName = parts.length === 1 
            ? (xmlDoc.querySelector("part-name")?.textContent || (totalSystemStaves === 2 ? "Piano" : "Score"))
            : "Full Score";

        // Header Title Block
        let currentY = initialY; // Use the defined starting Y
        
        // Title
        // Draw title at the initial currentY position
        this.drawText(containerWidth / 2, currentY, songTitle, `${Math.round(22 * scale)}px`, this.engraverColor, "middle", true, "'Outfit', 'Times New Roman', serif");
        
        // Subtitle
        if (subtitle && subtitle !== songTitle) {
            currentY += 18 * scale;
            this.drawText(containerWidth / 2, currentY, subtitle, `${Math.round(12 * scale)}px`, "#64748b", "middle", false);
        }
        
        // Composer
        if (composer) {
            this.drawText(containerWidth - 40 * scale, currentY + 15 * scale, composer, `${Math.round(11 * scale)}px`, this.engraverColor, "end", false, "'Inter', sans-serif");
        }
        
        // Part Name / Instrument
        currentY += 22 * scale;
        this.drawText(50 * scale, currentY, partName, `${Math.round(12 * scale)}px`, "#475569", "start", true, "'Inter', sans-serif");
        
        // System Layout Metrics
        currentY += 25 * scale; // Add space before the first staff system
        const leftMargin = 85 * scale;
        const rightMargin = 40 * scale;
        const systemStartX = leftMargin - 60 * scale;
        const usableWidth = containerWidth - leftMargin - rightMargin;
        const measureWidth = Math.max(220 * scale, usableWidth / this.measuresPerLine);
        
        let currentX = leftMargin;
        let nextSystemY = currentY; // FIX: Use a separate variable to track the Y of the next system

        // Track clefs, key, and time signatures for each staff ID (1..totalSystemStaves)
        const staffState = {};
        for (let s = 1; s <= totalSystemStaves; s++) {
            staffState[s] = { clef: "G", fifths: 0, beats: 4, beatType: 4, timeSymbol: null, divisions: 4 };
        }
        
        const activeTies = {};
        
        // Measure Iteration across maxMeasures
        for (let measureIdx = 0; measureIdx < maxMeasures; measureIdx++) {
            const isSystemStart = (measureIdx % this.measuresPerLine === 0);
            // Declare offset variables here to be accessible throughout the measure loop
            let currentStaffYOffset = 0;
            let previousPartIndex = -1;
            
            // Advance to next system row if row is full
            if (measureIdx > 0 && isSystemStart) {
                currentX = leftMargin; // Reset X position for the new system
                currentY = nextSystemY; // FIX: Set currentY to the pre-calculated start of the new system
                nextSystemY += this.rowSpacing; // FIX: Calculate the Y for the *next* system row
            }

            // Update metadata attributes across all parts for this measure
            partStaffMap.forEach(pInfo => {
                const mNode = partMeasures[pInfo.partIndex][measureIdx];
                if (!mNode) return;
                
                const attrNode = mNode.querySelector("attributes");
                if (attrNode) {
                    const divisionsNode = attrNode.querySelector("divisions");
                    const divVal = divisionsNode ? (parseInt(divisionsNode.textContent) || 4) : 4;
                    
                    const fifthsNode = attrNode.querySelector("key fifths");
                    const fifthsVal = fifthsNode ? (parseInt(fifthsNode.textContent) || 0) : 0;

                    const timeNode = attrNode.querySelector("time");
                    let beatsVal = 4, beatTypeVal = 4, symbolVal = null;
                    if (timeNode) {
                        symbolVal = timeNode.getAttribute("symbol");
                        beatsVal = parseInt(timeNode.querySelector("beats")?.textContent || "4") || 4;
                        beatTypeVal = parseInt(timeNode.querySelector("beat-type")?.textContent || "4") || 4;
                    }

                    attrNode.querySelectorAll("clef").forEach(clefNode => {
                        const clefNum = parseInt(clefNode.getAttribute("number") || "1") || 1;
                        const sign = clefNode.querySelector("sign")?.textContent;
                        const staffId = pInfo.startStaffId + (clefNum - 1);
                        if (staffState[staffId] && sign) {
                            staffState[staffId].clef = sign;
                        }
                    });

                    for (let s = 0; s < pInfo.numStaves; s++) {
                        const staffId = pInfo.startStaffId + s;
                        if (staffState[staffId]) {
                            staffState[staffId].divisions = divVal;
                            staffState[staffId].fifths = fifthsVal;
                            if (timeNode) {
                                staffState[staffId].beats = beatsVal;
                                staffState[staffId].beatType = beatTypeVal;
                                staffState[staffId].timeSymbol = symbolVal;
                            }
                        }
                    }
                }
            });

            // At System Start: Draw Continuous System Staff Lines & Connectors
            if (isSystemStart) {
                const remainingMeasures = maxMeasures - measureIdx;
                const systemMeasuresInRow = Math.min(this.measuresPerLine, remainingMeasures);
                const systemRowWidth = systemMeasuresInRow * measureWidth + 60 * scale;

                // Draw continuous 5 staff lines for each active staff in full system
                for (let s = 1; s <= totalSystemStaves; s++) {
                    const pInfo = partStaffMap.find(p => s >= p.startStaffId && s < p.startStaffId + p.numStaves);
                    if (!pInfo) continue; // Should not happen

                    const localStaff = (s - pInfo.startStaffId) + 1; // 1-based local staff index within its part
                    
                    // Add extra spacing when moving to a new part
                    if (pInfo.partIndex !== previousPartIndex && previousPartIndex !== -1) {
                        currentStaffYOffset += this.basePartSpacing * scale; // Add part spacing for new part
                    }
                    const sY = currentY + currentStaffYOffset; // Top line of the current staff

                    this.drawStaffLines(systemStartX, sY, systemRowWidth);

                    // Draw Clef, Key, Time Signature at system start for this staff
                    const state = staffState[s];
                    this.drawClef(state.clef, systemStartX + 6 * scale, sY);
                    this.drawKeySignature(systemStartX + 26 * scale, sY, state.fifths, state.clef);
                    this.drawTimeSignature(systemStartX + 46 * scale, sY, state.beats, state.beatType, state.timeSymbol);

                    // Update offset for the next staff
                    currentStaffYOffset += (4 * this.lineSpacing); // Height of the staff lines
                    if (localStaff < pInfo.numStaves) { // If there are more staves in this part, add internal staff spacing
                        currentStaffYOffset += this.staffSpacing;
                    }
                    previousPartIndex = pInfo.partIndex;
                }

                // Vertical System Start Bar Line across all staves
                this.drawSystemStartLine(systemStartX, currentY, calculatedStaffSystemHeight);

                // Curly Grand Staff Brace (if 2 staves in Part 0)
                if (totalSystemStaves >= 2 && partStaffMap[0].numStaves === 2) {
                    const firstPartHeight = partStaffMap[0].numStaves * (4 * this.lineSpacing) + Math.max(0, partStaffMap[0].numStaves - 1) * this.staffSpacing;
                    this.drawGrandStaffBrace(systemStartX - 6 * scale, currentY, currentY + firstPartHeight);
                }

                // Measure Number
                this.drawText(systemStartX, currentY - 14 * scale, `${measureIdx + 1}`, `${Math.round(10 * scale)}px`, "#64748b", "start", true, "'Inter', sans-serif");
            }

            // Tempo Markings from primary measure
            const firstM = partMeasures[0][measureIdx];
            if (firstM) {
                let tempoBpm = null;
                const metroNode = firstM.querySelector("metronome per-minute");
                if (metroNode) {
                    tempoBpm = metroNode.textContent;
                } else {
                    const soundNode = firstM.querySelector("sound[tempo]");
                    if (soundNode) tempoBpm = soundNode.getAttribute("tempo");
                }
                if (tempoBpm && !isNaN(parseFloat(tempoBpm))) {
                    const bpmText = `♩ = ${Math.round(parseFloat(tempoBpm))}`;
                    this.drawText(currentX, currentY - 12 * scale, bpmText, `${Math.round(11 * scale)}px`, this.engraverColor, "start", true);
                }
            }

            // Measure Right Barline across all staves
            const isLastMeasureInScore = (measureIdx === maxMeasures - 1);
            this.drawBarLine(currentX + measureWidth, currentY, isLastMeasureInScore, calculatedStaffSystemHeight);

            // Parse & Render Notes across all parts and staves for this measure
            currentStaffYOffset = 0; // Reset offset for the note rendering pass
            previousPartIndex = -1; // Reset part index tracker

            for (let s = 1; s <= totalSystemStaves; s++) {
                const pInfo = partStaffMap.find(p => s >= p.startStaffId && s < p.startStaffId + p.numStaves);
                if (!pInfo) continue;
 
                const localStaff = (s - pInfo.startStaffId) + 1;
                
                // Add extra spacing when moving to a new part
                if (pInfo.partIndex !== previousPartIndex && previousPartIndex !== -1) {
                    currentStaffYOffset += this.basePartSpacing * scale;
                }
                const sY = currentY + currentStaffYOffset;
                const state = staffState[s];
                const mNode = partMeasures[pInfo.partIndex][measureIdx];
                if (!mNode) continue;

                let currentDiv = 0;
                let lastBaseDiv = 0;
                const parsedNotes = [];

                mNode.querySelectorAll("note").forEach(noteNode => {
                    const noteStaff = parseInt(noteNode.querySelector("staff")?.textContent || "1") || 1;
                    if (noteStaff !== localStaff) return;

                    const isChord = noteNode.querySelector("chord") !== null;
                    const isRest = noteNode.querySelector("rest") !== null;
                    const duration = parseInt(noteNode.querySelector("duration")?.textContent || "0") || 0;
                    
                    let lyricText = null;
                    const lyricTxtNode = noteNode.querySelector("lyric text");
                    if (lyricTxtNode) {
                        lyricText = lyricTxtNode.textContent;
                    } else {
                        const rawLyric = noteNode.querySelector("lyric");
                        if (rawLyric) lyricText = rawLyric.textContent.trim();
                    }

                    let onsetDiv = isChord ? lastBaseDiv : currentDiv;
                    if (!isChord) lastBaseDiv = currentDiv;

                    const step = noteNode.querySelector("pitch step")?.textContent || 
                               noteNode.querySelector("unpitched display-step")?.textContent || "C";
                    const octave = parseInt(noteNode.querySelector("pitch octave")?.textContent || 
                                  noteNode.querySelector("unpitched display-octave")?.textContent || "4") || 4;
                    const alter = parseInt(noteNode.querySelector("pitch alter")?.textContent || "0") || 0;
                    const accidental = noteNode.querySelector("accidental")?.textContent;

                    const noteData = {
                        node: noteNode,
                        isRest: isRest,
                        staff: s,
                        step: step,
                        octave: octave,
                        alter: alter,
                        accidental: accidental,
                        type: noteNode.querySelector("type")?.textContent || "quarter",
                        stem: noteNode.querySelector("stem")?.textContent,
                        lyric: lyricText,
                        onsetDiv: onsetDiv,
                        duration: duration,
                        articulations: {
                            staccato: noteNode.querySelector("articulations staccato") !== null,
                            accent: noteNode.querySelector("articulations accent") !== null,
                            tenuto: noteNode.querySelector("articulations tenuto") !== null,
                            fermata: noteNode.querySelector("fermata") !== null
                        }
                    };

                    parsedNotes.push(noteData);
                    if (!isChord) currentDiv += duration;
                });

                const measureDuration = state.beats * state.divisions;
                const totalMeasureDivs = Math.max(measureDuration, currentDiv, 1);

                const columnsByOnset = {};
                parsedNotes.forEach(note => {
                    if (!columnsByOnset[note.onsetDiv]) columnsByOnset[note.onsetDiv] = [];
                    columnsByOnset[note.onsetDiv].push(note);
                });

                const renderedStems = [];
                Object.keys(columnsByOnset).forEach(onsetStr => {
                    const onset = parseInt(onsetStr) || 0;
                    const colNotes = columnsByOnset[onset];
                    const ratio = onset / totalMeasureDivs;
                    const padding = 24 * scale;
                    const xRange = measureWidth - padding - 18 * scale;
                    const colX = currentX + padding + ratio * xRange;

                    const stemData = this.drawNoteColumn(colX, sY, colNotes, state.clef, activeTies);
                    if (stemData && stemData.isBeamable) {
                        renderedStems.push(stemData);
                    }
                });

                // Render beams for this staff
                this.drawBeams(renderedStems);

                // Update offset for the next staff
                // This logic was missing from the note rendering loop, causing overlaps.
                currentStaffYOffset += (4 * this.lineSpacing);
                if (localStaff < pInfo.numStaves) {
                    currentStaffYOffset += this.staffSpacing;
                }
                previousPartIndex = pInfo.partIndex;
            }

            // CRITICAL FIX: Advance currentX to the next measure column!
            currentX += measureWidth;
        }

        // FIX: Correctly calculate viewBox to fit the rendered content without extra top space.
        const topMargin = 20 * scale; // Explicit top margin inside the SVG
        const finalContentBottomY = currentY + calculatedStaffSystemHeight + 40 * scale;
        const viewBoxStartY = initialY - topMargin; // Start viewBox above the first content element
        const totalContentHeight = finalContentBottomY - viewBoxStartY;
 
        // FIX: Set only the viewBox. This makes the SVG intrinsically responsive.
        // The browser will scale it correctly to fit the container's width without distortion.
        this.svg.setAttribute("viewBox", `0 ${viewBoxStartY} ${containerWidth} ${totalContentHeight}`);
    }
    /**
     * Draw 5 Horizontal Staff Lines
     */
    drawStaffLines(x, y, width) {
        for (let i = 0; i < 5; i++) {
            const lineY = y + i * this.lineSpacing;
            const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line.setAttribute("x1", x);
            line.setAttribute("y1", lineY);
            line.setAttribute("x2", x + width);
            line.setAttribute("y2", lineY);
            line.setAttribute("stroke", this.staffLineColor);
            line.setAttribute("stroke-width", `${0.95 * this.zoom}`);
            this.svg.appendChild(line);
        }
    }

    /**
     * Draw System Start Bar Line across active staves
     */
    drawSystemStartLine(x, y, totalSystemHeight) {
        const scale = this.zoom || 1.0;
        const topY = y;
        const bottomY = y + totalSystemHeight;
        
        const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
        line.setAttribute("x1", x);
        line.setAttribute("y1", topY);
        line.setAttribute("x2", x);
        line.setAttribute("y2", bottomY);
        line.setAttribute("stroke", this.engraverColor);
        line.setAttribute("stroke-width", `${1.8 * scale}`);
        this.svg.appendChild(line);
    }

    /**
     * Draw Curly Grand Staff Brace `{` (Piano Accolade)
     */
    drawGrandStaffBrace(x, topY, bottomY) {
        const scale = this.zoom || 1.0;
        const height = bottomY - topY;
        const midY = topY + height / 2;
        const depth = 14 * scale;

        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        const d = `M ${x} ${topY} ` +
                  `C ${x - depth * 0.8} ${topY + height * 0.1}, ${x - depth * 0.8} ${midY - height * 0.1}, ${x - depth} ${midY} ` +
                  `C ${x - depth * 0.8} ${midY + height * 0.1}, ${x - depth * 0.8} ${bottomY - height * 0.1}, ${x} ${bottomY} ` +
                  `C ${x - depth * 0.5} ${bottomY - height * 0.05}, ${x - depth * 0.4} ${midY + height * 0.05}, ${x - depth * 0.6} ${midY} ` +
                  `C ${x - depth * 0.4} ${midY - height * 0.05}, ${x - depth * 0.5} ${topY + height * 0.05}, ${x} ${topY} Z`;

        path.setAttribute("d", d);
        path.setAttribute("fill", this.engraverColor);
        this.svg.appendChild(path);
    }

    /**
     * Draw Vertical Bar Line spanning across staves
     */
    drawBarLine(x, y, isFinalEnd, totalSystemHeight) {
        const scale = this.zoom || 1.0;
        const topY = y;
        const bottomY = y + totalSystemHeight;

        if (isFinalEnd) {
            const line1 = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line1.setAttribute("x1", x - 5 * scale);
            line1.setAttribute("y1", topY);
            line1.setAttribute("x2", x - 5 * scale);
            line1.setAttribute("y2", bottomY);
            line1.setAttribute("stroke", this.engraverColor);
            line1.setAttribute("stroke-width", `${1.1 * scale}`);
            this.svg.appendChild(line1);

            const line2 = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line2.setAttribute("x1", x);
            line2.setAttribute("y1", topY);
            line2.setAttribute("x2", x);
            line2.setAttribute("y2", bottomY);
            line2.setAttribute("stroke", this.engraverColor);
            line2.setAttribute("stroke-width", `${3.5 * scale}`);
            this.svg.appendChild(line2);
        } else {
            const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line.setAttribute("x1", x);
            line.setAttribute("y1", topY);
            line.setAttribute("x2", x);
            line.setAttribute("y2", bottomY);
            line.setAttribute("stroke", this.lightLineColor);
            line.setAttribute("stroke-width", `${1.0 * scale}`);
            this.svg.appendChild(line);
        }
    }

    /** 
     * Draw Clefs (G-clef, F-clef, C-clef)
     */
    drawClef(clefSign, x, y) {
        if (clefSign === "F") {
            this.drawBassClef(x, y);
        } else if (clefSign === "C") {
            this.drawAltoClef(x, y);
        } else {
            this.drawTrebleClef(x, y);
        }
    }

    /**
     * Draw G-clef (Treble) Vector
     */
    drawTrebleClef(x, y) {
        const scale = this.zoom || 1.0;
        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        const pathData = "M165 177q-24 30-26 60-2 34 19 64 23 32 57 34h21l4 23q3 15 2 26-1 15-9 24-9 10-23 9-6 0-11-3l10-5q9-7 10-19 0-12-6-21-8-9-20-10t-22 9q-7 10-9 22-1 19 14 31 13 11 31 12a52 52 0 0 0 34-9q17-13 18-31 1-15-2-34l-4-29q17-5 28-20 12-15 13-36 3-25-12-46a51 51 0 0 0-46-23l-5-36q20-16 32-42 12-24 14-53 0-17-5-41-7-31-22-33-6 0-12 6a89 89 0 0 0-25 37 167 167 0 0 0-3 89q-31 29-45 45m98 97c0 12-5 31-13 36l-9-63q21 6 22 27m-41-169q1-18 9-37 10-22 16-22h3c5 0 10 2 9 15q-1 17-13 35-10 15-22 25-3-7-2-16m-6 76 3 27q-14 6-23 18-12 13-13 30-1 18 8 31 4 7 12 13c7 5 16 5 18 2q0-4-8-15-4-5-4-13 1-18 16-25l9 70-16 1q-22-2-39-19a48 48 0 0 1-16-38q3-42 53-82";
        
        const tx = x - 7.5 * scale;
        const ty = y - 20.0 * scale;
        const sx = 0.2075 * scale;
        const sy = 0.2075 * scale;
        
        path.setAttribute("d", pathData);
        path.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
        path.setAttribute("fill", this.engraverColor);
        this.svg.appendChild(path);
    }

    /**
     * Draw F-clef (Bass) Vector with double dots
     */
    drawBassClef(x, y) {
        const scale = this.zoom || 1.0;
        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        const pathData = "M205 23c-67 0-107 39-118 77-11 39 3 77 17 98h1a64 64 0 0 0 52 26 64 64 0 0 0 64-64 64 64 0 0 0-64-64 64 64 0 0 0-50 24l3-18c10-33 34-61 95-61 60 0 94 64 92 153-1 80-12 128-60 171q-72 65-180 107c-13 5-1 19 7 16 73-28 145-53 196-98 51-46 96-87 96-198 1-97-44-169-151-169";
        
        const tx = x + 2.5 * scale;
        const ty = y - 1.5 * scale;
        const sx = 0.09 * scale;
        const sy = 0.09 * scale;
        
        path.setAttribute("d", pathData);
        path.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
        path.setAttribute("fill", this.engraverColor);
        this.svg.appendChild(path);
        
        // Double dots around line 4
        const dotX = x + 18.5 * scale;
        const r = 1.5 * scale;
        this.drawCircle(dotX, y + 1 * this.lineSpacing, r, this.engraverColor); // FIX: Position relative to staff lines
        this.drawCircle(dotX, y + 3 * this.lineSpacing, r, this.engraverColor); // FIX: Position relative to staff lines
    }

    /**
     * Draw C-clef (Alto/Tenor) Vector
     */
    drawAltoClef(x, y) {
        const scale = this.zoom || 1.0;
        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        const pathData = "M 0 0 L 4 0 L 4 38 L 0 38 Z M 6 0 L 10 0 L 10 38 L 6 38 Z M 10 9 C 18 9 20 14 20 19 C 20 24 18 29 10 29 Z";
        
        path.setAttribute("d", pathData);
        path.setAttribute("transform", `translate(${x}, ${y}) scale(${scale})`);
        path.setAttribute("fill", this.engraverColor);
        this.svg.appendChild(path);
    }

    drawCircle(cx, cy, r, color) {
        const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        circle.setAttribute("cx", cx);
        circle.setAttribute("cy", cy);
        circle.setAttribute("r", r);
        circle.setAttribute("fill", color);
        this.svg.appendChild(circle);
    }

    /**
     * Draw Time Signature (4/4, 3/4, Common Time C, Cut Time ¢)
     */
    drawTimeSignature(x, y, beats, beatType, symbol = null) {
        const scale = this.zoom || 1.0;
        
        if (symbol === "common" || (beats === 4 && beatType === 4 && symbol === "C")) {
            this.drawText(x, y + 24 * scale, "C", `${Math.round(24 * scale)}px`, this.engraverColor, "middle", true, "'Outfit', 'Times New Roman', serif");
        } else if (symbol === "cut") {
            this.drawText(x, y + 2 * this.lineSpacing, "¢", `${Math.round(24 * scale)}px`, this.engraverColor, "middle", true, "'Outfit', 'Times New Roman', serif");
        } else {
            const topY = y + 1.5 * this.lineSpacing; // FIX: Position relative to staff lines
            const bottomY = y + 3.5 * this.lineSpacing; // FIX: Position relative to staff lines
            const size = `${Math.round(18 * scale)}px`;
            
            this.drawText(x, topY, `${beats}`, size, this.engraverColor, "middle", true, "'Outfit', 'Times New Roman', serif"); // Centered on space 1
            this.drawText(x, bottomY, `${beatType}`, size, this.engraverColor, "middle", true, "'Outfit', 'Times New Roman', serif"); // Centered on space 3
        }
    }

    /**
     * Draw Key Signature accidentals
     */
    drawKeySignature(x, y, fifths, clefType) {
        if (!fifths || fifths === 0) return;
        
        const scale = this.zoom || 1.0;
        const count = Math.abs(fifths);
        
        const trebleSharps = [10, 7, 11, 8, 5, 9, 6];
        const trebleFlats = [6, 9, 5, 8, 4, 7, 3];
        const bassSharps = [-4, -7, -3, -6, -9, -5, -8];
        const bassFlats = [-8, -5, -9, -6, -10, -7, -11];
        
        const positions = fifths > 0 
            ? (clefType === "F" ? bassSharps : trebleSharps)
            : (clefType === "F" ? bassFlats : trebleFlats);
            
        for (let i = 0; i < Math.min(count, positions.length); i++) {
            const diatonic = positions[i];
            const symY = this.getNoteY(diatonic, y, clefType);
            const symX = x + i * 8.5 * scale;
            if (fifths > 0) {
                this.drawSharp(symX, symY);
            } else {
                this.drawFlat(symX, symY);
            }
        }
    }

    /**
     * Draw Sharp ♯ Vector
     */
    drawSharp(x, y) {
        const scale = this.zoom || 1.0;
        const paths = [
            "M1.2 0 L1.6 0 L1.6 10 L1.2 10 Z",
            "M3.0 0 L3.4 0 L3.4 10 L3.0 10 Z",
            "M0 3.5 L5 2.5 L5 3.0 L0 4.0 Z",
            "M0 6.5 L5 5.5 L5 6.0 L0 7.0 Z"
        ];
        const tx = x - 5.5 * scale;
        const ty = y - 10.5 * scale;
        const sx = 2.25 * scale;
        const sy = 2.25 * scale;
        
        paths.forEach(d => {
            const p = document.createElementNS("http://www.w3.org/2000/svg", "path");
            p.setAttribute("d", d);
            p.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
            p.setAttribute("fill", this.engraverColor);
            this.svg.appendChild(p);
        });
    }

    /**
     * Draw Flat ♭ Vector
     */
    drawFlat(x, y) {
        const scale = this.zoom || 1.0;
        const d = "M 1.2 0 L 1.6 0 L 1.6 9 C 4.8 9 4.8 18 1.6 18 L 1.2 18 Z";
        const tx = x - 3.15 * scale;
        const ty = y - 30.35 * scale;
        const sx = 2.25 * scale;
        const sy = 2.25 * scale;
        
        const p = document.createElementNS("http://www.w3.org/2000/svg", "path");
        p.setAttribute("d", d);
        p.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
        p.setAttribute("fill", this.engraverColor);
        this.svg.appendChild(p);
    }

    /**
     * Draw Natural ♮ Vector
     */
    drawNatural(x, y) {
        const scale = this.zoom || 1.0;
        const d = "M 1 0 L 1.4 0 L 1.4 10 L 1 10 Z M 3 0 L 3.4 0 L 3.4 10 L 3 10 Z M 1 3 L 3.4 3 L 3.4 3.5 L 1 3.5 Z M 1 6.5 L 3.4 6.5 L 3.4 7 L 1 7 Z";
        const tx = x - 4.5 * scale;
        const ty = y - 10.5 * scale;
        const sx = 2.25 * scale;
        const sy = 2.25 * scale;
        
        const p = document.createElementNS("http://www.w3.org/2000/svg", "path");
        p.setAttribute("d", d);
        p.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
        p.setAttribute("fill", this.engraverColor);
        this.svg.appendChild(p);
    }

    /**
     * Render Note Column / Chord
     */
    drawNoteColumn(x, y, notes, clefType, activeTies) {
        const scale = this.zoom || 1.0;        
        let hasRest = false;
        let restType = "quarter";
        
        const rests = notes.filter(n => n.isRest);
        if (rests.length > 0) {
            hasRest = true;
            restType = rests[0].type;
        }

        if (hasRest) {
            this.drawRestSymbol(x, y, restType);
            return null;
        }

        const calculatedNotes = notes.map(note => {
            const diatonic = this.getDiatonicIndex(note.step, note.octave);
            const noteY = this.getNoteY(diatonic, y, clefType);
            return { ...note, diatonic, y: noteY };
        });

        calculatedNotes.sort((a, b) => a.diatonic - b.diatonic);

        const lowestNote = calculatedNotes[0];
        const highestNote = calculatedNotes[calculatedNotes.length - 1];

        const avgDiatonic = calculatedNotes.reduce((sum, n) => sum + n.diatonic, 0) / calculatedNotes.length;
        const middleDiatonic = clefType === "F" ? -6 : 6;
        const stemDown = avgDiatonic >= middleDiatonic;

        calculatedNotes.forEach(note => {
            const color = this.colorCoded ? (this.pitchColors[note.step] || this.engraverColor) : this.engraverColor;
            const isHollow = note.type === "whole" || note.type === "half";
            
            this.drawNotehead(x, note.y, color, isHollow);

            // Educational letter label inside notehead (only if colorCoded is enabled)
            if (this.colorCoded) {
                this.drawText(x, note.y + 3 * scale, note.step || "", `${Math.round(8 * scale)}px`, isHollow ? this.engraverColor : "#ffffff", "middle", true);
            }

            // Accidentals
            if (note.accidental === "natural") {
                this.drawNatural(x - 14 * scale, note.y);
            } else if (note.alter !== 0) {
                if (note.alter === 1) {
                    this.drawSharp(x - 14 * scale, note.y);
                } else if (note.alter === -1) {
                    this.drawFlat(x - 14 * scale, note.y);
                } else if (note.alter === 0) {
                    this.drawNatural(x - 14 * scale, note.y);
                }
            }

            // Ledger Lines
            this.drawLedgerLines(x, y, note.diatonic, clefType);

            // Articulations (Staccato dot, Accent, Tenuto, Fermata)
            if (note.articulations) {
                const artY = stemDown ? lowestNote.y - 12 * scale : highestNote.y + 12 * scale;
                if (note.articulations.staccato) {
                    this.drawCircle(x, artY, 2 * scale, this.engraverColor);
                }
                if (note.articulations.accent) {
                    this.drawText(x, artY, ">", `${Math.round(14 * scale)}px`, this.engraverColor, "middle", true);
                }
                if (note.articulations.fermata) {
                    this.drawText(x, y - 18 * scale, "𝄐", `${Math.round(16 * scale)}px`, this.engraverColor, "middle", true);
                }
            }

            // Lyric Text
            if (note.lyric) {
                this.drawText(x, y + (this.liricYOffset * scale), note.lyric, `${Math.round(11 * scale)}px`, "#1e293b", "middle", false, "'Inter', sans-serif");
            }

            // Slurs / Ties Bezier Arcs
            let tieStart = false;
            let tieStop = false;
            note.node.querySelectorAll("tie, tied").forEach(t => {
                const type = t.getAttribute("type");
                if (type === "start") tieStart = true;
                if (type === "stop") tieStop = true;
            });
            
            const pitchKey = `${note.step}${note.alter}${note.octave}${note.staff}`;
            
            if (tieStop && activeTies[pitchKey]) {
                const prev = activeTies[pitchKey];
                const yOffset = (stemDown ? -8 : 8) * scale;
                const sy1 = prev.y + yOffset;
                const sy2 = note.y + yOffset;
                
                // System boundary check (x <= prev.x or Y distance > 50*scale)
                const isCrossSystem = (x <= prev.x) || (Math.abs(prev.y - note.y) > 50 * scale);

                if (isCrossSystem) {
                    // Curved tie ending at right edge of start system line
                    const path1 = document.createElementNS("http://www.w3.org/2000/svg", "path");
                    const endX1 = prev.x + 22 * scale;
                    const cx1 = prev.x + 11 * scale;
                    const cy1 = sy1 + (stemDown ? -6 : 6) * scale;
                    path1.setAttribute("d", `M ${prev.x} ${sy1} Q ${cx1} ${cy1} ${endX1} ${sy1}`);
                    path1.setAttribute("fill", "none");
                    path1.setAttribute("stroke", this.engraverColor);
                    path1.setAttribute("stroke-width", `${1.3 * scale}`);
                    this.svg.appendChild(path1);

                    // Curved tie starting from left edge of stop system line
                    const path2 = document.createElementNS("http://www.w3.org/2000/svg", "path");
                    const startX2 = x - 22 * scale;
                    const cx2 = x - 11 * scale;
                    const cy2 = sy2 + (stemDown ? -6 : 6) * scale;
                    path2.setAttribute("d", `M ${startX2} ${sy2} Q ${cx2} ${cy2} ${x} ${sy2}`);
                    path2.setAttribute("fill", "none");
                    path2.setAttribute("stroke", this.engraverColor);
                    path2.setAttribute("stroke-width", `${1.3 * scale}`);
                    this.svg.appendChild(path2);
                } else {
                    const cx = (prev.x + x) / 2;
                    const cy = ((sy1 + sy2) / 2) + (stemDown ? -7 : 7) * scale;
                    
                    const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
                    path.setAttribute("d", `M ${prev.x} ${sy1} Q ${cx} ${cy} ${x} ${sy2}`);
                    path.setAttribute("fill", "none");
                    path.setAttribute("stroke", this.engraverColor);
                    path.setAttribute("stroke-width", `${1.3 * scale}`);
                    this.svg.appendChild(path);
                }
                
                delete activeTies[pitchKey];
            }
            
            if (tieStart) {
                activeTies[pitchKey] = { x: x, y: note.y };
            }
        });

        // Stems & Flags
        const firstNoteType = calculatedNotes[0].type;
        let flagElement = null;
        let stemX = x;
        let stemEndY = y;
        let stemLine = null;
        const isBeamable = firstNoteType === "eighth" || firstNoteType === "16th" || firstNoteType === "32nd";

        if (firstNoteType !== "whole") {
            const stemLength = 28 * scale;
            stemX = stemDown ? x - 6.0 * scale : x + 6.0 * scale;
            const stemStartY = stemDown ? highestNote.y : lowestNote.y;
            stemEndY = stemDown ? lowestNote.y + stemLength : highestNote.y - stemLength;

            stemLine = document.createElementNS("http://www.w3.org/2000/svg", "line");
            stemLine.setAttribute("x1", stemX);
            stemLine.setAttribute("y1", stemStartY);
            stemLine.setAttribute("x2", stemX);
            stemLine.setAttribute("y2", stemEndY);
            stemLine.setAttribute("stroke", this.engraverColor);
            stemLine.setAttribute("stroke-width", `${1.4 * scale}`);
            this.svg.appendChild(stemLine);

            if (isBeamable) {
                flagElement = this.drawStemFlag(stemX, stemEndY, stemDown, firstNoteType === "16th");
            }
        }

        return {
            x: x,
            stemX: stemX,
            stemEndY: stemEndY,
            stemDown: stemDown,
            type: firstNoteType,
            isBeamable: isBeamable,
            // FIX: Pass beat index for correct beaming logic
            beatIndex: Math.floor(lowestNote.onsetDiv / (notes[0].divisions || 1)),
            divisions: notes[0].divisions,
            flagElement: flagElement, // Keep for potential removal
            stemLine: stemLine
        };
    }

    /**
     * Draw Multi-Note Beams (8th/16th/32nd note groups)
     */
    drawBeams(stems) {
        if (!stems || stems.length < 2) return;

        let i = 0;
        while (i < stems.length) {
            // Find the start of a potential beam group
            if (!stems[i].isBeamable) {
                i++;
                continue;
            }

            let currentGroup = [stems[i]];
            let j = i + 1;
            while (j < stems.length && stems[j].isBeamable && stems[j].beatIndex === stems[i].beatIndex && stems[j].stemDown === stems[i].stemDown) {
                currentGroup.push(stems[j]);
                j++;
            }

            if (currentGroup.length > 1) {
                this.renderBeamGroup(currentGroup);
            } else {
                // Not enough notes for a beam, do nothing
            }
            i = j; // Move to the next note after the processed group
        }
    }


    renderBeamGroup(group) {
        const scale = this.zoom || 1.0;
        const first = group[0];
        const last = group[group.length - 1];
        
        group.forEach(s => {
            if (s.flagElement && s.flagElement.parentNode) {
                s.flagElement.parentNode.removeChild(s.flagElement);
            }
        });

        const x1 = first.stemX;
        const y1 = first.stemEndY;
        const x2 = last.stemX;
        const y2 = last.stemEndY;

        // Align intermediate stem endpoints to touch the sloped beam vector exactly
        const dx = x2 - x1;
        const dy = y2 - y1;
        
        if (dx > 0) {
            group.forEach(s => {
                const ratio = (s.stemX - x1) / dx;
                const alignedY = y1 + ratio * dy;
                s.stemEndY = alignedY;
                if (s.stemLine) {
                    s.stemLine.setAttribute("y2", alignedY);
                }
            });
        }

        // Primary Beam Line
        const beam = document.createElementNS("http://www.w3.org/2000/svg", "line");
        beam.setAttribute("x1", x1);
        beam.setAttribute("y1", y1);
        beam.setAttribute("x2", x2);
        beam.setAttribute("y2", y2);
        beam.setAttribute("stroke", this.engraverColor);
        beam.setAttribute("stroke-width", `${3.5 * scale}`);
        this.svg.appendChild(beam);

        // Secondary Beam Line (16th notes)
        const has16th = group.some(s => s.type === "16th" || s.type === "32nd");
        if (has16th) {
            const offset = (first.stemDown ? -5 : 5) * scale;
            const beam2 = document.createElementNS("http://www.w3.org/2000/svg", "line");
            beam2.setAttribute("x1", x1);
            beam2.setAttribute("y1", y1 + offset);
            beam2.setAttribute("x2", x2);
            beam2.setAttribute("y2", y2 + offset);
            beam2.setAttribute("stroke", this.engraverColor);
            beam2.setAttribute("stroke-width", `${3.0 * scale}`);
            this.svg.appendChild(beam2);
        }
    }

    /**
     * Draw Notehead Tilted Ellipse (-15 degrees)
     */
    drawNotehead(cx, cy, color, isHollow) {
        const scale = this.zoom || 1.0;
        const rx = 7.75 * scale;
        const ry = 4.6 * scale;
        
        const ellipse = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        ellipse.setAttribute("cx", cx);
        ellipse.setAttribute("cy", cy);
        ellipse.setAttribute("rx", rx);
        ellipse.setAttribute("ry", ry);
        ellipse.setAttribute("transform", `rotate(-15 ${cx} ${cy})`);
        
        if (isHollow) {
            ellipse.setAttribute("fill", "#ffffff");
            ellipse.setAttribute("stroke", color);
            ellipse.setAttribute("stroke-width", `${2.0 * scale}`);
        } else {
            ellipse.setAttribute("fill", color);
        }
        this.svg.appendChild(ellipse);
    }

    /**
     * Draw Ledger Lines
     */
    drawLedgerLines(x, y, diatonic, clefType) {
        const lineMin = clefType === "F" ? -10 : 2;
        const lineMax = clefType === "F" ? -2 : 10;

        if (diatonic < lineMin) {
            for (let d = lineMin - 2; d >= diatonic; d -= 2) {
                const lineY = this.getNoteY(d, y, clefType);
                this.drawHorizontalLedger(x, lineY);
            }
        } else if (diatonic > lineMax) {
            for (let d = lineMax + 2; d <= diatonic; d += 2) {
                const lineY = this.getNoteY(d, y, clefType);
                this.drawHorizontalLedger(x, lineY);
            }
        }
    }

    drawHorizontalLedger(x, lineY) {
        const scale = this.zoom || 1.0;
        const ledger = document.createElementNS("http://www.w3.org/2000/svg", "line");
        ledger.setAttribute("x1", x - 11 * scale);
        ledger.setAttribute("y1", lineY);
        ledger.setAttribute("x2", x + 11 * scale);
        ledger.setAttribute("y2", lineY);
        ledger.setAttribute("stroke", this.engraverColor);
        ledger.setAttribute("stroke-width", `${1.1 * scale}`);
        this.svg.appendChild(ledger);
    }

    /**
     * Draw Note Stem Flags
     */
    drawStemFlag(x, y, isDown, isDouble) {
        const scale = this.zoom || 1.0;
        const group = document.createElementNS("http://www.w3.org/2000/svg", "g");
        const flagPathUp = "M -0.112 3.631 C -0.112 0 -0.3031 0 0 0 C 0.28 0.1911 0 0 0 0 C 0.42 0.6879 0.512 0.7834 0.531 0.898 C 1.4 2.8 1.4 2.8 2.327 4.051 C 4.028 5.943 4.525 7.071 4.525 8.581 C 4.506 9.994 3.263 13.014 2.996 12.899 C 3.378 11.829 3.913 10.682 4.047 9.727 C 4.219 8.561 3.741 6.879 1.831 5.16 C 0.779 4.294 0 4.2 -0.112 3.631 Z";
        const flagPathDown = "M -0.112 -3.631 C -0.112 0 -0.3031 0 0 0 C 0.28 -0.1911 0 0 0 0 C 0.42 -0.6879 0.512 -0.7834 0.531 -0.898 C 1.4 -2.8 1.4 -2.8 2.327 -4.051 C 4.028 -5.943 4.525 -7.071 4.525 -8.581 C 4.506 -9.994 3.263 -13.014 2.996 -12.899 C 3.378 -11.829 3.913 -10.682 4.047 -9.727 C 4.219 -8.561 3.741 -6.879 1.831 -5.16 C 0.779 -4.294 0 -4.2 -0.112 -3.631 Z";
        
        const path = isDown ? flagPathDown : flagPathUp;
        const sx = 2.0 * scale;
        const sy = 1.6 * scale;
        
        const p1 = document.createElementNS("http://www.w3.org/2000/svg", "path");
        p1.setAttribute("d", path);
        p1.setAttribute("transform", `translate(${x}, ${y}) scale(${sx}, ${sy})`);
        p1.setAttribute("fill", this.engraverColor);
        group.appendChild(p1);
        
        if (isDouble) {
            const yOffset = (isDown ? -8.5 : 8.5) * scale;
            const p2 = document.createElementNS("http://www.w3.org/2000/svg", "path");
            p2.setAttribute("d", path);
            p2.setAttribute("transform", `translate(${x}, ${y + yOffset}) scale(${sx}, ${sy})`);
            p2.setAttribute("fill", this.engraverColor);
            group.appendChild(p2);
        }
        
        this.svg.appendChild(group);
        return group;
    }

    /**
     * Draw Rest Symbols
     */
    drawRestSymbol(x, y, type) {
        const scale = this.zoom || 1.0;
        
        switch (type) {
            case 'whole':
                const rWhole = document.createElementNS("http://www.w3.org/2000/svg", "rect");
                rWhole.setAttribute("x", x - 10 * scale);
                rWhole.setAttribute("y", y + 9.5 * scale);
                rWhole.setAttribute("width", 20 * scale);
                rWhole.setAttribute("height", 6 * scale);
                rWhole.setAttribute("fill", this.engraverColor);
                this.svg.appendChild(rWhole);
                break;
                
            case 'half':
                const rHalf = document.createElementNS("http://www.w3.org/2000/svg", "rect");
                rHalf.setAttribute("x", x - 10 * scale);
                rHalf.setAttribute("y", y + 13.5 * scale);
                rHalf.setAttribute("width", 20 * scale);
                rHalf.setAttribute("height", 6 * scale);
                rHalf.setAttribute("fill", this.engraverColor);
                this.svg.appendChild(rHalf);
                break;
                
            case 'quarter':
                const qPath = "M349 372c-14-12-44-43-65-102-21-58 25-95 50-114q12-7-1-21L219 9c-13-17-30-7-20 7 120 171-35 197-35 197s17 44 97 115c-84-22-139 40-97 104 41 64 120 78 127 80s18-4 7-11c-26-17-79-61-54-93 34-42 84-23 97-17 22 11 31-1 8-19";
                const pQ = document.createElementNS("http://www.w3.org/2000/svg", "path");
                pQ.setAttribute("d", qPath);
                pQ.setAttribute("transform", `translate(${x - 8 * scale}, ${y + 5 * scale}) scale(${0.06 * scale})`);
                pQ.setAttribute("fill", this.engraverColor);
                this.svg.appendChild(pQ);
                break;
                
            case 'eighth':
            case '16th':
            case '32nd':
                const hookPath = "M 1.098 0 C 0.578 0.098 0.18 0.457 0 0.953 C -0.039 1.113 -0.039 1.152 -0.039 1.371 C -0.039 1.672 -0.02 1.832 0.121 2.07 C 0.32 2.469 0.738 2.789 1.215 2.906 C 1.715 3.047 3 3.153 4 2.153 L 4.941 0.598 C 4.844 0.477 4.645 0.438 4.523 0.535 C 4.484 0.574 4.422 0.656 4.383 0.715 C 4.203 1.016 3.746 1.551 3.508 1.75 C 3.289 1.93 3.168 1.949 2.969 1.871 C 2.789 1.773 2.73 1.672 2.609 1.133 C 2.492 0.598 2.352 0.355 2.051 0.156 C 1.773 -0.023 1.414 -0.082 1.098 0 z";
                const hookScale = 2.75 * scale;
                
                const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                line.setAttribute("x1", x + 15 * scale);
                line.setAttribute("y1", y + 10 * scale);
                line.setAttribute("x2", x + 10 * scale);
                line.setAttribute("y2", y + 40.5 * scale);
                line.setAttribute("stroke", this.engraverColor);
                line.setAttribute("stroke-width", `${1.1 * scale}`);
                this.svg.appendChild(line);
                
                const pH1 = document.createElementNS("http://www.w3.org/2000/svg", "path");
                pH1.setAttribute("d", hookPath);
                pH1.setAttribute("transform", `translate(${x + 1.5 * scale}, ${y + 11 * scale}) scale(${hookScale})`);
                pH1.setAttribute("fill", this.engraverColor);
                this.svg.appendChild(pH1);
                
                if (type === '16th' || type === '32nd') {
                    const pH2 = document.createElementNS("http://www.w3.org/2000/svg", "path");
                    pH2.setAttribute("d", hookPath);
                    pH2.setAttribute("transform", `translate(${x - 0.5 * scale}, ${y + 21 * scale}) scale(${hookScale})`);
                    pH2.setAttribute("fill", this.engraverColor);
                    this.svg.appendChild(pH2);
                }
                break;
        }
    }

    getDiatonicIndex(step, octave) {
        const offset = this.stepOffsets[step] !== undefined ? this.stepOffsets[step] : 0;
        const oct = typeof octave === "number" && !isNaN(octave) ? octave : 4;
        return (oct - 4) * 7 + offset;
    }

    getNoteY(diatonic, startY, clefType) {
        if (clefType === "F") {
            // FIX: Correct logic for F-clef (Bass clef). Diatonic 0 (G2) is on the bottom line.
            return startY + (8 - diatonic) * (this.lineSpacing / 2);
        } else {
            // G-clef (Treble clef). Diatonic 0 (E4) is on the bottom line.
            return startY + (10 - diatonic) * (this.lineSpacing / 2);
        }
    }

    drawText(x, y, text, size, color, anchor = "middle", isBold = false, fontStyle = "'Inter', sans-serif") {
        const txt = document.createElementNS("http://www.w3.org/2000/svg", "text");
        txt.setAttribute("x", x);
        txt.setAttribute("y", y);
        txt.setAttribute("font-size", size);
        txt.setAttribute("fill", color);
        txt.setAttribute("text-anchor", anchor === "center" ? "middle" : anchor);
        txt.setAttribute("font-family", fontStyle);
        if (isBold) {
            txt.setAttribute("font-weight", "bold");
        }
        txt.textContent = text || "";
        this.svg.appendChild(txt);
    }
}

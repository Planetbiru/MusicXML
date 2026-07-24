/**
 * Planetbiru Custom MusicXML SVG Renderer
 * A simplified, elegant, and modern sheet music renderer that parses MusicXML 
 * and draws sharp vector notations on an SVG canvas, featuring pitch color-coding,
 * dynamic zoom, grand staff (Treble/Bass) support, and key signature rendering.
 * Matches visual vector assets and layout metrics of PHP SheetMusicSVG/SheetMusicTrait.
 */
class MusicXMLSvgRenderer {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.svg = null;
        this.zoom = 1.0; // Dynamic scale factor
        
        // Base Configuration
        this.baseLineSpacing = 10;
        this.baseRowSpacingSingle = 160;
        this.baseRowSpacingDouble = 230;
        this.baseStaffSpacing = 70; // gap between Treble & Bass staves
        this.measuresPerLine = 3;
        
        this.pitchColors = {
            'C': '#ef4444', // Red
            'D': '#f97316', // Orange
            'E': '#f59e0b', // Yellow-gold
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
        this.container.innerHTML = "";
        
        // Calculate scaled dimensions
        const scale = this.zoom || 1.0;
        this.lineSpacing = this.baseLineSpacing * scale;
        this.staffSpacing = this.baseStaffSpacing * scale;
        
        // Parse MusicXML
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlText, "application/xml");
        
        // Find errors
        const parserError = xmlDoc.querySelector("parsererror");
        if (parserError) {
            throw new Error("Invalid XML structure: " + parserError.textContent);
        }

        // Setup SVG
        const containerWidth = this.container.clientWidth || 800;
        this.svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        this.svg.setAttribute("width", "100%");
        this.svg.style.backgroundColor = "#ffffff";
        this.svg.style.display = "block";
        this.container.appendChild(this.svg);

        // Find parts and measures
        const parts = xmlDoc.querySelectorAll("part");
        if (parts.length === 0) {
            this.container.innerHTML = "<div style='color:#ef4444; padding:2rem; text-align:center;'>No musical parts found in MusicXML.</div>";
            return;
        }

        const primaryPart = parts[0];
        const measures = primaryPart.querySelectorAll("measure");
        
        if (measures.length === 0) {
            this.container.innerHTML = "<div style='color:#ef4444; padding:2rem; text-align:center;'>No measures found in MusicXML.</div>";
            return;
        }

        // Detect if grand staff is needed
        let activeNumStaves = 1;
        const firstMeasure = measures[0];
        if (firstMeasure) {
            const stavesNode = firstMeasure.querySelector("attributes staves");
            if (stavesNode) {
                activeNumStaves = parseInt(stavesNode.textContent);
            } else {
                // Scan notes to see if there are staff 2 elements
                const hasStaff2 = firstMeasure.querySelector("note staff")?.textContent === "2";
                if (hasStaff2) activeNumStaves = 2;
            }
        }

        // Set row spacing based on staff count
        this.rowSpacing = (activeNumStaves === 2 ? this.baseRowSpacingDouble : this.baseRowSpacingSingle) * scale;

        // Layout parameters
        const leftMargin = 70 * scale;
        const rightMargin = 30 * scale;
        const usableWidth = containerWidth - leftMargin - rightMargin;
        const measureWidth = Math.max(220 * scale, usableWidth / this.measuresPerLine);
        
        let currentX = leftMargin;
        let currentY = 50 * scale; // top padding
        let rowCount = 0;
        
        // Active metadata status
        let activeClef1 = "G";
        let activeClef2 = "F";
        let activeBeats = 4;
        let activeBeatType = 4;
        let activeFifths = 0;
        
        // Render measures
        measures.forEach((measureNode, measureIdx) => {
            if (measureIdx > 0 && measureIdx % this.measuresPerLine === 0) {
                rowCount++;
                currentX = leftMargin;
                currentY += this.rowSpacing;
            }

            const attrNode = measureNode.querySelector("attributes");
            if (attrNode) {
                // Parse staves count
                const stavesNode = attrNode.querySelector("staves");
                if (stavesNode) activeNumStaves = parseInt(stavesNode.textContent);

                // Parse clefs
                attrNode.querySelectorAll("clef").forEach(clefNode => {
                    const number = parseInt(clefNode.getAttribute("number") || "1");
                    const sign = clefNode.querySelector("sign")?.textContent;
                    if (number === 1 && sign) activeClef1 = sign;
                    if (number === 2 && sign) activeClef2 = sign;
                });
                
                // Key Signature fifths
                const fifthsNode = attrNode.querySelector("key fifths");
                if (fifthsNode) activeFifths = parseInt(fifthsNode.textContent);

                // Time beats
                const beats = attrNode.querySelector("time beats")?.textContent;
                const beatType = attrNode.querySelector("time beat-type")?.textContent;
                if (beats) activeBeats = parseInt(beats);
                if (beatType) activeBeatType = parseInt(beatType);
            }

            // Draw staff lines
            this.drawStaffLines(currentX, currentY, measureWidth);
            if (activeNumStaves === 2) {
                this.drawStaffLines(currentX, currentY + this.staffSpacing, measureWidth);
            }

            // Draw clef, key signature, and time signature at the start of each line
            if (measureIdx % this.measuresPerLine === 0) {
                // Clef 1 (Treble)
                this.drawTrebleClef(currentX - 50 * scale, currentY);
                this.drawKeySignature(currentX - 35 * scale, currentY, activeFifths, activeClef1);
                this.drawTimeSignature(currentX - 12 * scale, currentY, activeBeats, activeBeatType);

                // Clef 2 (Bass) if grand staff
                if (activeNumStaves === 2) {
                    this.drawBassClef(currentX - 50 * scale, currentY + this.staffSpacing);
                    this.drawKeySignature(currentX - 35 * scale, currentY + this.staffSpacing, activeFifths, activeClef2);
                    this.drawTimeSignature(currentX - 12 * scale, currentY + this.staffSpacing, activeBeats, activeBeatType);
                    
                    // Draw a left vertical connector line
                    const brace = document.createElementNS("http://www.w3.org/2000/svg", "line");
                    brace.setAttribute("x1", currentX);
                    brace.setAttribute("y1", currentY);
                    brace.setAttribute("x2", currentX);
                    brace.setAttribute("y2", currentY + this.staffSpacing + 4 * this.lineSpacing);
                    brace.setAttribute("stroke", "#0f172a");
                    brace.setAttribute("stroke-width", `${2.5 * scale}`);
                    this.svg.appendChild(brace);
                }
            }

            // Draw barlines
            this.drawBarLine(currentX, currentY, measureIdx === 0, false, activeNumStaves);
            if (measureIdx === measures.length - 1) {
                this.drawBarLine(currentX + measureWidth, currentY, false, true, activeNumStaves);
            } else {
                this.drawBarLine(currentX + measureWidth, currentY, false, false, activeNumStaves);
            }

            // Draw measure number
            this.drawText(currentX + 5 * scale, currentY - 15 * scale, `${measureIdx + 1}`, `${Math.round(10 * scale)}px`, "#64748b", "start", true);

            // Group notes into chord columns
            const columns = [];
            let currentCol = null;

            measureNode.querySelectorAll("note").forEach(noteNode => {
                const isChord = noteNode.querySelector("chord") !== null;
                const isRest = noteNode.querySelector("rest") !== null;
                const staffVal = parseInt(noteNode.querySelector("staff")?.textContent || "1");
                
                const noteData = {
                    node: noteNode,
                    isRest: isRest,
                    staff: staffVal,
                    step: noteNode.querySelector("pitch step")?.textContent,
                    octave: parseInt(noteNode.querySelector("pitch octave")?.textContent || "4"),
                    alter: parseInt(noteNode.querySelector("pitch alter")?.textContent || "0"),
                    type: noteNode.querySelector("type")?.textContent || "quarter",
                    stem: noteNode.querySelector("stem")?.textContent
                };
                
                if (isChord && currentCol) {
                    currentCol.push(noteData);
                } else {
                    currentCol = [noteData];
                    columns.push(currentCol);
                }
            });

            // Position and render note columns separately for each staff
            if (columns.length > 0) {
                const colSpacing = (measureWidth - 40 * scale) / columns.length;
                columns.forEach((col, colIdx) => {
                    const colX = currentX + 25 * scale + colIdx * colSpacing;
                    
                    const staff1Notes = col.filter(n => n.staff === 1);
                    const staff2Notes = col.filter(n => n.staff === 2);
                    
                    if (staff1Notes.length > 0) {
                        this.drawNoteColumn(colX, currentY, staff1Notes, activeClef1);
                    }
                    if (staff2Notes.length > 0 && activeNumStaves === 2) {
                        this.drawNoteColumn(colX, currentY + this.staffSpacing, staff2Notes, activeClef2);
                    }
                });
            }
        });

        const totalHeight = currentY + this.rowSpacing;
        this.svg.setAttribute("height", `${totalHeight}px`);
        this.svg.setAttribute("viewBox", `0 0 ${containerWidth} ${totalHeight}`);
    }

    /**
     * Draw 5 horizontal staff lines
     */
    drawStaffLines(x, y, width) {
        for (let i = 0; i < 5; i++) {
            const lineY = y + i * this.lineSpacing;
            const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line.setAttribute("x1", x);
            line.setAttribute("y1", lineY);
            line.setAttribute("x2", x + width);
            line.setAttribute("y2", lineY);
            line.setAttribute("stroke", "#cbd5e1");
            line.setAttribute("stroke-width", `${0.8 * this.zoom}`);
            this.svg.appendChild(line);
        }
    }

    /**
     * Draw vertical bar lines crossing all active staves
     */
    drawBarLine(x, y, isStart, isEnd = false, numStaves = 1) {
        const topY = y;
        const bottomY = y + (numStaves === 2 ? this.staffSpacing : 0) + 4 * this.lineSpacing;
        const scale = this.zoom || 1.0;

        if (isEnd) {
            const line1 = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line1.setAttribute("x1", x - 4 * scale);
            line1.setAttribute("y1", topY);
            line1.setAttribute("x2", x - 4 * scale);
            line1.setAttribute("y2", bottomY);
            line1.setAttribute("stroke", "#0f172a");
            line1.setAttribute("stroke-width", `${1 * scale}`);
            this.svg.appendChild(line1);

            const line2 = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line2.setAttribute("x1", x);
            line2.setAttribute("y1", topY);
            line2.setAttribute("x2", x);
            line2.setAttribute("y2", bottomY);
            line2.setAttribute("stroke", "#0f172a");
            line2.setAttribute("stroke-width", `${3 * scale}`);
            this.svg.appendChild(line2);
        } else {
            const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line.setAttribute("x1", x);
            line.setAttribute("y1", topY);
            line.setAttribute("x2", x);
            line.setAttribute("y2", bottomY);
            line.setAttribute("stroke", isStart ? "#0f172a" : "#cbd5e1");
            line.setAttribute("stroke-width", `${isStart ? 1.5 * scale : 0.8 * scale}`);
            this.svg.appendChild(line);
        }
    }

    /**
     * Draw G-clef using FPDF vector path metrics (SheetMusicTrait line 75)
     */
    drawTrebleClef(x, y) {
        const scale = this.zoom || 1.0;
        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        const pathData = "M165 177q-24 30-26 60-2 34 19 64 23 32 57 34h21l4 23q3 15 2 26-1 15-9 24-9 10-23 9-6 0-11-3l10-5q9-7 10-19 0-12-6-21-8-9-20-10t-22 9q-7 10-9 22-1 19 14 31 13 11 31 12a52 52 0 0 0 34-9q17-13 18-31 1-15-2-34l-4-29q17-5 28-20 12-15 13-36 3-25-12-46a51 51 0 0 0-46-23l-5-36q20-16 32-42 12-24 14-53 0-17-5-41-7-31-22-33-6 0-12 6a89 89 0 0 0-25 37 167 167 0 0 0-3 89q-31 29-45 45m98 97c0 12-5 31-13 36l-9-63q21 6 22 27m-41-169q1-18 9-37 10-22 16-22h3c5 0 10 2 9 15q-1 17-13 35-10 15-22 25-3-7-2-16m-6 76 3 27q-14 6-23 18-12 13-13 30-1 18 8 31 4 7 12 13c7 5 16 5 18 2q0-4-8-15-4-5-4-13 1-18 16-25l9 70-16 1q-22-2-39-19a48 48 0 0 1-16-38q3-42 53-82";
        
        const tx = x - 4.5 * 5 * scale;
        const ty = y - 4.0 * 5 * scale;
        const sx = 0.0415 * 5 * scale;
        const sy = 0.0415 * 5 * scale;
        
        path.setAttribute("d", pathData);
        path.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
        path.setAttribute("fill", "#0f172a");
        path.setAttribute("stroke", "#ffffff");
        path.setAttribute("stroke-width", `${0.25 * 5 * scale}`);
        this.svg.appendChild(path);
    }

    /**
     * Draw F-clef using FPDF vector path & dots metrics (SheetMusicTrait line 109)
     */
    drawBassClef(x, y) {
        const scale = this.zoom || 1.0;
        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        const pathData = "M205 23c-67 0-107 39-118 77-11 39 3 77 17 98h1a64 64 0 0 0 52 26 64 64 0 0 0 64-64 64 64 0 0 0-64-64 64 64 0 0 0-50 24l3-18c10-33 34-61 95-61 60 0 94 64 92 153-1 80-12 128-60 171q-72 65-180 107c-13 5-1 19 7 16 73-28 145-53 196-98 51-46 96-87 96-198 1-97-44-169-151-169";
        
        const tx = x - 0.5 * 5 * scale;
        const ty = y - 0.3 * 5 * scale;
        const sx = 0.018 * 5 * scale;
        const sy = 0.018 * 5 * scale;
        
        path.setAttribute("d", pathData);
        path.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
        path.setAttribute("fill", "#0f172a");
        path.setAttribute("stroke", "#ffffff");
        path.setAttribute("stroke-width", `${0.25 * 5 * scale}`);
        this.svg.appendChild(path);
        
        // Draw F-clef double-dots
        const dotX = x + 6.25 * 5 * scale;
        const r = 0.45 * 5 * scale;
        this.drawCircle(dotX, y + 0.85 * 5 * scale, r, "#0f172a");
        this.drawCircle(dotX, y + 3.15 * 5 * scale, r, "#0f172a");
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
     * Draw time signature
     */
    drawTimeSignature(x, y, beats, beatType) {
        const scale = this.zoom || 1.0;
        const topY = y + 12 * scale;
        const bottomY = y + 32 * scale;
        const size = `${Math.round(18 * scale)}px`;
        this.drawText(x, topY, `${beats}`, size, "#0f172a", "middle", true);
        this.drawText(x, bottomY, `${beatType}`, size, "#0f172a", "middle", true);
    }

    /**
     * Draw Key Signature accidentals next to clef
     */
    drawKeySignature(x, y, fifths, clefType) {
        if (!fifths || fifths === 0) return;
        
        const scale = this.zoom || 1.0;
        const count = Math.abs(fifths);
        
        // Define diatonic positions for G & F staves
        const trebleSharps = [10, 7, 11, 8, 5, 9, 6];   // F5, C5, G5, D5, A4, E5, B4
        const trebleFlats = [6, 9, 5, 8, 4, 7, 3];      // B4, E5, A4, D5, G4, C5, F4
        
        const bassSharps = [-4, -7, -3, -6, -9, -5, -8]; // F3, C3, G3, D3, A2, E3, B2
        const bassFlats = [-8, -5, -9, -6, -10, -7, -11]; // B2, E3, A2, D3, G2, C3, F2
        
        const positions = fifths > 0 
            ? (clefType === "F" ? bassSharps : trebleSharps)
            : (clefType === "F" ? bassFlats : trebleFlats);
            
        for (let i = 0; i < Math.min(count, positions.length); i++) {
            const diatonic = positions[i];
            const symY = this.getNoteY(diatonic, y, clefType);
            const symX = x + i * 8 * scale;
            if (fifths > 0) {
                this.drawSharp(symX, symY);
            } else {
                this.drawFlat(symX, symY);
            }
        }
    }

    /**
     * Draw Sharp vector shape (SheetMusicTrait line 150)
     */
    drawSharp(x, y) {
        const scale = this.zoom || 1.0;
        const paths = [
            "M1.2 0 L1.6 0 L1.6 10 L1.2 10 Z",
            "M3.0 0 L3.4 0 L3.4 10 L3.0 10 Z",
            "M0 3.5 L5 2.5 L5 3.0 L0 4.0 Z",
            "M0 6.5 L5 5.5 L5 6.0 L0 7.0 Z"
        ];
        const tx = x - 1.1 * 5 * scale;
        const ty = y - 2.1 * 5 * scale;
        const sx = 0.45 * 5 * scale;
        const sy = 0.45 * 5 * scale;
        
        paths.forEach(d => {
            const p = document.createElementNS("http://www.w3.org/2000/svg", "path");
            p.setAttribute("d", d);
            p.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
            p.setAttribute("fill", "#0f172a");
            this.svg.appendChild(p);
        });
    }

    /**
     * Draw Flat vector shape (SheetMusicTrait line 172)
     */
    drawFlat(x, y) {
        const scale = this.zoom || 1.0;
        const d = "M 1.2 0 L 1.6 0 L 1.6 9 C 4.8 9 4.8 18 1.6 18 L 1.2 18 Z";
        const tx = x - 0.63 * 5 * scale;
        const ty = y - 6.07 * 5 * scale;
        const sx = 0.45 * 5 * scale;
        const sy = 0.45 * 5 * scale;
        
        const p = document.createElementNS("http://www.w3.org/2000/svg", "path");
        p.setAttribute("d", d);
        p.setAttribute("transform", `translate(${tx}, ${ty}) scale(${sx}, ${sy})`);
        p.setAttribute("fill", "#0f172a");
        this.svg.appendChild(p);
    }

    /**
     * Render a single chord column
     */
    drawNoteColumn(x, y, notes, clefType) {
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
            return;
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
            const color = this.pitchColors[note.step] || "#000000";
            const isHollow = note.type === "whole" || note.type === "half";
            this.drawNotehead(x, note.y, color, isHollow);

            // Draw pitch label
            this.drawText(x, note.y + 3 * scale, note.step, `${Math.round(8 * scale)}px`, isHollow ? "#0f172a" : "#ffffff", "middle", true);

            // Accidentals
            if (note.alter !== 0) {
                if (note.alter === 1) {
                    this.drawSharp(x - 14 * scale, note.y);
                } else if (note.alter === -1) {
                    this.drawFlat(x - 14 * scale, note.y);
                }
            }

            this.drawLedgerLines(x, y, note.diatonic, clefType);
        });

        // Draw stems (do not draw stems for whole notes)
        const firstNoteType = calculatedNotes[0].type;
        if (firstNoteType !== "whole") {
            const stemLength = 28 * scale;
            const stemX = stemDown ? x - 5.5 * scale : x + 5.5 * scale;
            const stemStartY = stemDown ? highestNote.y : lowestNote.y;
            const stemEndY = stemDown ? lowestNote.y + stemLength : highestNote.y - stemLength;

            const stem = document.createElementNS("http://www.w3.org/2000/svg", "line");
            stem.setAttribute("x1", stemX);
            stem.setAttribute("y1", stemStartY);
            stem.setAttribute("x2", stemX);
            stem.setAttribute("y2", stemEndY);
            stem.setAttribute("stroke", "#0f172a");
            stem.setAttribute("stroke-width", `${1.5 * scale}`);
            this.svg.appendChild(stem);

            if (firstNoteType === "eighth" || firstNoteType === "16th") {
                this.drawStemFlag(stemX, stemEndY, stemDown, firstNoteType === "16th");
            }
        }
    }

    /**
     * Draw notehead tilted ellipse (from PHP rx=1.55, ry=0.92 rotated -15 degrees)
     */
    drawNotehead(cx, cy, color, isHollow) {
        const scale = this.zoom || 1.0;
        const rx = 1.55 * 5 * scale;
        const ry = 0.92 * 5 * scale;
        
        const ellipse = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        ellipse.setAttribute("cx", cx);
        ellipse.setAttribute("cy", cy);
        ellipse.setAttribute("rx", rx);
        ellipse.setAttribute("ry", ry);
        ellipse.setAttribute("transform", `rotate(-15 ${cx} ${cy})`);
        
        if (isHollow) {
            ellipse.setAttribute("fill", "#ffffff");
            ellipse.setAttribute("stroke", color);
            ellipse.setAttribute("stroke-width", `${2 * scale}`);
        } else {
            ellipse.setAttribute("fill", color);
        }
        this.svg.appendChild(ellipse);
    }

    /**
     * Draw ledger lines
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
        ledger.setAttribute("x1", x - 10 * scale);
        ledger.setAttribute("y1", lineY);
        ledger.setAttribute("x2", x + 10 * scale);
        ledger.setAttribute("y2", lineY);
        ledger.setAttribute("stroke", "#0f172a");
        ledger.setAttribute("stroke-width", `${1 * scale}`);
        this.svg.appendChild(ledger);
    }

    /**
     * Draw note stem flags using PHP vector curves (SheetMusicTrait line 24/35)
     */
    drawStemFlag(x, y, isDown, isDouble) {
        const scale = this.zoom || 1.0;
        const flagPathUp = "M -0.112 3.631 C -0.112 0 -0.3031 0 0 0 C 0.28 0.1911 0 0 0 0 C 0.42 0.6879 0.512 0.7834 0.531 0.898 C 1.4 2.8 1.4 2.8 2.327 4.051 C 4.028 5.943 4.525 7.071 4.525 8.581 C 4.506 9.994 3.263 13.014 2.996 12.899 C 3.378 11.829 3.913 10.682 4.047 9.727 C 4.219 8.561 3.741 6.879 1.831 5.16 C 0.779 4.294 0 4.2 -0.112 3.631 Z";
        const flagPathDown = "M -0.112 -3.631 C -0.112 0 -0.3031 0 0 0 C 0.28 -0.1911 0 0 0 0 C 0.42 -0.6879 0.512 -0.7834 0.531 -0.898 C 1.4 -2.8 1.4 -2.8 2.327 -4.051 C 4.028 -5.943 4.525 -7.071 4.525 -8.581 C 4.506 -9.994 3.263 -13.014 2.996 -12.899 C 3.378 -11.829 3.913 -10.682 4.047 -9.727 C 4.219 -8.561 3.741 -6.879 1.831 -5.16 C 0.779 -4.294 0 -4.2 -0.112 -3.631 Z";
        
        const path = isDown ? flagPathDown : flagPathUp;
        const sx = 0.40 * 5 * scale;
        const sy = 0.32 * 5 * scale;
        
        // Draw first flag
        const p1 = document.createElementNS("http://www.w3.org/2000/svg", "path");
        p1.setAttribute("d", path);
        p1.setAttribute("transform", `translate(${x}, ${y}) scale(${sx}, ${sy})`);
        p1.setAttribute("fill", "#0f172a");
        this.svg.appendChild(p1);
        
        if (isDouble) {
            // Draw second flag with 1.7mm vertical offset (1.7 * 5 = 8.5px)
            const yOffset = (isDown ? -1.7 : 1.7) * 5 * scale;
            const p2 = document.createElementNS("http://www.w3.org/2000/svg", "path");
            p2.setAttribute("d", path);
            p2.setAttribute("transform", `translate(${x}, ${y + yOffset}) scale(${sx}, ${sy})`);
            p2.setAttribute("fill", "#0f172a");
            this.svg.appendChild(p2);
        }
    }

    /**
     * Draw a rest symbol using PHP vector shapes (SheetMusicSVG line 691)
     */
    drawRestSymbol(x, y, type) {
        const scale = this.zoom || 1.0;
        
        switch (type) {
            case 'whole':
                // Hangs below the 4th line (D5, which is y + 2.0 in mm)
                const rWhole = document.createElementNS("http://www.w3.org/2000/svg", "rect");
                rWhole.setAttribute("x", x - 10 * scale);
                rWhole.setAttribute("y", y + 10 * scale);
                rWhole.setAttribute("width", 20 * scale);
                rWhole.setAttribute("height", 6 * scale);
                rWhole.setAttribute("fill", "#64748b");
                this.svg.appendChild(rWhole);
                break;
                
            case 'half':
                // Sits on top of the 3rd line (B4, which is y + 2.8 in mm)
                const rHalf = document.createElementNS("http://www.w3.org/2000/svg", "rect");
                rHalf.setAttribute("x", x - 10 * scale);
                rHalf.setAttribute("y", y + 14 * scale);
                rHalf.setAttribute("width", 20 * scale);
                rHalf.setAttribute("height", 6 * scale);
                rHalf.setAttribute("fill", "#64748b");
                this.svg.appendChild(rHalf);
                break;
                
            case 'quarter':
                const qPath = "M349 372c-14-12-44-43-65-102-21-58 25-95 50-114q12-7-1-21L219 9c-13-17-30-7-20 7 120 171-35 197-35 197s17 44 97 115c-84-22-139 40-97 104 41 64 120 78 127 80s18-4 7-11c-26-17-79-61-54-93 34-42 84-23 97-17 22 11 31-1 8-19";
                const pQ = document.createElementNS("http://www.w3.org/2000/svg", "path");
                pQ.setAttribute("d", qPath);
                pQ.setAttribute("transform", `translate(${x - 8 * scale}, ${y + 5 * scale}) scale(${0.06 * scale})`);
                pQ.setAttribute("fill", "#64748b");
                this.svg.appendChild(pQ);
                break;
                
            case 'eighth':
            case '16th':
            case '32nd':
                const hookPath = "M 1.098 0 C 0.578 0.098 0.18 0.457 0 0.953 C -0.039 1.113 -0.039 1.152 -0.039 1.371 C -0.039 1.672 -0.02 1.832 0.121 2.07 C 0.32 2.469 0.738 2.789 1.215 2.906 C 1.715 3.047 3 3.153 4 2.153 L 4.941 0.598 C 4.844 0.477 4.645 0.438 4.523 0.535 C 4.484 0.574 4.422 0.656 4.383 0.715 C 4.203 1.016 3.746 1.551 3.508 1.75 C 3.289 1.93 3.168 1.949 2.969 1.871 C 2.789 1.773 2.73 1.672 2.609 1.133 C 2.492 0.598 2.352 0.355 2.051 0.156 C 1.773 -0.023 1.414 -0.082 1.098 0 z";
                const hookScale = 2.75 * scale;
                
                // Draw diagonal stem line for rest
                const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                line.setAttribute("x1", x + 15 * scale);
                line.setAttribute("y1", y + 10 * scale);
                line.setAttribute("x2", x + 10 * scale);
                line.setAttribute("y2", y + 40.5 * scale);
                line.setAttribute("stroke", "#64748b");
                line.setAttribute("stroke-width", `${1.0 * scale}`);
                this.svg.appendChild(line);
                
                // Hook 1
                const pH1 = document.createElementNS("http://www.w3.org/2000/svg", "path");
                pH1.setAttribute("d", hookPath);
                pH1.setAttribute("transform", `translate(${x + 1.5 * scale}, ${y + 11 * scale}) scale(${hookScale})`);
                pH1.setAttribute("fill", "#64748b");
                this.svg.appendChild(pH1);
                
                if (type === '16th' || type === '32nd') {
                    // Hook 2
                    const pH2 = document.createElementNS("http://www.w3.org/2000/svg", "path");
                    pH2.setAttribute("d", hookPath);
                    pH2.setAttribute("transform", `translate(${x - 0.5 * scale}, ${y + 21 * scale}) scale(${hookScale})`);
                    pH2.setAttribute("fill", "#64748b");
                    this.svg.appendChild(pH2);
                }
                if (type === '32nd') {
                    // Hook 3
                    const pH3 = document.createElementNS("http://www.w3.org/2000/svg", "path");
                    pH3.setAttribute("d", hookPath);
                    pH3.setAttribute("transform", `translate(${x - 2.5 * scale}, ${y + 31 * scale}) scale(${hookScale})`);
                    pH3.setAttribute("fill", "#64748b");
                    this.svg.appendChild(pH3);
                }
                break;
        }
    }

    getDiatonicIndex(step, octave) {
        const offset = this.stepOffsets[step] || 0;
        return (octave - 4) * 7 + offset;
    }

    getNoteY(diatonic, startY, clefType) {
        if (clefType === "F") {
            return startY + (-2 - diatonic) * (this.lineSpacing / 2);
        } else {
            return startY + (10 - diatonic) * (this.lineSpacing / 2);
        }
    }

    drawText(x, y, text, size, color, anchor = "middle", isBold = false) {
        const txt = document.createElementNS("http://www.w3.org/2000/svg", "text");
        txt.setAttribute("x", x);
        txt.setAttribute("y", y);
        txt.setAttribute("font-size", size);
        txt.setAttribute("fill", color);
        txt.setAttribute("text-anchor", anchor === "center" ? "middle" : anchor);
        txt.setAttribute("font-family", "Arial, sans-serif");
        if (isBold) {
            txt.setAttribute("font-weight", "bold");
        }
        txt.textContent = text;
        this.svg.appendChild(txt);
    }
}

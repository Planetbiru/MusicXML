import sys

with open('D:/MagicServer/www/MusicXML/example/midi-to-musicxml.js', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix empty measure double rest bug and tie logic.
# The user wants empty measure rest logic to mirror PHP.
# We will disable useRestFilling for measures that have notes, or fix the fill logic.

old_code = '''                // Fill end of measure with rests if needed
                if (opts.useRestFilling && lastNoteXmlEnd < xmlMeasureLength) {
                    const remainingDivs = xmlMeasureLength - lastNoteXmlEnd;
                    const restPieces = this.splitIntoRepresentableDurations(remainingDivs, divisions);
                    restPieces.forEach(restDivs => {'''

new_code = '''                // Fill end of measure with rests if needed
                if (opts.useRestFilling && lastNoteXmlEnd < xmlMeasureLength) {
                    const isBlank = notesInMeasure.length === 0 && Object.keys(tieContinue).length === 0;
                    if (isBlank) {
                        // Just generate a single whole rest for an entirely blank measure
                        xmlPartsContent += this.generateNoteXML({
                            isChord: false,
                            isRest: true,
                            ch,
                            partId,
                            noteCode: 0,
                            durationDivs: xmlMeasureLength,
                            divisions,
                            dynamics: 0,
                            tieType: null,
                            lyricText: null,
                            beamType: null
                        });
                    } else {
                        const remainingDivs = xmlMeasureLength - lastNoteXmlEnd;
                        const restPieces = this.splitIntoRepresentableDurations(remainingDivs, divisions);
                        restPieces.forEach(restDivs => {'''

content = content.replace(old_code, new_code + ' ' * 28)

# Close the else block properly
content = content.replace('''                            });
                        });
                    }''', '''                            });
                        });
                    }
                }''')

with open('D:/MagicServer/www/MusicXML/example/midi-to-musicxml.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("Patched")

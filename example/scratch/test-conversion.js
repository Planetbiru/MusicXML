const fs = require('fs');
const path = require('path');

// Set global MidiParser since midi-to-musicxml.js relies on it globally
global.MidiParser = require('../midi-parser.js');
const MidiToMusicXML = require('../midi-to-musicxml.js');

const midiPath = path.join(__dirname, '../example.mid');
const outputPath = path.join(__dirname, '../output/example-js.xml');

try {
    console.log("Reading MIDI file from:", midiPath);
    const midiBuffer = fs.readFileSync(midiPath);
    
    console.log("Converting MIDI to MusicXML via JS...");
    const xmlContent = MidiToMusicXML.convert(midiBuffer, {
        title: "Example Song (JavaScript Conversion)",
        creator: "Planetbiru MusicXML JS"
    });
    
    const outputDir = path.dirname(outputPath);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }
    
    fs.writeFileSync(outputPath, xmlContent, 'utf-8');
    console.log(`Success! MusicXML saved to ${outputPath}`);
    
    // Check if the XML is non-empty and well-formed at a basic level
    if (xmlContent.includes("<score-partwise") && xmlContent.includes("</score-partwise>")) {
        console.log("Basic XML structure validation passed.");
    } else {
        console.error("Warning: Root score-partwise tags not found in output!");
    }
} catch (e) {
    console.error("Conversion test failed:", e);
    process.exit(1);
}

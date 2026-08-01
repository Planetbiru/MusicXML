const fs = require('fs');
const MidiParser = require('./MidiParser.js');
const MidiToMusicXML = require('./midi-to-musicxml.js');

const buffer = fs.readFileSync('example.mid');
const parsed = MidiParser.parse(buffer.buffer, { normalize: true });
const xml = MidiToMusicXML.convertParsed(parsed, { divisions: 4, useRestFilling: true });

fs.writeFileSync('output.xml', xml);
console.log('XML generated');

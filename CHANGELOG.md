# Changelog

## v1.1.0 - 2023-10-27

### Added
- **MusicXML to MIDI Conversion Feature**: Implemented the initial functionality to convert MusicXML object models back into binary MIDI files. This allows for a roundtrip conversion process (MIDI -> MusicXML -> MIDI).

### Known Issues
- This feature is still under development.
- There are known issues with note duration accuracy during the conversion from MusicXML back to MIDI.
- Some notes may be missing in the final MIDI output.
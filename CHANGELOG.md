# Changelog

## v1.1.1 - 2026-07-16

### Fixed
- Fixed docblock for the `MusicXMLFromMidi::midiToScorePartwiseObject()` function.
- Fixed logic for handling `<tie>` and `<notations><tied>` elements in the `MusicXMLToMidi::processPart()` function for more accurate MusicXML to MIDI conversion.
- Refactored note placement in `MusicXMLFromMidi::addMeasureElement` to use absolute positioning. This fixes rhythmic shifts by removing the sequential cursor (`$xmlCursor`) and ensuring each note's start time is accurately preserved based on its absolute time from the MIDI.

## v1.1.0 - 2026-07-15

### Added
- **MusicXML to MIDI Conversion Feature**: Implemented the initial functionality to convert MusicXML object models back into binary MIDI files. This allows for a roundtrip conversion process (MIDI -> MusicXML -> MIDI).

### Known Issues
- This feature is still under development.
- There is a known issue where some drum kit instruments may be swapped during conversion.
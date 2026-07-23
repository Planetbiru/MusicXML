# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.3.0 - 2026-07-24

### Added
- **Dotted Note Rendering**: The rendering engine now correctly draws augmentation dots next to noteheads for dotted rhythms (e.g., dotted eighth notes).
- **Comprehensive Beaming System**: Implemented a full beaming system for notes shorter than a quarter note. This feature is optional and can be enabled via the `drawBeam` constructor parameter.

### Changed
- **Advanced Beaming Logic**: The new beaming system follows standard music notation rules:
    - Beams are now automatically grouped by beat.
    - Beams will not cross beat boundaries, correctly breaking for rests or notes in subsequent beats.
    - Complex rhythmic figures (e.g., a dotted eighth followed by a sixteenth) are now correctly beamed within a single beat.
    - Beams are slanted based on the pitch contour of the first and last notes in the group, rather than being strictly horizontal.
    - Secondary beams and hooks for shorter notes now follow the angle of the primary beam.

### Fixed
- Resolved incorrect beaming where notes from different beats were improperly connected.
- Fixed rendering of complex rhythms that span across beat subdivisions, ensuring they are beamed correctly according to standard notation practice.

## v1.2.3 - 2026-07-23

### Added
- Parameter `$year` to define the **Year** in the score

### Changed
- Remove parameter `$singlePage` from method `MusicConverter::dawProjectToPDF()`

### Fixed
- Calculation of the **x** coordinate for the first note after a *tie stop*, taking into account the duration of the tied note
- Calculation of the **x** coordinate for the first note after a *rest*, taking into account the rest duration
- Replaced direct null comparison on `ModelMap::CLASS_MAP[$childName]` with `array_key_exists()` + null check to prevent *Undefined index* notices in PHP 5 while maintaining compatibility with PHP 7 and 8.
- Updated `MusicXMLInstrument::INSTRUMENT_LIST[$instrumentId]` access to use `array_key_exists()` + null check, ensuring safe retrieval across PHP 5, 7, and 8 without triggering notices when the key is missing.

This change in version 1.2.3 is not intended to guarantee that the x‑position aligns with the duration of the preceding note, since each lyric syllable must be displayed without overlapping other syllables. The same principle applies to notes. However, it does help reduce irregularities caused by rests and overflow notes from the previous measure.

## v1.2.2 - 2026-07-20

### Added
- **Mobile-Optimized SVG Rendering**: Introduced a new `$mobile` boolean parameter to all SVG conversion methods (`midiToSVG`, `musicXMLToSVG`, `mxlToSVG`, `dawProjectToSVG`).

### Changed
- When the `$mobile` parameter is set to `true`:
    - The SVG layout width is reduced from a standard A4-based width (~210mm) to a mobile-friendly A5-based width (~105mm).
    - The score is rendered with **one measure per system** (staff line) to ensure readability on narrow screens. This provides a clear, vertically scrolling experience.
- This feature is fully backward-compatible. The `$mobile` parameter defaults to `false`, so existing applications using older versions of the library will not be affected and will continue to render SVGs with the standard multi-measure layout.
- **Improved Measure Layout**: The space allocated for measures has been widened by reducing the initial indent for clefs and signatures. This provides more horizontal room for notes, which is especially beneficial in the mobile-optimized layout.

## v1.2.1 - 2026-07-18

### Changed
- Updated PHPDoc blocks and inline comments for better code documentation and clarity.

## v1.2.0 - 2026-07-17

### Added
- **MIDI to .dawproject Conversion**: Added functionality to convert standard MIDI files into the `.dawproject` format, compatible with DAWs like Bitwig Studio.
- **.dawproject to MIDI Conversion**: Implemented the reverse conversion, allowing `.dawproject` files to be converted back into standard MIDI files.
- **Roundtrip Conversion Capability**: The new features enable a full roundtrip conversion (`MIDI` -> `.dawproject` -> `MIDI`), preserving track structure and instrument information.

### Changed
- Use the MIDI track name as the part name in MusicXML if available. If not available, use the instrument name.

## v1.1.1 - 2026-07-16

### Changed
- Updated and completed the instrument and drum kit mappings for more accurate sound representation.

### Fixed
- Fixed docblock for the `MusicXMLFromMIDI::midiToScorePartwiseObject()` function.
- Fixed logic for handling `<tie>` and `<notations><tied>` elements in the `MusicXMLToMIDI::processPart()` function for more accurate MusicXML to MIDI conversion.
- Refactored note placement in `MusicXMLFromMIDI::addMeasureElement` to use absolute positioning. This fixes rhythmic shifts by removing the sequential cursor (`$xmlCursor`) and ensuring each note's start time is accurately preserved based on its absolute time from the MIDI.
- Resolved the drum kit instrument swapping issue from v1.1.0 through improved instrument mapping and note processing logic.

## v1.1.0 - 2026-07-15

### Added
- **MusicXML to MIDI Conversion Feature**: Implemented the initial functionality to convert MusicXML object models back into binary MIDI files. This allows for a roundtrip conversion process (MIDI -> MusicXML -> MIDI).

### Known Issues
- This feature is still under development.
- There is a known issue where some drum kit instruments may be swapped during conversion.
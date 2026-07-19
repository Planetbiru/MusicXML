# Planetbiru MusicXML Converter

[![Latest Stable Version](https://img.shields.io/packagist/v/planetbiru/musicxml.svg)](https://packagist.org/packages/planetbiru/musicxml)
[![License](https://img.shields.io/packagist/l/planetbiru/musicxml.svg)](https://opensource.org/licenses/MIT)

A comprehensive PHP library for handling MusicXML, MIDI, and DAWProject files. It supports conversion between these formats and can render musical scores into beautiful, printable sheet music in PDF or SVG formats. This library is written in pure PHP and has minimal dependencies, making it easy to integrate into any project.
This library is actively used in production and powers the online sheet music generator at [https://composer.planetbiru.com/](https://composer.planetbiru.com/).

## Key Features

*   **MIDI to MusicXML:** Core functionality to parse MIDI data and convert it into a standard MusicXML structure.
*   **MusicXML to MIDI:** Convert MusicXML object models back into binary MIDI files, enabling a full roundtrip conversion process.
    *   This roundtrip capability is powerful for building interactive **MusicXML players**. The workflow is as follows:
        1.  The MusicXML is converted to a MIDI file for audio playback.
        2.  The same MusicXML is converted to an SVG for visual rendering, allowing for synchronized highlighting of notes and lyrics as the audio plays.
*   **MIDI to DAWProject Conversion:** Added functionality to convert standard MIDI files into the `.dawproject` format, compatible with DAWs like Bitwig Studio.
*   **DAWProject to MIDI Conversion:** Implemented the reverse conversion, allowing `.dawproject` files to be converted back into standard MIDI files.
*   **Roundtrip Conversion Capability:** The new features enable a full roundtrip conversion (`MIDI` -> `.dawproject` -> `MIDI`), preserving track structure and instrument information.
*   **SVG Rendering:** Create scalable SVG vector graphics of your sheet music from MusicXML data, perfect for web display.
    *   **Interactive:** The generated SVG includes `data-*` attributes for easy synchronization with an audio player, enabling features like note highlighting and a moving playhead.
    *   **Page-by-Page Preview:** In addition to a single, continuous scrolling view, the SVG can also be rendered in a multi-page layout, similar to a PDF. This is useful for previewing how the score will look when printed. This behavior is controlled by a parameter in the conversion function.
    *   **Mobile-Optimized Layout:** A `$mobile` flag renders the score with one measure per line on a narrower canvas, perfect for vertical scrolling on mobile devices.
*   **PDF Rendering:** Generate high-quality PDF sheet music from MusicXML data using a built-in FPDF-based rendering engine. No external binaries required.
*   **Track & Channel Filtering:** Easily select a specific track or channel from a MIDI file to render.
*   **Automatic Part Detection:** Intelligently detects the most suitable part to render (e.g., the main melody with lyrics).
*   **Rich Notation Support:** Handles notes, rests, chords, ties, time signatures, key signatures, clefs, and tempo markings.
*   **Lyric Support:** Automatically detects and renders lyrics embedded in MIDI files.
*   **Percussion & Drums:** Special handling for drum tracks (Channel 10) with appropriate notation.
*   **Batch Processing:** Includes examples for processing all tracks of a MIDI file into a single ZIP archive of PDFs.

## Conversion Workflows

The library supports the following conversion workflows:

![Conversion Compatibility](conversion-compatibility.svg)

* **DAWProject → MIDI:** Parse DAWProject files and reconstruct MIDI tracks/events, including tempo, notes, and program changes.
* **MIDI → DAWProject:** Convert MIDI data into DAWProject format (ZIP with project.xml and metadata.xml), preserving tempo, tracks, notes, and instrument mapping for DAW interoperability.
* **MIDI → MusicXML:** Parse MIDI data and convert it into a standard MusicXML structure.
* **MusicXML → MIDI:** Convert MusicXML object models back into binary MIDI files.
* **MusicXML → PDF:** Render MusicXML data into high-quality, printable PDF sheet music.
* **MusicXML → SVG:** Render MusicXML data into scalable SVG, perfect for interactive web display.


## Format Comparison and Data Loss

When converting between MIDI, MusicXML, and DAWProject, it's important to understand that each format was designed for a different purpose. As a result, some information can be lost in translation.

### Feature Comparison Matrix

| Feature                   | MIDI (.mid)                               | MusicXML (.xml, .mxl)                     | DAWProject (.dawproject)                  |
|---------------------------|-------------------------------------------|-------------------------------------------|-------------------------------------------|
| **Primary Purpose**       | Performance Data                          | Digital Sheet Music                       | DAW Session Interchange                   |
| **Note Data**             | Yes (Pitch, Velocity, Timing)             | Yes (with rich notation)                  | Yes (within clips)                        |
| **Sheet Music Notation**  | No (Inferred by converter)                | **Yes (Primary feature)**                 | No (Inferred by converter)                |
| **Lyrics**                | Yes (as timed meta-events)                | Yes (syllable-based, well-structured)     | No (Not supported in the spec)            |
| **Continuous Controllers**| Yes (Pitch Bend, Modulation, etc.)        | Limited (often simplified or lost)        | Yes (as automation curves)                |
| **Track Structure**       | Yes                                       | Yes (as "Parts")                          | Yes (Audio, Instrument, etc.)             |
| **Audio Clips**           | No                                        | No                                        | Yes (References external files)           |
| **Plugin/VST State**      | No                                        | No                                        | Yes (Can store plugin info)               |

### Conversion Data Loss

Understanding what is lost during conversion is key to choosing the right workflow for your needs.

#### **MIDI → MusicXML**
This is a "performance-to-notation" conversion. The goal is to create readable sheet music from raw performance data.
*   **What is LOST:**
    *   **Continuous Performance Data:** Nuanced pitch bends, modulation wheel curves, and other continuous controller (CC) data are generally lost or heavily simplified.
    *   **Precise Timing:** Unquantized, "human" timing is snapped to a rhythmic grid (quantized). This can alter the feel of the original performance.
    *   **Panning:** Pan (CC10) information is typically discarded.
    *   **Complex Dynamics:** The interplay between note velocity, volume (CC7), and expression (CC11) is flattened into a single dynamic marking (e.g., *mf*, *p*).

#### **MusicXML → MIDI**
This is a "notation-to-performance" conversion. The goal is to create an audible representation of the sheet music.
*   **What is LOST:**
    *   **Visual Notation:** All visual-only information is lost. This includes slurs, specific beam groupings, page layout, and textual annotations that don't have a direct playback meaning.
    *   **Semantic Meaning:** The distinction between enharmonically equivalent notes (e.g., F# vs. Gb) is lost. The MIDI file only knows the pitch, not its theoretical context.
    *   **Note Velocity:** The final note velocity is an interpretation of the MusicXML dynamic markings, not a restoration of the original performance velocity.

#### **MIDI → DAWProject**
This conversion focuses on preserving core musical data like notes, tracks, and tempo for interoperability between DAWs.
*   **What is LOST:**
    *   **Lyrics:** The `.dawproject` format does not have a standard way to represent lyrics, so they are lost during this conversion.
    *   **Other Meta-Events:** Some non-standard or less common MIDI meta-events may not be translated.

#### **DAWProject → MIDI**
This conversion extracts musical event data from a DAW session file.
*   **What is LOST:**
    *   **DAW-Specific Information:** All data related to audio clips, VST/plugin states, routing, and scene structure is lost. The conversion only extracts the MIDI-related information from instrument clips.

#### **Conclusion: The Best Format for the Job**

*   **Use MIDI** as the source when you have raw performance data from a keyboard or sequencer and want to generate sheet music or a DAW project from it.
*   **Use MusicXML** when your primary goal is to create, edit, or share digital sheet music with accurate and detailed notation. It is the best format for exchanging scores between notation software (Sibelius, Finale, MuseScore).
*   **Use DAWProject** for exchanging a project's structure—including MIDI clips, tempo, and track information—between compatible DAWs (like Bitwig Studio and Cubase). It is not a format for detailed notation.

Due to these differences, a round-trip conversion like `MIDI → MusicXML → MIDI` will **not** produce a file identical to the original. The resulting MIDI will be a quantized, simplified performance based on the generated sheet music, not a perfect copy of the original performance.



## MusicXML 4.0 Compliance

The object model in this library, primarily located within the `MusicXML\Model` namespace, is designed to accurately reflect the official MusicXML 4.0 specification. Each PHP class, such as `Accidental`, `Note`, `MeasurePartwise`, and others, corresponds directly to an element defined in the MusicXML standard.

This 1:1 mapping approach ensures that the generated XML output is well-structured, valid, and broadly compatible with various music notation software that supports MusicXML (such as Sibelius, Finale, MuseScore, etc.). When you work with the PHP objects in this library, you are essentially building a MusicXML document programmatically.

For a complete and detailed reference of all elements, their attributes, and expected values, please refer to the official element reference from W3C MusicXML 4.0. For instance, the `Accidental` class in this library corresponds to the `<accidental>` element described in the documentation. You can find a complete list of all elements at:

*   **MusicXML 4.0 Element Reference**

As a specific example, the `<accidental-text>` element can be found in this documentation.


## Installation

Install the library via Composer:

```bash
composer require planetbiru/musicxml
```

## Usage

The `MusicConverter` class is the main entry point and the easiest way to use this library. Think of it as the primary "conversion engine" that does all the heavy lifting for you.

When you provide a MIDI file, `MusicConverter` automatically performs the following steps behind the scenes:

1.  **Reads & Analyzes MIDI:** It understands all the data from the MIDI file, such as notes, tempo, instruments, and lyrics.
2.  **Converts to MusicXML:** It translates that MIDI data into the MusicXML format, which is the standard "language" for digital music notation.
3.  **Renders to Visuals:** It draws the MusicXML structure into your desired visual format, either a print-ready PDF or an interactive SVG for the web.

You don't need to worry about the technical details of each step; you just need to call a single function.

### Constructor

`new MusicConverter($compressEmptyMeasures, $showTempoChanges, $useRestFilling, $lyricFontSize, $systemHeight)`

Initializes the converter with optional rendering settings.

- `$compressEmptyMeasures` (bool): If `true`, consecutive empty measures will be collapsed into a single multi-measure rest. Default is `false`.
- `$showTempoChanges` (bool): If `true`, tempo change markings (e.g., "Tempo: = 120") will be displayed on the score. Default is `true`.
- `$useRestFilling` (bool): If `true`, uses an alternative algorithm for filling gaps with rests. This can affect how rests are displayed in measures with complex rhythms. Default is `false`.
- `$lyricFontSize` (float): The font size for lyrics, in points. Default is `6.0`.
- `$systemHeight` (int): The vertical height of a single staff system in millimeters, including space for lyrics. Default is `28`.


### Core Conversion Methods

Here are the main public methods available in the `MusicConverter` class:

#### `midiToMusicXML($midiData, $songTitle, $version, $format)`

Converts MIDI data into a MusicXML string.
- `$midiData` (string): The binary content of a MIDI file.
- `$songTitle` (string): The title for the musical work. Defaults to "Untitled".
- `$version` (string): The MusicXML version to use. Defaults to "4.0".
- `$format` (string): The output format, either 'xml' (uncompressed) or 'mxl' (compressed). Defaults to "musicxml".

#### `midiToDAWProject($midiData)`

Converts MIDI data into a `.dawproject` file format. This method takes binary MIDI data and converts it into a ZIP archive containing `project.xml` and `metadata.xml`, which is compatible with DAWs like Bitwig Studio.
- `$midiData` (string): The binary content of the MIDI file.

#### `dawProjectToMIDI($dawProjectData)`

Converts a `.dawproject` file back into MIDI data. This method reads a `.dawproject` ZIP archive, parses its contents, and reconstructs the corresponding binary MIDI data.
- `$dawProjectData` (string): The binary content of the `.dawproject` file.

#### `dawProjectToPDF($dawProjectData, $songTitle, $composer, $targetChannelOrPartId, $singlePage)`

Converts a `.dawproject` file into a PDF file. This method first converts the `.dawproject` data into an intermediate MIDI format, and then renders that MIDI data to a PDF.
- `$dawProjectData` (string): The binary content of the `.dawproject` file.
- `$songTitle` (string): The title to be displayed on the sheet music.
- `$composer` (string): The composer's name to be displayed.
- `$targetChannelOrPartId` (int|string|null): The specific MIDI channel (1-16) or part ID to render.

#### `musicXMLToMIDI($musicXmlContent)`

Converts a MusicXML string into a binary MIDI data string.
- `$musicXmlContent` (string): The MusicXML content as a string.

#### `midiToPDF($midiData, $songTitle, $composer, $targetChannelOrPartId, $mainMelody)`

Renders MIDI data directly into a PDF sheet music string.
- `$midiData` (string): The binary content of a MIDI file.
- `$songTitle` (string): The title to be displayed on the sheet music.
- `$composer` (string): The composer's name to be displayed.
- `$targetChannelOrPartId` (int|string|null): The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
- `$mainMelody` (int): The MIDI channel number (1-16) considered to be the main melody, used to prioritize lyric display. Defaults to 3.

#### `musicXMLToPDF($xmlStr, $songTitle, $composer, $targetChannelOrPartId, $showLyric)`

Renders MusicXML data into a PDF sheet music string.
- `$xmlStr` (string): The string content of a MusicXML file.
- `$songTitle` (string): The title to be displayed on the sheet music.
- `$composer` (string): The composer's name to be displayed.
- `$targetChannelOrPartId` (int|string|null): The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
- `$showLyric` (bool): If true, forces lyrics to be displayed if they exist in the selected part.

#### `midiToSVG($midiData, $songTitle, $composer, $targetChannelOrPartId, $mainMelody, $singlePage)`
#### `midiToSVG($midiData, $songTitle, $composer, $targetChannelOrPartId, $mainMelody, $singlePage, $mobile)`

Renders MIDI data directly into an interactive SVG image string.
- `$midiData` (string): The binary content of a MIDI file.
- `$songTitle` (string): The title to be displayed on the sheet music.
- `$composer` (string): The composer's name to be displayed.
- `$targetChannelOrPartId` (int|string|null): The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
- `$mainMelody` (int): The MIDI channel number (1-16) considered to be the main melody. Defaults to 3.
- `$singlePage` (bool): If true (default), generates a single continuous SVG. If false, generates stacked, page-like layouts within one SVG.
- `$mobile` (bool): If `true`, optimizes the layout for mobile devices by rendering one measure per system. Defaults to `false`.

#### `musicXMLToSVG($xmlStr, $songTitle, $composer, $targetChannelOrPartId, $showLyric, $singlePage, $mobile)`

Renders MusicXML data into an interactive SVG image string.
- `$xmlStr` (string): The string content of a MusicXML file.
- `$songTitle` (string): The title to be displayed on the sheet music.
- `$composer` (string): The composer's name to be displayed.
- `$targetChannelOrPartId` (int|string|null): The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
- `$showLyric` (bool): If true, forces lyrics to be displayed if they exist in the selected part.
- `$singlePage` (bool): If true (default), generates a single continuous SVG. If false, generates stacked, page-like layouts.
- `$mobile` (bool): If `true`, optimizes the layout for mobile devices by rendering one measure per system. Defaults to `false`.


#### `dawProjectToSVG($dawProjectData, $songTitle, $composer, $targetChannelOrPartId, $singlePage, $mobile)`

Converts a `.dawproject` file into an SVG image. This method first converts the `.dawproject` data into an intermediate MIDI format, and then renders that MIDI data to an SVG.
- `$dawProjectData` (string): The binary content of the `.dawproject` file.
- `$songTitle` (string): The title to be displayed on the sheet music.
- `$composer` (string): The composer's name to be displayed.
- `$targetChannelOrPartId` (int|string|null): The specific MIDI channel (1-16) or part ID to render.
- `$singlePage` (bool): If true, generates a single continuous SVG. If false, generates stacked pages.
- `$mobile` (bool): If `true`, optimizes the layout for mobile devices by rendering one measure per system. Defaults to `false`.


## Examples

### Basic Example: Convert MIDI to PDF

This example demonstrates the simplest use case: converting a MIDI file into a PDF.

```php
<?php
require 'vendor/autoload.php';

use MusicXML\MusicConverter;

// 1. Load your MIDI file content
$midiData = file_get_contents('path/to/your/song.mid');

// 2. Instantiate the converter
$converter = new MusicConverter();

// 3. Convert to PDF
// The converter will automatically detect the best track to render.
$pdfContent = $converter->midiToPDF($midiData, "My Awesome Song", "The Composer");

// 4. Save the PDF file
file_put_contents('output/my-song.pdf', $pdfContent);

echo "PDF generated successfully!";
```

### Advanced Examples

#### Convert to SVG

The process is nearly identical for generating an SVG. You can also control whether the output is a single continuous page or a multi-page layout.

```php
use MusicXML\MusicConverter;

$converter = new MusicConverter();

// Generate a single, continuous SVG (default)
$singlePageSvg = $converter->midiToSVG($midiData, "My Song SVG", "The Composer", null, 3, true);
file_put_contents('output/my-song-single-page.svg', $singlePageSvg);

// Generate a multi-page SVG to preview the printed layout
$multiPageSvg = $converter->midiToSVG($midiData, "My Song SVG", "The Composer", null, 3, false);
file_put_contents('output/my-song-multi-page.svg', $multiPageSvg);
```

#### Select a Specific Track/Channel

You can specify a MIDI channel number (1-16) to render a specific instrument part.

```php
use MusicXML\MusicConverter;

$converter = new MusicConverter();

// Render only track 4 (e.g., the piano part)
$pdfContent = $converter->midiToPDF($midiData, "My Song", "The Composer", 4);
file_put_contents('output/piano-part.pdf', $pdfContent);
```

#### Batch Convert All Tracks to a ZIP

The provided `example/run-examples.php` file contains a complete implementation for iterating through all tracks in a MIDI file, converting each one to a separate PDF, and packaging them into a single ZIP archive. This is useful for creating individual instrumental parts from a full arrangement.

### Building a DAWProject Player

You can leverage the library's roundtrip capabilities to build a fully interactive player for `.dawproject` files, complete with synchronized sheet music and audio playback. This is ideal for creating custom DAW environments or analysis tools on the web.

The workflow involves two parallel conversion paths:

1.  **Audio Generation (`.dawproject` → `MIDI`):**
    *   Use `dawProjectToMIDI()` to convert the `.dawproject` file into binary MIDI data.
    *   This MIDI data can then be loaded into a web-based player like MIDI.js for audio playback.

2.  **Visual Score Generation (`.dawproject` → `MIDI` → `MusicXML` → `SVG`):**
    *   First, convert the `.dawproject` file to MIDI using `dawProjectToMIDI()`.
    *   Next, convert the resulting MIDI data to MusicXML using `midiToMusicXML()`.
    *   Finally, render the MusicXML into an interactive SVG using `musicXMLToSVG()`.

By running these two processes, you get both the audio and the visual score from a single `.dawproject` source. The generated SVG will contain the necessary `data-tick` attributes to synchronize the note highlighting and playhead with the audio from the MIDI player, just like in the `vocal-training.php` example.

This powerful workflow allows you to create rich, interactive experiences directly from a modern DAW project format.

> **Note on Lyrics**: The `.dawproject` format does not natively support lyric events. Therefore, any lyrics present in an original MIDI file will be lost during the `MIDI` → `.dawproject` conversion and will not appear in the final generated score.


### Interactive SVG with Player Synchronization

A powerful feature of this library is its ability to generate SVGs ready for synchronization with an audio player (like a MIDI player). Each note, rest, and measure element in the SVG is tagged with `data-start-tick` and `data-end-tick` attributes.

This metadata allows you to build interactive web applications where:

*   **Note Highlighting:** As the music plays, you can use JavaScript to find the note element corresponding to the current playback time (tick) and apply a CSS class to highlight it.
*   **Moving Playhead:** You can create a vertical line (playhead) and animate its position across the musical staff based on the current tick, providing a clear visual guide for the user.

This makes the library ideal for creating:
*   Karaoke-style lyric and note displays.
*   Interactive music learning tools and applications.
*   Sheet music viewers with synchronized audio playback.

### Standalone Example: `vocal-training.php`

The library includes a powerful, self-contained example in `d:\MagicServer\www\MusicXML\example\vocal-training.php`. This file demonstrates how to build a complete interactive sheet music player for the web.

**How It Works:**

1.  **Backend (PHP):**
    *   It loads a MIDI file (`example.mid`).
    *   It uses `MidiFilter` to get a list of all tracks and to apply transposition.
    *   It uses `MusicConverter` to render the selected MIDI track into an interactive SVG.
    *   The final MIDI data (for playback) and the SVG data (for display) are passed to the frontend.

2.  **Frontend (JavaScript):**
    *   **MIDI Playback:** It uses the MIDI.js library (loaded via its CDN) to play the MIDI audio directly in the browser.
    *   **Score Synchronization:** The core logic is in `vocal-training.js`. It uses the `MIDIjs.player_callback` function, which fires approximately 10 times per second during playback.
    *   **Real-time Updates:** On each callback, the script:
        *   Calculates the current position (tick) in the music.
        *   Highlights the currently playing note(s) by adding a `.active` CSS class to the corresponding SVG elements.
        *   Moves a red vertical line (`#playhead-line`) across the staff to show the exact playback position.
        *   Automatically scrolls the score down as the music progresses to keep the current system in view.
    *   **Interactive Controls:** The example includes fully functional controls for:
        *   **Play / Pause / Resume:** Simulates pause/resume since MIDI.js lacks a native function.
        *   **Seeking:** The user can drag the timeline slider to any point in the song. Playback will resume from the selected position.
        *   **Track Selection & Transposition:** The page can be reloaded to show a different track or apply a different transposition.
        *   **Audio-Visual Sync Compensation:** A slider allows the user to adjust the visual offset to compensate for any audio latency, ensuring the playhead and note highlights are perfectly synchronized with the sound.


### Notes on MusicXML to MIDI Conversion

While a MIDI file converted from MusicXML is nearly identical to the original MIDI before it was converted to MusicXML, there are several important points to consider regarding data loss in a `MIDI -> MusicXML -> MIDI` round trip:

1.  **Controller Data**: The MusicXML generated by this library does not store continuous controller data like pitch bend, modulation, or detailed CC7 (Volume) and CC11 (Expression) curves. When converted back to MIDI, this nuanced performance data is lost.
2.  **Note Velocity**: MusicXML note dynamics are calculated from a combination of the original MIDI velocity, volume (CC7), and expression (CC11). This means the velocity of a note in the final MIDI file is a computed value and will not be the same as the original velocity.
3.  **Panning**: Since pan information is not stored in the generated MusicXML, the resulting audio from the converted MIDI file will be mono.
4.  **Quantization**: Note onsets that do not fall precisely on standard rhythmic grid lines (e.g., 1, 1/2, 1/4, 1/8, 1/16, 1/32) are quantized. This can cause slight timing shifts compared to the original performance.
5.  **Drum Note Durations**: Note durations on the percussion channel (Channel 10) are also quantized.

Due to these factors, the MusicXML format as used here is not intended for perfect preservation of an original musical performance. It is best used as an intermediate format for tasks such as:

-   Generating sheet music.
-   Creating visualizations for vocal training or analysis.
-   Exchanging project data with a DAW that does not support direct MIDI import but does support MusicXML.


## Dependencies

*   PHP >= 5.6
*   PHP `xml` extension (usually enabled by default)
*   PHP `zip` extension (for handling `.mxl` and `.dawproject` files)
*   `fpdf/fpdf`: Used by the PDF rendering engine (installed automatically via Composer).

## License

This library is open-source software licensed under the MIT license.

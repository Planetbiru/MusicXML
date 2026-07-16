# Planetbiru MusicXML Converter

[![Latest Stable Version](https://img.shields.io/packagist/v/planetbiru/musicxml.svg)](https://packagist.org/packages/planetbiru/musicxml)
[![License](https://img.shields.io/packagist/l/planetbiru/musicxml.svg)](https://opensource.org/licenses/MIT)

A comprehensive PHP library for converting MIDI files into MusicXML, and rendering them as beautiful, printable sheet music in PDF or SVG formats. This library is written in pure PHP and has minimal dependencies, making it easy to integrate into any project.

## Key Features

*   **MIDI to MusicXML:** Core functionality to parse MIDI data and convert it into a standard MusicXML structure.
*   **PDF Rendering:** Generate high-quality PDF sheet music using a built-in FPDF-based rendering engine. No external binaries required.
*   **SVG Rendering:** Create scalable SVG vector graphics of your sheet music, perfect for web display.
    *   **Interactive:** The generated SVG includes `data-*` attributes for easy synchronization with an audio player, enabling features like note highlighting and a moving playhead.
*   **Track & Channel Filtering:** Easily select a specific track or channel from a MIDI file to render.
*   **Automatic Part Detection:** Intelligently detects the most suitable part to render (e.g., the main melody with lyrics).
*   **Rich Notation Support:** Handles notes, rests, chords, ties, time signatures, key signatures, clefs, and tempo markings.
*   **Lyric Support:** Automatically detects and renders lyrics embedded in MIDI files.
*   **Percussion & Drums:** Special handling for drum tracks (Channel 10) with appropriate notation.
*   **Batch Processing:** Includes examples for processing all tracks of a MIDI file into a single ZIP archive of PDFs.

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

The process is nearly identical for generating an SVG.

```php
use MusicXML\MusicConverter;

$converter = new MusicConverter();
$svgContent = $converter->midiToSVG($midiData, "My Song SVG", "The Composer");
file_put_contents('output/my-song.svg', $svgContent);
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

## Dependencies

*   PHP >= 5.6
*   fpdf/fpdf: Used by the PDF rendering engine.

## License

This library is open-source software licensed under the MIT license.

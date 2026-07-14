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

The library is designed to be straightforward. The main entry point is the `MusicXML\MusicConverter` class.

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
$pdfContent = $converter->midiToPdf($midiData, "My Awesome Song", "The Composer");

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
$svgContent = $converter->midiToSvg($midiData, "My Song SVG", "The Composer");
file_put_contents('output/my-song.svg', $svgContent);
```

#### Select a Specific Track/Channel

You can specify a MIDI channel number (1-16) to render a specific instrument part.

```php
use MusicXML\MusicConverter;

$converter = new MusicConverter();

// Render only channel 4 (e.g., the piano part)
$pdfContent = $converter->midiToPdf($midiData, "Piano Part", "The Composer", 4);
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
*   Interactive music learning tools.
*   Sheet music viewers with synchronized audio playback.

## Dependencies

*   PHP >= 5.6
*   fpdf/fpdf: Used by the PDF rendering engine.

## License

This library is open-source software licensed under the MIT license.

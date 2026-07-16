<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use DAWProject\DAWProjectFromMIDI;
use DAWProject\DAWProjectToMIDI;

/**
 * This script tests the roundtrip conversion from MIDI to .dawproject and back to MIDI.
 *
 * 1. It reads an input MIDI file.
 * 2. Converts the MIDI data to the .dawproject format.
 * 3. Saves the intermediate .dawproject file.
 * 4. Converts the .dawproject data back to MIDI format.
 * 5. Saves the final MIDI file.
 *
 * The resulting `roundtrip-final.mid` can be compared with the original `example.mid`
 * to verify the conversion process.
 */

echo "Starting DAWProject roundtrip conversion test...\n";

// Define file paths
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
    echo "Created output directory: " . $outputDir . "\n";
}

$inputMidiPath = __DIR__ . '/example.mid';
$outputDawProjectPath = $outputDir . '/roundtrip.dawproject';
$outputMidiPath = $outputDir . '/roundtrip-final.mid';

// 1. Load input MIDI file
$midiData = file_get_contents($inputMidiPath);
echo "Loaded input MIDI: " . $inputMidiPath . "\n";

// 2. Convert MIDI to .dawproject
$fromMidiConverter = new DAWProjectFromMIDI();
$dawProjectData = $fromMidiConverter->convert($midiData, 'DAWProject Roundtrip Test');
file_put_contents($outputDawProjectPath, $dawProjectData);
echo "Generated .dawproject file: " . $outputDawProjectPath . "\n";

// 3. Convert .dawproject back to MIDI
$toMidiConverter = new DAWProjectToMIDI();
$finalMidiData = $toMidiConverter->convert($dawProjectData);
file_put_contents($outputMidiPath, $finalMidiData);
echo "Generated final MIDI file: " . $outputMidiPath . "\n";

echo "Roundtrip conversion complete.\n";
<?php

// Sertakan autoloader Composer
require __DIR__ . '/../vendor/autoload.php';

use Midi\MidiFilter;

// Definisikan path file dan direktori
$midiInputFile = __DIR__ . '/example.mid';
$outputDir = __DIR__ . '/output';

// Buat direktori output jika belum ada
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Muat konten file MIDI
$midiData = file_get_contents($midiInputFile);
if ($midiData === false) {
    die("Gagal membaca file MIDI: " . $midiInputFile);
}

echo "Memulai pengujian MidiFilter...\n\n";

// Buat instance filter
$midiFilter = new MidiFilter();

// ========================================================================
// 1. Uji transposeAndMute()
// ========================================================================
echo "1. Menguji transposeAndMute()...\n";
try {
    $semitonesToTranspose = -12; // Transposisi naik 2 seminada (satu nada penuh)
    $channelsToMute = array(1, 2, 3); // Matikan (mute) channel 1, 2, dan 3

    echo "   - Transposisi: +{$semitonesToTranspose} seminada.\n";
    echo "   - Mematikan channel: " . implode(', ', $channelsToMute) . ".\n";

    $transposedMidiData = $midiFilter->transposeAndMute($midiData, $semitonesToTranspose, $channelsToMute);

    $outputFile = $outputDir . '/example-transposed-muted.mid';
    file_put_contents($outputFile, $transposedMidiData);
    echo "   -> Berhasil! File disimpan di: " . realpath($outputFile) . "\n\n";

} catch (Exception $e) {
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}


// ========================================================================
// 2. Uji filterByGetTrack()
// ========================================================================
echo "2. Menguji filterByGetTrack()...\n";
try {
    $targetTrackIndex = 4; // Pertahankan hanya track dengan indeks 4 (melodi vokal)

    echo "   - Menyaring untuk mempertahankan hanya track indeks {$targetTrackIndex}.\n";

    $filteredMidiData = $midiFilter->filterByGetTrack($midiData, $targetTrackIndex);

    $outputFile = $outputDir . '/example-filtered-track-4.mid';
    file_put_contents($outputFile, $filteredMidiData);
    echo "   -> Berhasil! MIDI yang telah disaring disimpan di: " . realpath($outputFile) . "\n\n";

} catch (Exception $e) {
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}

echo "Semua pengujian selesai.\n";
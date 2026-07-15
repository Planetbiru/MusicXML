<?php

// Sertakan autoloader Composer
require __DIR__ . '/../vendor/autoload.php';

// Gunakan kelas Midi
use Midi\Midi;

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

echo "Memulai tes I/O MIDI (Baca -> Parse -> Tulis)...\n\n";

try {
    // ========================================================================
    // Langkah 1: Buat instance kelas Midi dan parse data MIDI
    // ========================================================================
    echo "1. Mem-parsing data MIDI ke struktur internal...\n";
    $midi = new Midi();
    $midi->parseMidi($midiData);
    echo "   -> Berhasil! Jumlah track: " . $midi->getTrackCount() . "\n\n";

    // ========================================================================
    // Langkah 2: Generate kembali data biner MIDI dari struktur internal
    // ========================================================================
    echo "2. Menulis kembali data biner MIDI dari struktur internal...\n";
    $newMidiData = $midi->getMid();
    echo "   -> Berhasil! Ukuran data baru: " . strlen($newMidiData) . " bytes.\n\n";

    // Simpan file MIDI hasil konversi
    $outputFile = $outputDir . '/test-midi-io.mid';
    file_put_contents($outputFile, $newMidiData);
    echo "File MIDI hasil tes I/O disimpan di: " . realpath($outputFile) . "\n";
    echo "Silakan coba putar file ini untuk memverifikasi integritasnya.\n";

} catch (Exception $e) {
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}

echo "\nProses selesai.\n";
<?php

// Sertakan autoloader Composer
require __DIR__ . '/../vendor/autoload.php';

// Gunakan kelas-kelas yang diperlukan
use MusicXML\MusicXMLFromMidi;
use MusicXML\MusicXMLToMidi;
use MusicXML\Util\MXL;

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

echo "Memulai proses konversi bolak-balik (MIDI -> MusicXML -> MIDI)...\n\n";

try {
    // ========================================================================
    // Langkah 1: Konversi MIDI ke string MusicXML
    // Ini mensimulasikan sumber data MusicXML Anda yang bisa berasal dari mana saja.
    // ========================================================================
    echo "1. Mengonversi MIDI ke string MusicXML...\n";
    $converter = new MusicXMLFromMidi();
    $midi = $converter->loadMidiString($midiData);
    
    $musicXmlContent = $converter->midiToMusicXml($midi, "Example Song (All Tracks)", "4.0", MXL::FORMAT_XML);
    echo "   -> Berhasil!\n\n";

    // ========================================================================
    // Langkah 2: Konversi string MusicXML kembali ke data MIDI
    // ========================================================================
    echo "2. Mengonversi string MusicXML kembali ke data MIDI...\n";
    $toMidiConverter = new MusicXMLToMidi();
    $newMidiData = $toMidiConverter->fromXmlString($musicXmlContent);
    echo "   -> Berhasil!\n\n";

    // Simpan file MIDI hasil konversi
    $outputFile = $outputDir . '/example-roundtrip.mid';
    file_put_contents($outputFile, $newMidiData);
    echo "File MIDI hasil konversi bolak-balik disimpan di: " . realpath($outputFile) . "\n";

} catch (Exception $e) {
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}

echo "\nProses selesai.\n";
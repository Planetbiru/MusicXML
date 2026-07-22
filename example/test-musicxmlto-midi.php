<?php

// Gunakan kelas-kelas yang diperlukan
use MusicXML\MusicXMLToMIDI;

// Sertakan autoloader Composer
require __DIR__ . '/../vendor/autoload.php';



// Definisikan path file dan direktori
$midiInputFile = __DIR__ . '/example.mid';
$outputDir = __DIR__ . '/output';

try
{
    $musicXmlContent = file_get_contents(__DIR__ . '/example.musicxml');
    
    echo "Mengonversi string MusicXML ke data MIDI...\n";
    $toMidiConverter = new MusicXMLToMIDI();
    $newMidiData = $toMidiConverter->fromXmlString($musicXmlContent);
    echo "   -> Berhasil!\n\n";

    // Simpan file MIDI hasil konversi
    $outputFile = $outputDir . '/example-roundtrip.mid';
    file_put_contents($outputFile, $newMidiData);
    echo "File MIDI hasil konversi bolak-balik disimpan di: " . realpath($outputFile) . "\n";
}
catch(Exception $e)
{
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}
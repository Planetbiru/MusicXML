<?php

// Sertakan autoloader Composer
require __DIR__ . '/../vendor/autoload.php';
// Gunakan kelas-kelas yang diperlukan dari library
use Midi\MidiFilter;
use MusicXML\MusicConverter;
use MusicXML\MusicXMLFromMidi; // Masih dibutuhkan untuk skenario 1
use MusicXML\Util\MXL; // Masih dibutuhkan untuk skenario 1

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

echo "Memulai proses konversi...\n\n";

// ========================================================================
// 1. MIDI to MusicXML -> semua track
// ========================================================================
echo "1. Mengonversi MIDI ke MusicXML (semua track)...\n";
try {
    $converter = new MusicXMLFromMidi();
    $midi = $converter->loadMidiString($midiData);
    
    // Konversi ke format XML
    $musicXmlContent = $converter->midiToMusicXml($midi, "Example Song (All Tracks)", "4.0", MXL::FORMAT_XML);
    
    $outputFile = $outputDir . '/example-all-tracks.xml';
    file_put_contents($outputFile, $musicXmlContent);
    echo "   -> Berhasil! File disimpan di: " . realpath($outputFile) . "\n\n";
} catch (Exception $e) {
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}


// ========================================================================
// 2. MIDI to PDF -> track 4 dan channel 4
// ========================================================================
echo "2. Mengonversi MIDI ke PDF (hanya track 4)...\n";
try {
    // Gunakan MusicConverter untuk proses yang lebih sederhana
    $musicConverter = new MusicConverter();
    // Parameter ke-4 adalah targetChannelOrPartId. Kita gunakan channel 4.
    $pdfContent = $musicConverter->midiToPDF($midiData, "Example Song (Track 4)", "Planetbiru", 4);
    $outputFile = $outputDir . '/example-track-4.pdf';
    
    file_put_contents($outputFile, $pdfContent);
    echo "   -> Berhasil! File disimpan di: " . realpath($outputFile) . "\n\n";
} catch (Exception $e) {
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}


// ========================================================================
// 3. MIDI to SVG -> track 4 dan channel 4
// ========================================================================
echo "3. Mengonversi MIDI ke SVG (hanya track 4)...\n";
try {
    // Gunakan MusicConverter untuk proses yang lebih sederhana
    $musicConverter = new MusicConverter();
    // Parameter ke-4 adalah targetChannelOrPartId. Kita gunakan channel 4.
    // Parameter terakhir (true) untuk menghasilkan SVG satu halaman (single page).
    $svgContent = $musicConverter->midiToSVG($midiData, "Example Song (Track 4)", "Planetbiru", 4, 3, true);
    $outputFile = $outputDir . '/example-track-4.svg';
    
    file_put_contents($outputFile, $svgContent);
    echo "   -> Berhasil! File disimpan di: " . realpath($outputFile) . "\n\n";
} catch (Exception $e) {
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}


// ========================================================================
// 4. MIDI to PDF -> semua track, 1 track satu file, main melody di channel 4. Masukkan ke ZIP
// ========================================================================
echo "4. Mengonversi setiap track MIDI ke PDF terpisah dan memasukkannya ke dalam ZIP...\n";
try {
    $zipFile = $outputDir . '/all-tracks-as-pdf.zip';
    $zip = new ZipArchive();

    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Tidak dapat membuat file ZIP.");
    }

    // Dapatkan informasi semua track dari MIDI asli
    $midiFilter = new MidiFilter();
    $midiFilter->loadMidi($midiData);
    $allTracksInfo = $midiFilter->getAllTracks();

    // Buat instance MusicConverter di luar loop untuk efisiensi
    $musicConverter = new MusicConverter();

    foreach ($allTracksInfo as $trackInfo) {
        $trackIndex = $trackInfo['index'];
        $trackName = preg_replace('/[^a-zA-Z0-9\s]/', '', $trackInfo['name']); // Bersihkan nama file

        // Lewati track 0 (indeks 0) karena biasanya hanya berisi meta-event (tempo, dll) dan tidak ada not.
        if ($trackIndex == 0) {
            continue;
        }

        $trackName = empty($trackName) ? "Track " . ($trackIndex + 1) : $trackName;
        
        echo "   - Memproses " . $trackName . " (Track " . ($trackIndex + 1) . ")...\n";

        // Filter MIDI untuk track saat ini menggunakan MidiFilter
        // Ini masih diperlukan karena kita ingin memproses setiap track secara terpisah.
        $filteredMidiData = $midiFilter->filterByGetTrack($midiData, $trackIndex);

        // Jika track yang difilter kosong (misal, hanya meta-track tanpa not), lewati.
        if (strlen($filteredMidiData) < 22) { // Ukuran minimal header MIDI
            continue;
        }

        // Gunakan MusicConverter untuk merender MIDI yang sudah difilter ke PDF
        // Kita tidak perlu menentukan target channel lagi karena MIDI sudah difilter.
        $pdfContent = $musicConverter->midiToPDF($filteredMidiData, $trackName, "Planetbiru", null, 4);

        // Tambahkan file PDF ke ZIP
        $zip->addFromString(sprintf('%03d - %s.pdf', $trackIndex, $trackName), $pdfContent);
    }

    $zip->close();
    echo "   -> Berhasil! File ZIP disimpan di: " . realpath($zipFile) . "\n\n";

} catch (Exception $e) {
    echo "   -> Gagal: " . $e->getMessage() . "\n\n";
}

echo "Semua proses selesai.\n";

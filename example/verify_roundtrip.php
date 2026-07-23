<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Midi\Midi;

$midi = new Midi();
$midi->importMidiFile('output/example-roundtrip.mid');
$track4 = $midi->getTrack(4); // Or maybe track 3 if it's 0-indexed. Let's print all tracks.
$tracks = $midi->getTracks();

foreach ($tracks as $idx => $track) {
    echo "Track $idx:\n";
    $count = 0;
    foreach ($track as $msg) {
        $msgArr = explode(' ', $msg);
        if ($msgArr[1] == 'On' && $msgArr[3] == 'v=0') {
             // Note off disguised as Note on v=0
             if ($idx == 4) echo "  $msg\n";
        } elseif ($msgArr[1] == 'On' || $msgArr[1] == 'Off') {
             if ($idx == 4) echo "  $msg\n";
        }
        $count++;
    }
}

<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MusicXML\Model\ScorePartwise;

$xmlString = file_get_contents(__DIR__ . '/output/example-all-tracks.xml');
$dom = new \DOMDocument();
$dom->loadXML($xmlString);

$score = new ScorePartwise($dom->documentElement);
$parts = $score->parts; // Not part, but parts? Actually $score->part array?
foreach ($score->part as $part) {
    if ($part->id == 'P4') {
        foreach ($part->measure as $mIdx => $measure) {
            foreach ($measure->note as $note) {
                if (isset($note->tie)) {
                    echo "Found tie in measure " . ($mIdx+1) . "\n";
                    var_dump($note->tie);
                }
            }
        }
    }
}

<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MusicXML\MusicXMLFromMIDI;

$midiPath = __DIR__ . '/example.mid';

$converter = new MusicXMLFromMIDI();
$midi = $converter->loadMidiFile($midiPath);

// We need to access private properties. Let's use Reflection.
$converter->midiToMusicXML($midi, "Title");

$reflector = new ReflectionObject($converter);
$trackNamesProp = $reflector->getProperty('trackNames');
$trackNamesProp->setAccessible(true);
$trackNames = $trackNamesProp->getValue($converter);

$partListProp = $reflector->getProperty('partList');
$partListProp->setAccessible(true);
$partList = $partListProp->getValue($converter);

echo "Track Names:\n";
print_r($trackNames);

echo "\nPart List:\n";
print_r($partList);

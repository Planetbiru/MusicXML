<?php

namespace DAWProject;

use Exception;
use SimpleXMLElement;
use ZipArchive;

/**
 * Converts a .dawproject file back into MIDI data.
 *
 * This class reads a .dawproject (ZIP archive), parses the `project.xml` within it,
 * and reconstructs a standard MIDI file from the track, clip, and note information.
 * It effectively reverses the process of the DAWProjectFromMIDI class.
 *
 * @author Kamshory
 */
class DAWProjectToMIDI
{
    /**
     * Converts a .dawproject file content into a binary MIDI data string.
     *
     * @param string $dawProjectData The binary content of the .dawproject file.
     * @return string The binary content of the generated MIDI file.
     * @throws Exception If the .dawproject file is invalid or cannot be processed.
     */
    public function convert($dawProjectData)
    {
        // 1. Unzip the .dawproject data in memory
        $tempFile = tempnam(sys_get_temp_dir(), 'daw_');
        file_put_contents($tempFile, $dawProjectData);

        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);
            throw new Exception("Failed to open .dawproject archive.");
        }

        $projectXmlContent = $zip->getFromName('project.xml');
        $zip->close();
        @unlink($tempFile);

        if ($projectXmlContent === false) {
            throw new Exception("Invalid .dawproject: project.xml not found.");
        }

        // 2. Parse project.xml
        $xml = new SimpleXMLElement($projectXmlContent);
        $xml->registerXPathNamespace('daw', 'http://www.bitwig.com/dawproject');

        $timebase = 480; // Standard MIDI timebase (PPQN)

        // 3. Create MIDI Header (MThd)
        // Format 1 (multiple tracks), track count, timebase
        $trackCount = count($xml->xpath('//daw:Structure/daw:Track')) + 1; // +1 for tempo track
        $midiData = "MThd";
        $midiData .= pack('N', 6); // Header length
        $midiData .= pack('n', 1); // Format 1
        $midiData .= pack('n', $trackCount);
        $midiData .= pack('n', $timebase);

        // 4. Create Tempo Track (Track 0)
        $bpm = (float)($xml->Transport->Tempo['value'] ?? 120.0);
        $microsecondsPerQuarterNote = (int)(60000000 / $bpm);

        $tempoTrack = '';
        // Add song title as track name for track 0
        $songTitle = (string)($xml->xpath('//daw:MetaData/daw:Title')[0] ?? 'Untitled');
        $tempoTrack .= "\x00\xFF\x03" . chr(mb_strlen($songTitle, '8bit')) . $songTitle;

        // Tempo meta event requires a 3-byte value for microseconds per quarter note.
        $tempoTrack .= "\x00\xFF\x51\x03" . substr(pack('N', $microsecondsPerQuarterNote), 1, 3);
        // End of track meta event
        $tempoTrack .= "\x00\xFF\x2F\x00";
        $midiData .= "MTrk" . pack('N', strlen($tempoTrack)) . $tempoTrack;

        // Helper to convert beats to ticks
        $beatsToTicks = function ($beats) use ($timebase) {
            return (int)round($beats * $timebase);
        };

        // 5. Create Instrument Tracks
        $tracks = $xml->xpath('//daw:Structure/daw:Track');
        $arrangementLanes = $xml->xpath('//daw:Arrangement/daw:Lanes/daw:Clips');

        foreach ($tracks as $trackNode) {
            $trackId = (string)$trackNode['id'];
            $trackName = (string)$trackNode['name'];
            $trackEvents = array();

            // Find clips for this track
            foreach ($arrangementLanes as $clipsNode) {
                if ((string)$clipsNode['track'] !== $trackId) {
                    continue;
                }

                foreach ($clipsNode->Clip as $clipNode) {
                    $clipStartTimeBeats = (float)$clipNode['time'];

                    foreach ($clipNode->Notes->Note as $noteNode) {
                        $key = (int)$noteNode['key'];
                        $velocity = (int)round((float)$noteNode['vel'] * 127);
                        $channel = (int)$noteNode['channel'];

                        $startTimeBeats = $clipStartTimeBeats + (float)$noteNode['time'];
                        $durationBeats = (float)$noteNode['duration'];
                        $endTimeBeats = $startTimeBeats + $durationBeats;

                        $startTick = $beatsToTicks($startTimeBeats);
                        $endTick = $beatsToTicks($endTimeBeats);

                        // Note On event: 0x90 | channel, key, velocity
                        $trackEvents[$startTick][] = pack('C3', 0x90 | ($channel - 1), $key, $velocity);
                        // Note Off event: 0x80 | channel, key, velocity (0)
                        $trackEvents[$endTick][] = pack('C3', 0x80 | ($channel - 1), $key, 0);
                    }
                }
            }

            if (empty($trackEvents)) {
                continue;
            }

            // Sort events by tick time
            ksort($trackEvents, SORT_NUMERIC);

            $trackContent = "";
            $lastTick = 0;

            // Add track name meta event
            $trackContent .= "\x00\xFF\x03" . chr(mb_strlen($trackName, '8bit')) . $trackName;

            foreach ($trackEvents as $tick => $events) {
                $deltaTime = $tick - $lastTick;
                foreach ($events as $event) {
                    $trackContent .= $this->writeVariableLength($deltaTime) . $event;
                    $deltaTime = 0; // Subsequent events at the same tick have a delta time of 0
                }
                $lastTick = $tick;
            }

            // Add end of track event with a delta-time of 0, as it should follow the last event.
            // The last event's delta-time already accounts for the duration.
            $trackContent .= $this->writeVariableLength(0) . "\xFF\x2F\x00";

            $midiData .= "MTrk" . pack('N', strlen($trackContent)) . $trackContent;
        }

        return $midiData; // Move return statement outside the loop
    }

    /**
     * Encodes an integer into the MIDI variable-length quantity format.
     *
     * @param int $value The integer to encode.
     * @return string The encoded byte string.
     */
    private function writeVariableLength($value)
    {
        $buffer = $value & 0x7F;

        while ($value >>= 7) {
            $buffer = ($buffer << 8) | (($value & 0x7F) | 0x80);
        }

        $result = '';
        while (true) {
            $result .= chr($buffer & 0xFF);
            if ($buffer & 0x80) {
                $buffer >>= 8;
            } else {
                return $result;
            }
        }
    }
}

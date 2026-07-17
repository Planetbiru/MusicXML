<?php

namespace DAWProject;

use Midi\Midi;
use Midi\MidiMeasure;
use SimpleXMLElement;
use ZipArchive;

class DAWProjectFromMIDI
{
    /**
     * Map of CC Volume events per channel
     * @var array
     */
    private $ccVolumeMap = array();

    /**
     * Map of CC Expression events per channel
     * @var array
     */
    private $ccExpressionMap = array();

    /**
     * Map of CC Pan events per channel
     * @var array
     */
    private $ccPanMap = array();

    /**
     * Map of Pitch Bend events per channel
     * @var array
     */
    private $pitchBendMap = array();

    /**
     * Map of CC Modulation (CC1) events per channel
     * @var array
     */
    private $ccModulationMap = array();

    /**
     * Map of CC Sustain Pedal (CC64) events per channel
     * @var array
     */
    private $ccSustainMap = array();

    /**
     * Map of CC Reverb (CC91) events per channel
     * @var array
     */
    private $ccReverbMap = array();

    /**
     * Map of CC Chorus (CC93) events per channel
     * @var array
     */
    private $ccChorusMap = array();

    private $timebase = 512;

    /**
     * Convert ticks (MIDI) to time DAWProject
     * 
     * @param int $ticks MIDI tick
     * @return float DAWProject time (in beats)
     */
    public function ticksToBeats($ticks) {
        return $ticks / $this->timebase;
    }


    /**
     * Convert MIDI string to DAWProject ZIP content
     *
     * @param string $midiData
     * @param string $songTitle
     * @param array $selectedChannels array of channels to include (0-based)
     * @return string ZIP binary content
     */
    public function convert($midiData, $songTitle = "Untitled", $selectedChannels = null)
    {
        $this->ccVolumeMap = array();
        $this->ccExpressionMap = array();
        $this->ccPanMap = array();
        $this->pitchBendMap = array();
        $this->ccModulationMap = array();
        $this->ccSustainMap = array();
        $this->ccReverbMap = array();
        $this->ccChorusMap = array();


        // Parse MIDI data
        $midi = new MidiMeasure();
        $midi->parseMidi($midiData);
        $timebase = $midi->getTimebase();
        $this->timebase = $timebase;

        // 1. Create project.xml
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Project xmlns="http://www.bitwig.com/dawproject" version="1.0"></Project>');
        $xml->addChild('Application')->addAttribute('name', 'Planetbiru/MusicXML');
        $xml->children()->Application->addAttribute('version', '1.0');


        // Go through MIDI tracks
        $tracks = $midi->getTracks();
        $trackCount = count($tracks);

        // Add Transport with compact Tempo Automation
        $transport = $xml->addChild('Transport');
        $tempoPoints = array();

        // Tempo events are usually in the first track
        if (isset($tracks[0])) {
            foreach ($tracks[0] as $evtLine) {
                $parts = explode(' ', trim($evtLine));
                if (count($parts) >= 3 && $parts[1] === 'Tempo') {
                    $tick = intval($parts[0]);
                    $tempoValue = intval($parts[2]); 
                    $bpm = 60000000 / $tempoValue;
                    $timeInBeats = $this->ticksToBeats($tick);
                    $tempoPoints[] = sprintf('%.4F,%.4F', $timeInBeats, $bpm);
                }
            }
        }

        // If no tempo events found, add a default one.
        if (empty($tempoPoints)) {
            $transport->addChild('Tempo')->addAttribute('value', '120.0');
        } else {
            // Set the first tempo as the main tempo and the rest as automation
            list(, $firstBpm) = explode(',', $tempoPoints[0]);
            $transport->addChild('Tempo')->addAttribute('value', (string)$firstBpm);
            $automation = $transport->addChild('Automation');
            $envelope = $automation->addChild('Envelope');
            $envelope->addAttribute('target', 'Tempo');
            $envelope->addAttribute('points', implode(';', $tempoPoints));
        }

        $structure = $xml->addChild('Structure');
        $arrangement = $xml->addChild('Arrangement');
        $arrangement->addAttribute('id', 'A1');
        $lanes = $arrangement->addChild('Lanes');

        $trackIdCounter = 1;
        for ($i = 0; $i < $trackCount; $i++) {
            $rawTrack = $tracks[$i];
            
            // Collect notes and track name
            $trackName = "Track " . $i;
            $notes = array();
            $programNumber = 0; // Default to 0 (Acoustic Grand Piano)
            $activeNotes = array(); // Reset active notes for each new track
            
            // Guess track channel
            $trackChannel = 0;

            foreach ($rawTrack as $evtLine) {
                $parts = explode(' ', trim($evtLine));
                if (count($parts) < 2) continue;
                
                $tick = intval($parts[0]);
                $type = $parts[1];

                if ($type === 'Meta' && isset($parts[2]) && $parts[2] === 'TrkName') {
                    $rawName = implode(' ', array_slice($parts, 3));
                    $trackName = trim($rawName, '" ');
                }

                if ($type === 'Par') {
                    $ch = 0;
                    $c = 0;
                    $v = 0;
                    foreach ($parts as $p) {
                        if (strpos($p, 'ch=') === 0) $ch = intval(substr($p, 3));
                        if (strpos($p, 'c=') === 0) $c = intval(substr($p, 2));
                        if (strpos($p, 'v=') === 0) $v = intval(substr($p, 2));
                    }
                    if ($c == 7) { // Volume
                        if (!isset($this->ccVolumeMap[$ch])) $this->ccVolumeMap[$ch] = array();
                        $this->ccVolumeMap[$ch][$tick] = $v;
                    }
                    if ($c == 11) { // Expression
                        if (!isset($this->ccExpressionMap[$ch])) $this->ccExpressionMap[$ch] = array();
                        $this->ccExpressionMap[$ch][$tick] = $v;
                    }
                    if ($c == 10) { // Pan
                        if (!isset($this->ccPanMap[$ch])) $this->ccPanMap[$ch] = array();
                        $this->ccPanMap[$ch][$tick] = $v;
                    }
                    if ($c == 1) { // Modulation
                        if (!isset($this->ccModulationMap[$ch])) $this->ccModulationMap[$ch] = array();
                        $this->ccModulationMap[$ch][$tick] = $v;
                    }
                    if ($c == 64) { // Sustain Pedal
                        if (!isset($this->ccSustainMap[$ch])) $this->ccSustainMap[$ch] = array();
                        $this->ccSustainMap[$ch][$tick] = $v;
                    }
                    if ($c == 91) { // Reverb
                        if (!isset($this->ccReverbMap[$ch])) $this->ccReverbMap[$ch] = array();
                        $this->ccReverbMap[$ch][$tick] = $v;
                    }
                    if ($c == 93) { // Chorus
                        if (!isset($this->ccChorusMap[$ch])) $this->ccChorusMap[$ch] = array();
                        $this->ccChorusMap[$ch][$tick] = $v;
                    }
                }

                // Find the Program Change event to determine the instrument
                if ($type === 'PrCh') {
                    foreach ($parts as $p) {
                        if (strpos($p, 'p=') === 0) {
                            $programNumber = intval(substr($p, 2));
                            break; // Found program number for this track
                        }
                    }
                }

                if ($type === 'Pb') {
                    $ch = 0;
                    $v = 0;
                    foreach ($parts as $p) {
                        if (strpos($p, 'ch=') === 0) $ch = intval(substr($p, 3));
                        if (strpos($p, 'v=') === 0) $v = intval(substr($p, 2));
                    }
                    if (!isset($this->pitchBendMap[$ch])) $this->pitchBendMap[$ch] = array();
                    $this->pitchBendMap[$ch][$tick] = $v;
                }

                if ($type === 'On' || $type === 'Off') {
                    $ch = 0;
                    $note = 0;
                    $vol = 0;
                    foreach ($parts as $p) {
                        
                        if (strpos($p, 'ch=') === 0) {
                            $ch = intval(substr($p, 3));
                        }
                        if (strpos($p, 'n=') === 0 || strpos($p, 'note=') === 0) {
                            $note = intval(substr($p, strpos($p, '=') + 1));
                        }
                        if (strpos($p, 'v=') === 0) $vol = intval(substr($p, 2));
                    }

                    $key = "$i-$ch-$note";
                    

                    $trackChannel = $ch;


                    if ($type === 'On') {
                        $activeNotes[$key] = array(
                            'tick' => $tick,
                            'velocity' => $vol / 127.0
                        );
                    } else {
                        if (isset($activeNotes[$key])) {
                            $startTick = $activeNotes[$key]['tick'];
                            $velocity = $activeNotes[$key]['velocity'];
                            unset($activeNotes[$key]);

                            $durationTicks = $tick - $startTick;
                            if ($durationTicks <= 0) $durationTicks = 1;

                            $notes[] = array(
                                'key' => $note,
                                'time' => $this->ticksToBeats($startTick),
                                'duration' => $this->ticksToBeats($durationTicks),
                                'velocity' => $velocity,
                                'channel' => $ch
                            );
                        }
                    }
                }
            }

            // Skip if track channel is not selected
            if ($selectedChannels !== null && !in_array($trackChannel, $selectedChannels)) {
                continue;
            }

            // Skip tracks with no notes
            if (empty($notes)) {
                continue;
            }

            $trackId = "T" . $trackIdCounter;
            $channelId = "C" . $trackIdCounter;
            $trackIdCounter++;

            // Add Track to Structure
            $trackEl = $structure->addChild('Track');
            $trackEl->addAttribute('name', $trackName);
            $trackEl->addAttribute('contentType', 'notes');
            $trackEl->addAttribute('id', $trackId);
            
            $channelEl = $trackEl->addChild('Channel');
            $channelEl->addAttribute('id', $channelId);

            $instrumentEl = $trackEl->addChild('Instrument');
            $instrumentName = (null != Midi::INSTRUMENT_LIST[$programNumber]) ? Midi::INSTRUMENT_LIST[$programNumber][0] : 'General MIDI';
            $instrumentEl->addAttribute('plugin', $instrumentName);
            $instrumentEl->addAttribute('program', $programNumber);

            // Add Automation for Pan, Volume, Expression
            $automationEl = $trackEl->addChild('Automation');
            $automationPoints = array(
                'Volume' => array(),    // Volume
                'Pan' => array(),       // Pan
                'CC11' => array(),      // Expression
                'PitchBend' => array(), // Picth Bend
                'CC1' => array(),       // Modulation
                'CC64' => array(),      // Sustain
                'CC91' => array(),      // Reverb
                'CC93' => array()       // Chorus
            );

            // Collect Volume points
            if (isset($this->ccVolumeMap[$trackChannel])) {
                foreach ($this->ccVolumeMap[$trackChannel] as $tick => $value) {
                    $timeInBeats = $this->ticksToBeats($tick);
                    $normalizedValue = $value / 127.0;
                    $automationPoints['Volume'][] = sprintf('%.4F,%.4F', $timeInBeats, $normalizedValue);
                }
            }

            // Collect Pan points
            if (isset($this->ccPanMap[$trackChannel])) {
                foreach ($this->ccPanMap[$trackChannel] as $tick => $value) {
                    $timeInBeats = $this->ticksToBeats($tick);
                    $normalizedValue = ($value - 64) / 63.0; // 0-127 -> -1.0 to 1.0 (approx)
                    $automationPoints['Pan'][] = sprintf('%.4F,%.4F', $timeInBeats, $normalizedValue);
                }
            }

            // Collect Expression points
            if (isset($this->ccExpressionMap[$trackChannel])) {
                foreach ($this->ccExpressionMap[$trackChannel] as $tick => $value) {
                    $timeInBeats = $this->ticksToBeats($tick);
                    $normalizedValue = $value / 127.0;
                    $automationPoints['CC11'][] = sprintf('%.4F,%.4F', $timeInBeats, $normalizedValue);
                }
            }

            // Collect Pitch Bend points
            if (isset($this->pitchBendMap[$trackChannel])) {
                foreach ($this->pitchBendMap[$trackChannel] as $tick => $value) {
                    $timeInBeats = $this->ticksToBeats($tick);
                    $normalizedValue = ($value - 8192) / 8191.0; // 0-16383 -> -1.0 to 1.0
                    $automationPoints['PitchBend'][] = sprintf('%.4F,%.4F', $timeInBeats, $normalizedValue);
                }
            }

            // Collect Modulation points
            if (isset($this->ccModulationMap[$trackChannel])) {
                foreach ($this->ccModulationMap[$trackChannel] as $tick => $value) {
                    $timeInBeats = $this->ticksToBeats($tick);
                    $normalizedValue = $value / 127.0;
                    $automationPoints['CC1'][] = sprintf('%.4F,%.4F', $timeInBeats, $normalizedValue);
                }
            }

            // Collect Sustain points
            if (isset($this->ccSustainMap[$trackChannel])) {
                foreach ($this->ccSustainMap[$trackChannel] as $tick => $value) {
                    $timeInBeats = $this->ticksToBeats($tick);
                    $normalizedValue = ($value >= 64) ? 1.0 : 0.0; // On/Off
                    $automationPoints['CC64'][] = sprintf('%.4F,%.4F', $timeInBeats, $normalizedValue);
                }
            }

            // Collect Reverb points
            if (isset($this->ccReverbMap[$trackChannel])) {
                foreach ($this->ccReverbMap[$trackChannel] as $tick => $value) {
                    $timeInBeats = $this->ticksToBeats($tick);
                    $normalizedValue = $value / 127.0;
                    $automationPoints['CC91'][] = sprintf('%.4F,%.4F', $timeInBeats, $normalizedValue);
                }
            }

            // Collect Chorus points
            if (isset($this->ccChorusMap[$trackChannel])) {
                foreach ($this->ccChorusMap[$trackChannel] as $tick => $value) {
                    $timeInBeats = $this->ticksToBeats($tick);
                    $normalizedValue = $value / 127.0;
                    $automationPoints['CC93'][] = sprintf('%.4F,%.4F', $timeInBeats, $normalizedValue);
                }
            }

            // Add Envelope to Automation
            foreach($automationPoints as $target => $points) {
                if(!empty($points)) {
                    $envelope = $automationEl->addChild('Envelope');
                    $envelope->addAttribute('target', $target);
                    $envelope->addAttribute('points', implode(';', $points));
                }
            }

            // Add Clips lane to Arrangement Lanes
            $clipsEl = $lanes->addChild('Clips');
            $clipsEl->addAttribute('track', $trackId);

            // Find min/max time to define clip boundaries
            $minTime = null;
            $maxTime = null;
            foreach ($notes as $n) {
                if ($minTime === null || $n['time'] < $minTime) $minTime = $n['time'];
                $endTime = $n['time'] + $n['duration'];
                if ($maxTime === null || $endTime > $maxTime) $maxTime = $endTime;
            }

            $clipEl = $clipsEl->addChild('Clip');
            $clipEl->addAttribute('name', $trackName);
            $clipEl->addAttribute('time', $minTime);
            $clipEl->addAttribute('duration', $maxTime - $minTime);

            $notesEl = $clipEl->addChild('Notes');
            
            foreach ($notes as $n) {
                // Time inside clip is relative to clip start
                $noteTimeInClip = $n['time'] - $minTime;
                
                $noteEl = $notesEl->addChild('Note');
                $noteEl->addAttribute('time', $noteTimeInClip);
                $noteEl->addAttribute('duration', $n['duration']);
                $noteEl->addAttribute('channel', $n['channel']);
                $noteEl->addAttribute('key', $n['key']);
                $noteEl->addAttribute('vel', round($n['velocity'], 4));
            }
        }

        // Format project.xml output
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        $projectXmlContent = $dom->saveXML();

        // 2. Create metadata.xml
        $metadataXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><MetaData xmlns="http://www.bitwig.com/dawproject" version="1.0"></MetaData>');
        $metadataXml->addChild('Title', htmlspecialchars($songTitle));
        
        $domMeta = dom_import_simplexml($metadataXml)->ownerDocument;
        $domMeta->formatOutput = true;
        $metadataXmlContent = $domMeta->saveXML();

        // 3. Create ZIP archive
        $tempFile = tempnam(sys_get_temp_dir(), 'dawproj');
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('project.xml', $projectXmlContent);
            $zip->addFromString('metadata.xml', $metadataXmlContent);
            $zip->close();
        }

        $zipData = file_get_contents($tempFile);
        @unlink($tempFile);

        return $zipData;
    }

    /**
     * Calculates the final note velocity by combining the initial velocity,
     * channel volume (CC 7), and channel expression (CC 11).
     *
     * @param int $velocity The initial note-on velocity (0-127).
     * @param int $volume The current channel volume (0-127), typically from CC 7.
     * @param int $expression The current channel expression (0-127), typically from CC 11.
     * @return float The final, normalized velocity (0.0 to 1.0).
     */
    public function getVelocity($velocity, $volume = 100, $expression = 127)
    {
        return ($velocity / 127) * ($volume / 127.0) * ($expression / 127.0);
    }

    /**
     * Get CC Volume value for a given channel and tick
     *
     * @param int $channelId The MIDI channel ID.
     * @param int $tick The absolute time in ticks.
     * @return int
     */
    public function getVolume($channelId, $tick)
    {
        if (!isset($this->ccVolumeMap[$channelId]) || empty($this->ccVolumeMap[$channelId])) {
            return 100; // Default volume if not set
        }
        $lastVal = 100;
        foreach ($this->ccVolumeMap[$channelId] as $eventTick => $val) {
            if ($eventTick <= $tick) {
                $lastVal = $val;
            } else {
                break;
            }
        }
        return $lastVal;
    }

    /**
     * Get CC Expression value for a given channel and tick
     *
     * @param int $channelId The MIDI channel ID.
     * @param int $tick The absolute time in ticks.
     * @return int
     */
    public function getExpression($channelId, $tick)
    {
        if (!isset($this->ccExpressionMap[$channelId]) || empty($this->ccExpressionMap[$channelId])) {
            return 127; // Default expression (max) if not set
        }
        $lastVal = 127;
        foreach ($this->ccExpressionMap[$channelId] as $eventTick => $val) {
            if ($eventTick <= $tick) {
                $lastVal = $val;
            } else {
                break;
            }
        }
        return $lastVal;
    }
}

<?php

namespace MusicXML;

use MusicXML\Model\ScorePartwise;
use MusicXML\Model\Note;
use Midi\Midi; // Assuming a MIDI writing class exists in this namespace

/**
 * Converts a MusicXML object model into a MIDI file.
 *
 * This class processes a ScorePartwise object, parsing its structure of parts,
 * measures, and notes, and translates them into corresponding MIDI tracks and events.
 * It handles notes, rests, tempo changes, time signatures, and program changes.
 * 
 * @author Kamshory
 */
class MusicXMLToMIDI
{
    /**
     * The MusicXML score object to be converted.
     *
     * @var ScorePartwise
     */
    private $score;

    /**
     * The MIDI file object being built.
     *
     * @var Midi
     */
    private $midi;

    /**
     * Ticks per quarter note for the MIDI file.
     *
     * @var int
     */
    private $timebase;

    /**
     * Maps MusicXML part IDs to MIDI track and channel info.
     *
     * @var array
     */
    private $partMap = array();

    /**
     * Converts a MusicXML string into a binary MIDI string.
     * This serves as a convenient entry point when the source is a string.
     *
     * @param string $xmlString The MusicXML content as a string.
     * @param int $timebase The desired timebase (ticks per quarter note) for the MIDI file.
     * @return string The binary MIDI data.
     */
    public function fromXmlString($xmlString, $timebase = 480)
    {
        $dom = new \DOMDocument();
        // Suppress warnings for malformed XML and handle them internally if needed.
        libxml_use_internal_errors(true);
        $dom->loadXML($xmlString);
        libxml_clear_errors();

        $score = new ScorePartwise($dom->documentElement);
        return $this->toMidi($score, $timebase);
    }

    /**
     * Converts a ScorePartwise object into a binary MIDI string.
     *
     * @param ScorePartwise $score The MusicXML score object.
     * @param int $timebase The desired timebase (ticks per quarter note) for the MIDI file.
     * @return string The binary MIDI data.
     */
    public function toMidi(ScorePartwise $score, $timebase = 480)
    {
        $this->score = $score;
        $this->timebase = $timebase;
        $this->midi = new Midi();
        $this->midi->setTempo(120); // Default tempo
        $this->midi->setTimebase($this->timebase);

        $this->buildPartMap();

        // Ensure the MIDI format is set to 1 (multitrack) if there are instrument tracks.
        // The Midi class might default to 0 if only the tempo track exists initially.
        $this->midi->setType(1);

        $this->createTempoTrack();

        foreach ($this->score->part as $part) {
            
            $this->processPart($part);
        }

        return $this->midi->getMidData();
    }

    /**
     * Creates the tempo track (Track 0) and adds global events like time signature.
     */
    private function createTempoTrack()
    {
        $track = 0;
        $this->midi->newTrack();
        $metaEvents = [];

        // Use the first part to determine global time signatures and tempos.
        // In a valid multitrack MIDI/MusicXML, these should be consistent across parts.
        if (isset($this->score->part[0])) {
            $firstPart = $this->score->part[0];
            $currentTime = 0;
            $divisions = $this->getInitialDivisions($firstPart);

            foreach ($firstPart->measure as $measure) {
                // Process attributes first
                if (isset($measure->elements)) {
                    foreach ($measure->elements as $element) {
                        if ($element instanceof \MusicXML\Model\Attributes) {
                            if (isset($element->divisions) && !empty($element->divisions->textContent)) {
                                $divisions = (int)$element->divisions->textContent;
                            }
                            if (isset($element->time)) {
                                $beats = (int)$element->time->beats[0]->textContent;
                                $beatType = (int)$element->time->beatType[0]->textContent;
                                $metaEvents[] = array('time' => $currentTime, 'type' => 'TimeSig', 'beats' => $beats, 'beatType' => $beatType);
                            }
                        }
                    }
                }

                // Process other elements to advance time
                if (isset($measure->elements)) {
                    foreach ($measure->elements as $element) {
                        if ($element instanceof \MusicXML\Model\Direction && isset($element->sound->tempo)) {
                            $tempo = (int)$element->sound->tempo;
                            if ($tempo > 0) {
                                $metaEvents[] = array('time' => $currentTime, 'type' => 'Tempo', 'tempo' => $tempo);
                            }
                        }

                        // Advance time based on note, backup, or forward durations
                        if ($element instanceof Note && !isset($element->chord)) {
                            $currentTime += $this->convertDurationToTicks($element->duration->textContent, $divisions);
                        } elseif ($element instanceof \MusicXML\Model\Backup) {
                            $currentTime -= $this->convertDurationToTicks($element->duration->textContent, $divisions);
                        } elseif ($element instanceof \MusicXML\Model\Forward) {
                            $currentTime += $this->convertDurationToTicks($element->duration->textContent, $divisions);
                        }
                    }
                }
            }
        }

        // Sort and add unique meta events to track 0
        usort($metaEvents, function ($a, $b) { return $a['time'] - $b['time']; });
        $uniqueEvents = [];
        foreach ($metaEvents as $event) { // NOSONAR
            $key = $event['time'] . '-' . $event['type'];
            $uniqueEvents[$key] = $event;
        }

        foreach ($uniqueEvents as $event) {
            if ($event['type'] === 'TimeSig') {
                $this->midi->addTimeSignature($event['time'], $event['beats'], $event['beatType'], $track);
            } elseif ($event['type'] === 'Tempo') {
                $microsecondsPerQuarter = round(60000000 / $event['tempo']);
                $this->midi->addMsg($track, $event['time'] . " Tempo " . $microsecondsPerQuarter);
            }
        }
    }

    /**
     * Builds a map from part IDs to MIDI channels and track numbers.
     */
    private function buildPartMap()
    {
        $trackIndex = 1; // Start from track 1 for instruments
        $channelCounter = 1; // Fallback channel counter, starts at 1

        foreach ($this->score->partList->scorePart as $scorePart) {
            $partId = (string)$scorePart->id;
            $channel = null;
            $program = 1;

            // Iterate through all midi-instruments to find the definitive channel and program for this part.
            if (isset($scorePart->midiInstrument) && is_array($scorePart->midiInstrument)) {
                foreach ($scorePart->midiInstrument as $midiInst) {
                    if (isset($midiInst->midiChannel) && !empty($midiInst->midiChannel->textContent)) {
                        $channel = (int)$midiInst->midiChannel->textContent;
                    }
                    if (isset($midiInst->midiProgram) && !empty($midiInst->midiProgram->textContent)) {
                        $program = (int)$midiInst->midiProgram->textContent;
                    }
                    if ($channel !== null) {
                        break; // Found channel, assume program is also set or default is fine.
                    }
                }
            }

            // If no channel was found, assign one sequentially, avoiding channel 10 unless necessary.
            if ($channel === null) {
                if ($channelCounter == 10) $channelCounter++; // Skip drum channel for normal instruments
                $channel = $channelCounter++;
            }

            $this->midi->newTrack();
            $this->partMap[$partId] = array(
                'track' => $trackIndex,
                'channel' => $channel,
                'program' => $program
            );

            // Do not add a Program Change event for the drum channel (10).
            if ($channel != 10) {
                $this->midi->addProgramChange($trackIndex, 0, $channel, $program);
            }
            $trackIndex++;
        }
    }

    /**
     * Processes a single MusicXML <part> and converts it to a MIDI track.
     *
     * @param \MusicXML\Model\PartPartwise $part The part to process.
     */
    private function processPart($part)
    {
        // file_put_contents("log.txt", print_r($part, true), FILE_APPEND);
        $partId = (string) $part->id;
        if (!isset($this->partMap[$partId])) {
            return; // Skip if part is not in the map
        }

        $trackInfo = $this->partMap[$partId];
        $track = $trackInfo['track'];
        $channel = $trackInfo['channel'];
        $currentTime = 0;
        $divisions = $this->getInitialDivisions($part); // Get divisions from the first measure

        // Tracks notes that are tied across measures. Key is note number, value is the Note Off event.
        $activeTies = array();

        // Stores the start time and duration of the current note or chord group.
        $noteGroupStartTime = 0;
        $noteGroupMaxDuration = 0;


        $timeline = array(); // NOSONAR

        // Ensure $part->measure is an array before looping
        foreach (is_array($part->measure) ? $part->measure : [] as $measure) {
            // Get all elements from the measure. They are already in order in the 'elements' array.
            $elements = (isset($measure->elements) && is_array($measure->elements)) ? $measure->elements : [];

            foreach ($elements as $element) {
                if ($element instanceof \MusicXML\Model\Attributes) {
                    // Handle mid-measure attribute changes, especially divisions.
                    if (isset($element->divisions) && !empty($element->divisions->textContent)) {
                        $newDivisions = (int)$element->divisions->textContent;
                        if ($newDivisions > 0) {
                            $divisions = $newDivisions;
                        }
                    }
                } else if ($element instanceof \MusicXML\Model\Backup) {
                    $durationTicks = $this->convertDurationToTicks($element->duration->textContent, $divisions); // Access textContent of the child Duration object
                    $currentTime -= $durationTicks;
                    if ($currentTime < 0) $currentTime = 0;
                } else if ($element instanceof \MusicXML\Model\Forward) {
                    $durationTicks = $this->convertDurationToTicks($element->duration->textContent, $divisions); // Access textContent of the child Duration object
                    $currentTime += $durationTicks;
                } else if ($element instanceof Note) {
                    $durationTicks = $this->convertDurationToTicks($element->duration->textContent, $divisions);
                    $isCurrentNoteAChord = isset($element->chord);

                    // Determine the start time and duration for this note or chord group
                    // If it's a new note/chord, advance time and reset group properties.
                    if (!$isCurrentNoteAChord) {
                        $currentTime += $noteGroupMaxDuration;
                        $noteGroupStartTime = $currentTime;
                        $noteGroupMaxDuration = $durationTicks;
                    }

                    if (!isset($element->rest)) {
                        $noteNumber = -1;
                        if (isset($element->pitch)) {
                            $noteNumber = $this->getMidiNoteNumber($element->pitch);
                        } elseif (isset($element->unpitched) && $channel == 10) {
                            $noteNumber = $this->getMidiDrumNoteNumber($element->instrument->id);
                        }
                        
                        if ($noteNumber >= 0) {
                            $velocity = isset($element->dynamics) ? (int)($element->dynamics * 1.27) : 100;

                            // Check for both <tie> and <notations><tied>
                            $isTieStart = (isset($element->tie) && $element->tie->type == 'start') || (isset($element->notations->tied) && $element->notations->tied[0]->type == 'start');
                            $isTieStop = (isset($element->tie) && $element->tie->type == 'stop') || (isset($element->notations->tied) && $element->notations->tied[0]->type == 'stop');
                            // A note can be both a stop and a start of a new tie
                            $isTieContinue = (isset($element->notations->tied) && count($element->notations->tied) > 1 && $element->notations->tied[0]->type == 'stop' && $element->notations->tied[1]->type == 'start');

                            // If this note is the end of a tie, don't create a new Note On event.
                            // Instead, extend the duration of the existing tied note.
                            if ($isTieStop && isset($activeTies[$noteNumber])) {
                                $activeTies[$noteNumber]['time'] += $durationTicks; // Extend duration
                                // If this is the end of the tie chain, add the final Note Off
                                if (!$isTieStart) {
                                    $timeline[] = $activeTies[$noteNumber]; // Add the final extended Note Off
                                    unset($activeTies[$noteNumber]);
                                }
                            } else {
                                // This is a new note or the start of a tie.
                                // Introduce a small gap (articulation) to prevent notes from having zero duration
                                // when they are consecutive. A 1-tick gap is usually sufficient.
                                $articulationGap = 1;
                                $effectiveDuration = ($durationTicks > $articulationGap) ? $durationTicks - $articulationGap : 0;

                                $noteOnEvent = array('time' => $noteGroupStartTime, 'type' => 'On', 'note' => $noteNumber, 'velocity' => $velocity);
                                $noteOffEvent = array('time' => $noteGroupStartTime + $effectiveDuration, 'type' => 'Off', 'note' => $noteNumber, 'velocity' => 0);

                                $timeline[] = $noteOnEvent;

                                // If this note starts a tie, store its Note Off event to be potentially extended later.
                                if ($isTieStart) {
                                    $activeTies[$noteNumber] = $noteOffEvent;
                                } else {
                                    // If it's a normal note, add its Note Off event immediately.
                                    $timeline[] = $noteOffEvent;
                                }
                            }

                            // Add lyric event if present
                            if (isset($element->lyric) && isset($element->lyric) && isset($element->lyric->text) && isset($element->lyric->text->textContent)) {
                                $lyricText = $element->lyric->text->textContent;
                                // Add Lyric meta event at the same time as the Note On event
                                $timeline[] = array('time' => $noteGroupStartTime, 'type' => 'Lyric', 'text' => $lyricText);
                            }
                        }
                    }

                    // Update the maximum duration seen in the current chord group.
                    // This ensures the main time cursor will advance correctly after the group.
                    // For rests, we also update the max duration to correctly advance time.
                    $noteGroupMaxDuration = max($noteGroupMaxDuration, $durationTicks);
                }
            }
        }

        // Final sort of all events before adding them to the MIDI track.
        // Sort timeline by time, then by type (Off before On at the same time)
        usort($timeline, function($a, $b) {
            // Primary sort: by time (tick)
            if ($a['time'] != $b['time']) {
                return $a['time'] < $b['time'] ? -1 : 1;
            }

            // Secondary sort: by event type, for events at the same time.
            // This is crucial for MIDI correctness. The desired order is:
            // 1. Lyric (Meta)
            // 2. Note Off
            // 3. Note On
            $typeOrder = array('Lyric' => 0, 'Off' => 1, 'On' => 2);
            $aOrder = isset($typeOrder[$a['type']]) ? $typeOrder[$a['type']] : 99;
            $bOrder = isset($typeOrder[$b['type']]) ? $typeOrder[$b['type']] : 99;

            return $aOrder - $bOrder;
        });

        // Add sorted events to the MIDI track
        foreach ($timeline as $event) {
            if ($event['type'] == 'On') {
                $this->midi->addNoteOn($track, $event['time'], $channel, $event['note'], $event['velocity']);
            } else if ($event['type'] == 'Lyric') {
                // The Midi class's internal parser expects the text part of the message to be enclosed in quotes. We must escape any quotes within the lyric text itself.
                $escapedLyricText = str_replace('"', '\"', $event['text']);
                $this->midi->addMsg($track, $event['time'] . ' Meta Lyric "' . $escapedLyricText . '"');
            } else {
                $this->midi->addNoteOff($track, $event['time'], $channel, $event['note'], $event['velocity']);
            }
        }
    }

    /**
     * Gets the initial <divisions> value from the first measure of a part.
     *
     * @param \MusicXML\Model\PartPartwise $part The part to inspect.
     * @return int The divisions value, or a default of 1 if not found.
     */
    private function getInitialDivisions($part)
    {
        if (isset($part->measure[0]) && isset($part->measure[0]->elements)) {
            foreach ($part->measure[0]->elements as $element) {
                if ($element instanceof \MusicXML\Model\Attributes) {
                    if (isset($element->divisions) && !empty($element->divisions->textContent)) {
                        $divisions = (int)$element->divisions->textContent;
                        if ($divisions > 0) {
                            return $divisions;
                        }
                    }
                }
            }
        }
        // Fallback if not found in the first measure, check the whole score (less efficient but safer)
        if (isset($this->score->part[0]->measure[0]->attributes->divisions)) {
            return (int) $this->score->part[0]->measure[0]->attributes->divisions;
        }
        return 1; // Should not happen with valid MusicXML
    }

    /**
     * Converts a MusicXML duration (in divisions) to MIDI ticks.
     *
     * @param int $duration The duration in MusicXML divisions.
     * @param int $divisions The <divisions> value for the current part/measure.
     * @return int The duration in MIDI ticks.
     */
    private function convertDurationToTicks($duration, $divisions)
    {
        if ($divisions <= 0) {
            return 0;
        }
        // The formula is: (musicxml_duration / musicxml_divisions_per_quarter) * midi_ticks_per_quarter
        // Use floating point for precision during calculation to match the generation logic.
        $preciseTicks = ((float)$duration / (float)$divisions) * $this->timebase;
        return (int) round($preciseTicks);
    }

    /**
     * Converts a MusicXML <pitch> object to a MIDI note number.
     *
     * @param \MusicXML\Model\Pitch $pitch The pitch object.
     * @return int The MIDI note number (0-127).
     */
    private function getMidiNoteNumber($pitch)
    {
        $stepMap = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];
        $step = strtoupper((string) $pitch->step->textContent);
        $octave = (int) $pitch->octave->textContent;
        $alter = isset($pitch->alter) ? (int) $pitch->alter->textContent : 0;

        if (!isset($stepMap[$step])) {
            return 60; // Default to Middle C if step is invalid
        }

        $note = 12 * ($octave + 1) + $stepMap[$step] + $alter;

        // Clamp to valid MIDI range
        return max(0, min(127, $note));
    }

    /**
     * Extracts the MIDI note number for a drum instrument from its MusicXML ID.
     * Assumes the ID is in the format 'P10-I36', where 36 is the note number.
     *
     * @param string $instrumentId The instrument ID string.
     * @return int The MIDI note number for the drum sound.
     */
    private function getMidiDrumNoteNumber($instrumentId)
    {
        $parts = explode('-I', $instrumentId);
        if (count($parts) == 2 && is_numeric($parts[1])) {
            return (int)$parts[1] - 1; // MusicXML unpitched is 1-based, MIDI is 0-based
        }
        return 35; // Default to Acoustic Bass Drum
    }

    /**
     * Helper to camelize strings.
     *
     * @param string $input
     * @param string $separator
     * @return string
     */
    private function camelize($input, $separator = '_')
    {
        return lcfirst(str_replace($separator, '', ucwords($input, $separator)));
    }
}
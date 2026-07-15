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
 * @author Gemini Code Assist
 */
class MusicXMLToMidi
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
        $this->midi->addTrack();
        $metaEvents = [];

        // Collect all global events (Tempo, TimeSig) from all parts
        foreach ($this->score->part as $part) {
            $currentTime = 0;
            $divisions = 1;
            foreach ($part->measure as $measure) {
                if (isset($measure->attributes)) {
                    $attributes = $measure->attributes;
                    if (isset($attributes->divisions)) {
                        $divisions = (int)$attributes->divisions;
                    }
                    if ($divisions == 0) $divisions = 1;

                    if (isset($attributes->time)) {
                        $beats = (int)$attributes->time->beats[0]->textContent;
                        $beatType = (int)$attributes->time->beatType[0]->textContent;
                        $metaEvents[] = ['time' => $currentTime, 'type' => 'TimeSig', 'beats' => $beats, 'beatType' => $beatType];
                    }
                }

                foreach ($measure->elements as $element) {
                    $durationTicks = 0;
                    if (isset($element->duration)) {
                        $durationTicks = $this->convertDurationToTicks($element->duration->textContent, $divisions);
                    }

                    if ($element instanceof \MusicXML\Model\Direction && isset($element->sound->tempo)) {
                        $tempo = (int)$element->sound->tempo;
                        if ($tempo > 0) {
                            $metaEvents[] = ['time' => $currentTime, 'type' => 'Tempo', 'tempo' => $tempo];
                        }
                    }

                    if ($element instanceof \MusicXML\Model\Note && !isset($element->chord)) {
                        $currentTime += $durationTicks;
                    } elseif ($element instanceof \MusicXML\Model\Backup) {
                        $currentTime -= $durationTicks;
                    } elseif ($element instanceof \MusicXML\Model\Forward) {
                        $currentTime += $durationTicks;
                    }
                }
            }
        }

        // Sort and add unique meta events to track 0
        usort($metaEvents, function ($a, $b) { return $a['time'] - $b['time']; });
        $uniqueEvents = [];
        foreach ($metaEvents as $event) {
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
            $partId = (string) $scorePart->id;
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

            $this->midi->addTrack();
            $this->partMap[$partId] = [
                'track' => $trackIndex,
                'channel' => $channel,
                'program' => $program,
            ];

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
        $partId = (string) $part->id;
        if (!isset($this->partMap[$partId])) {
            return; // Skip if part is not in the map
        }

        $trackInfo = $this->partMap[$partId];
        $track = $trackInfo['track'];
        $channel = $trackInfo['channel'];
        $currentTime = 0;
        $divisions = 1; // Default, will be updated

        $timeline = array();

        foreach ($part->measure as $measure) {
            // Update divisions if specified in the measure attributes
            if (isset($measure->attributes->divisions)) {
                $divisions = (int) $measure->attributes->divisions;
            }
            if ($divisions == 0) $divisions = 1;

            foreach ($measure->elements as $element) {
                if ($element instanceof \MusicXML\Model\Backup) {
                    $durationTicks = $this->convertDurationToTicks($element->duration->textContent, $divisions);
                    $currentTime -= $durationTicks;
                    if ($currentTime < 0) $currentTime = 0;
                } else if ($element instanceof \MusicXML\Model\Forward) {
                    $durationTicks = $this->convertDurationToTicks($element->duration->textContent, $divisions);
                    $currentTime += $durationTicks;
                } else if ($element instanceof Note) {
                    $durationTicks = $this->convertDurationToTicks($element->duration->textContent, $divisions);
                    $isChord = isset($element->chord);

                    if (isset($element->rest)) {
                        if (!$isChord) {
                            $currentTime += $durationTicks;
                        }
                    } else {
                        $noteNumber = -1;
                        if (isset($element->pitch)) {
                            $noteNumber = $this->getMidiNoteNumber($element->pitch);
                        } elseif (isset($element->unpitched) && $channel == 10) {
                            $noteNumber = $this->getMidiDrumNoteNumber($element->instrument->id);
                        }

                        if ($noteNumber >= 0) {
                            $velocity = isset($element->dynamics) ? (int)($element->dynamics * 1.27) : 100;
                            
                            // Add Note On to timeline
                            $timeline[] = array('time' => $currentTime, 'type' => 'On', 'note' => $noteNumber, 'velocity' => $velocity);
                            // Add Note Off to timeline
                            $timeline[] = array('time' => $currentTime + $durationTicks, 'type' => 'Off', 'note' => $noteNumber, 'velocity' => 0);
                        }

                        if (!$isChord) {
                            $currentTime += $durationTicks;
                        }
                    }
                }
            }
        }

        // Sort timeline by time, then by type (Off before On at the same time)
        usort($timeline, function($a, $b) {
            if ($a['time'] == $b['time']) {
                return ($a['type'] == 'Off') ? -1 : 1;
            }
            return $a['time'] < $b['time'] ? -1 : 1;
        });

        // Add sorted events to the MIDI track
        foreach ($timeline as $event) {
            if ($event['type'] == 'On') {
                $this->midi->addNoteOn($track, $event['time'], $channel, $event['note'], $event['velocity']);
            } else {
                $this->midi->addNoteOff($track, $event['time'], $channel, $event['note'], $event['velocity']);
            }
        }
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
        // A MusicXML division is a fraction of a quarter note.
        // MIDI ticks are also based on a quarter note (timebase).
        return (int) (($duration * $this->timebase) / $divisions);
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
        if (count($parts) == 2) {
            return (int)$parts[1] - 1; // MusicXML unpitched is 1-based, MIDI is 0-based
        }
        return 35; // Default to Acoustic Bass Drum
    }
}
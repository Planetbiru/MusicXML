<?php
namespace MusicXML;

use MusicXML\Model\BeatUnit;
use MusicXML\Model\Clef;
use MusicXML\Model\Direction;
use MusicXML\Model\DirectionType;
use MusicXML\Model\Line;
use MusicXML\Model\MeasurePartwise;
use MusicXML\Model\Metronome;
use MusicXML\Model\Note;
use MusicXML\Model\PerMinute;
use MusicXML\Model\Sign;
use MusicXML\Model\Sound;
use MusicXML\Model\Work;
use MusicXML\Model\WorkTitle;
use MusicXML\Properties\AttackRelease;
use MusicXML\Properties\BeamNote;
use MusicXML\Properties\Coordinate;
use MusicXML\Properties\TimeSignature;

/**
 * A utility class providing static helper methods for MusicXML creation and data manipulation.
 *
 * This class contains functions for calculating note types, handling durations, creating
 * complex MusicXML elements like directions (tempo), and determining musical properties like clefs.
 * 
 * @author Kamshory
 */
class MusicXMLUtil
{
    /**
     * Dget note type
     *
     * @param int $duration
     * @param int $divisions
     * @return string
     */
    public static function getNoteType($duration, $divisions)
    {
        $value = $duration/(4*$divisions);
        foreach(self::$type as $type=>$valueType)
        {
            if($value >= $valueType)
            {
                return $type;
            }
        }
        return '1024th';
    }

    /**
     * Get number of dots for a given duration and divisions
     *
     * @param int $duration
     * @param int $divisions
     * @return integer
     */
    public static function getNoteDots($duration, $divisions)
    {
        if ($divisions <= 0 || $duration <= 0) {
            return 0;
        }
        $value = $duration / (4 * $divisions);
        foreach (self::$type as $type => $valueType) {
            if ($value >= $valueType) {
                $baseDuration = $valueType * 4 * $divisions;
                $ratio = $duration / $baseDuration;
                if (abs($ratio - 1.5) < 0.01) {
                    return 1;
                }
                if (abs($ratio - 1.75) < 0.01) {
                    return 2;
                }
                if (abs($ratio - 1.875) < 0.01) {
                    return 3;
                }
                break;
            }
        }
        return 0;
    }


    /**
     * @var array
     */
    protected static $type = array(
        'maxima'=>8,
        'long'=>4,
        'breve'=>2,
        'whole'=>1,
        'half'=>0.5,
        'quarter'=>0.25,
        'eighth'=>0.125,
        '16th'=>0.0625,
        '32nd'=>0.03125,
        '64th'=>0.015625,
        '128th'=>0.0078125,
        '256th'=>0.00390625,
        '512th'=>0.001953125,
        '1024th'=>0.0009765625
    );
    
    /**
     * Get note coordinate
     *
     * @param int $measureIndex
     * @param array $message
     * @param int $divisions
     * @param int $timebase
     * @param TimeSignature $timeSignature
     * @param float $width
     * @return Coordinate
     */
    public static function getNoteCoordinate($measureIndex, $message, $divisions, $timebase, $timeSignature, $width)
    {
        $coordinate = new Coordinate();       
        $timeRelative = $message['abstime'] - ($measureIndex * $timebase);
        $coordinate->defaultX = $timeRelative * $width * $timeSignature->getBeats() / ($divisions*$timebase);
        return $coordinate;
    }
    
    /**
     * Get note coordinate
     *
     * @param int $measureIndex
     * @param array $message
     * @param int $timebase
     * @param TimeSignature $timeSignature
     * @param int $duration
     * @return AttackRelease
     */
    public static function getAttackRelease($measureIndex, $message, $timebase, $timeSignature, $duration)
    {
        $measureLength = $timebase * $timeSignature->getBeats();
        $timeRelative = $message['abstime'] - ($measureIndex * $measureLength);
        $attack = $timeRelative * $timeSignature->getBeats() / ($timebase);
        $release = $attack + $duration;
        return new AttackRelease($attack, $release);     
    }
    
    /**
     * Get work
     *
     * @param string $title
     * @return Work
     */
    public static function getWork($title)
    {
        $work = new Work();
        $work->workTitle = new WorkTitle($title);
        return $work;
    }
    
    /**
     * Find last On
     *
     * @param array $messages
     * @return integer
     */
    public static function findLastOn($messages)
    {
        $last = 0;
        foreach ($messages as $idx => $note) {
            if ($note['event'] == 'On') {
                $last = $idx;
            }
        }
        return $last;
    }
    
    /**
     * Fix duration
     *
     * @param float $duration
     * @param int $timebase
     * @return float
     */
    public static function fixDuration($duration, $timebase)
    {
        if($duration > 4/$timebase)
        {
            $duration = 4/$timebase;
        }
        return $duration;
    }
    
    /**
     * Get last time
     *
     * @param array $lastTime
     * @param string $index
     * @return float
     */
    public static function getLastTime($lastTime, $index)
    {
        if (isset($lastTime[$index])) {
            $lt = $lastTime[$index];
        } else {
            $lt = 0;
        }
        return $lt;
    }
    
    /**
     * Get directions
     * 
     * @param array $tempoList
     */
    public static function getDirections($tempoList)
    {
        $lastBpm = 0;
        $directions = array();
        if(isset($tempoList))
        {
            foreach($tempoList as $value) 
            {
                $rawtime = $value['rawtime'];
                $bpm = $value['bpm'];
                if(!isset($directions[$rawtime]))
                {
                    $directions[$rawtime] = new Direction();
                }
                if($bpm != $lastBpm)
                {
                    $sound = new Sound();
                    $sound->tempo = $bpm;
                    $directions[$rawtime]->sound = $sound;                    
                    $directionType = new DirectionType();
                    $metronome = new Metronome();
                    $metronome->parentheses = 'no';
                    $metronome->perMinute = new PerMinute($bpm);
                    $metronome->beatUnit = new BeatUnit('quarter');
                    $directionType->metronome = $metronome;                    
                    $directions[$rawtime]->directionType = $directionType;
                    $directions[$rawtime]->placement = 'above';               
                    $lastBpm = $bpm;
                }
            }
        }
        return $directions;
    }
    
    /**
     * Get clef from notes
     *
     * @param int $min
     * @param int $max
     * @return Clef[]
     */
    public static function getClef($min, $max)
    {
        $clefs = array();
        $clef1 = new Clef();

        // Clef selection logic:
        // 1. Bass clef (F) for low register notes
        // 2. Alto clef (C) on line 3 for mid-register notes (e.g. Viola range)
        // 3. Treble clef (G) for high register notes
        if ($min < 48) {
            $clef1->sign = new Sign('F');
            $clef1->line = new Line(4);
        } elseif ($min < 57 && $max <= 76 && ($min + $max) / 2 < 63) {
            $clef1->sign = new Sign('C');
            $clef1->line = new Line(3);
        } else {
            $clef1->sign = new Sign('G');
            $clef1->line = new Line(2);
        }
        $clefs[] = $clef1;
        
        return $clefs;
    }
    
    /**
     * Get programs
     *
     * @param array $midiEventMessages
     * @return array
     */
    public static function getControlEvent($midiEventMessages)
    {
        $messages = array();
        foreach ($midiEventMessages as $message) {
            if (isset($message['event']) && $message['event'] != 'On' && $message['event'] != 'Off') {
                $messages[] = $message;
            }
        }
        return $messages;
    }
    
    /**
     * Get minimum duration
     *
     * @param array $midiEventMessages
     * @param int $timebase
     * @return float
     */
    public static function getMinimumDuration($midiEventMessages, $timebase)
    {
        $min = $timebase;
        foreach ($midiEventMessages as $message) {
            if (isset($message['duration']) && $message['duration'] > 0 && $message['duration'] < $min) {
                $min = $message['duration'];
            }
        }
        return $min;
    }

    /**
     * Get notes
     *
     * @param array $midiEventMessages
     * @return array
     */
    public static function getNotes($midiEventMessages)
    {
        $messages = array();
        foreach ($midiEventMessages as $message) {
            if (isset($message['event']) && ($message['event'] == 'On' || $message['event'] == 'Off')) {
                $messages[] = $message;
            }
        }
        return $messages;
    }
    
    /**
     * Get index of note of channel
     *
     * @param array $noteMessages
     * @param int $time
     * @param int $timebase
     * @return integer | false
     */
    public static function getNoteIndex($noteMessages, $time, $timebase)
    {
        // reverse
        $keys = array_keys($noteMessages);
        $reversed = array_reverse($keys);
        foreach($reversed as $key)
        {
            $duration = isset($noteMessages[$key]['duration']) ? $noteMessages[$key]['duration'] * $timebase : 0;
            if($noteMessages[$key]['time'] < $time && ($noteMessages[$key]['time'] + $duration) > $time)
            {
                return $key;
            }
        }
        return false;
    }
    
    /**
     * Get element index from note index or false if not found
     *
     * @param MeasurePartwise $measure
     * @param int $idx
     * @return integer|boolean
     */
    public static function getElementIndexFromNoteIndexX($measure, $idx)
    {
        $cnt = 0;
        foreach($measure->elements as $elementIndex=>$element)
        {
            if($element instanceof Note)
            {
                if($cnt == $idx)
                {
                    return $elementIndex;
                }
                $cnt++;
            }
        }
        return false;
    }

    /**
     * Get element index
     *
     * @param array $noteMessages
     * @return integer|boolean
     */
    public static function getElementIndexFromNoteIndex($noteMessages)
    {
        if(isset($noteMessages['elementIndex']))
        {
            return $noteMessages['elementIndex'];
        }
        return false;
    }
    
    /**
     * Get beams
     *
     * @param array $noteMessages
     * @param int $timebase
     * @param TimeSignature $timeSignature
     * @return BeamNote[] | false
     */
    public static function getBeams($noteMessages, $timebase, $timeSignature)
    {
        $beamNotes = array();      
        for($i = 0; $i < $timeSignature->getBeats(); $i++)
        {
            $time1 = $timebase * $i;
            $time2 = $timebase * ($i + 1);
            $j = 0;
            foreach($noteMessages as $message)
            {
                $rtime = $message['abstime'] % ($timebase * $timeSignature->getBeats());
                if($message['event'] == 'On' && isset($message['duration']) && $rtime >= $time1 && $rtime <= $time2)
                {
                    $duration = $message['duration'];
                    if(($rtime + $duration) <= $time2)
                    {
                        $k = self::getElementIndexFromNoteIndex($message);
                        $beamNotes[] = new BeamNote($i, $j, $k);
                        $j++;   
                    }
                }
            }
        }
        if(empty($beamNotes))
        {
            return false;
        }
        $beamNotes = BeamNote::closeBeams($beamNotes);
        return $beamNotes;
    }
    
    /**
     * Get instrument  name
     *
     * @param int $instrumentId
     * @param int $channelId
     * @return array
     */
    public static function getInstrumentName($instrumentId, $channelId)
    {
        if ($channelId == 10) {
            $drumkits = array(
                0 => 'Drum Kit',
                8 => 'Room Kit',
                16 => 'Power Kit',
                24 => 'Electronic Kit',
                32 => 'Jazz Kit',
                40 => 'Brush Kit',
                48 => 'Orchestra Kit',
                56 => 'SFX Kit'
            );
            $kitName = isset($drumkits[$instrumentId]) ? $drumkits[$instrumentId] : 'Drum Kit';
            return array($kitName, 'D. Kit');
        } 
        if (null !== MusicXMLInstrument::INSTRUMENT_LIST[$instrumentId]) {
            return MusicXMLInstrument::INSTRUMENT_LIST[$instrumentId];
        }
        return array('Instrument ' . ($instrumentId + 1), 'Instr.');
    }

}
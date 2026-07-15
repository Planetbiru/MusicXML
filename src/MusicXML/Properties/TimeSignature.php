<?php

namespace MusicXML\Properties;

/**
 * Represents a time signature parsed from a MIDI message.
 *
 * This class extracts the time, beats (numerator), and beat-type (denominator)
 * from a raw MIDI 'TimeSig' event array.
 * 
 * @author Kamshory
 */
class TimeSignature
{
    /**
     * The time of the event in ticks.
     * @var int
     */
    private $time = 0;
    /**
     * The number of beats per measure (the numerator).
     * @var int
     */
    private $beats = 0;
    /**
     * The note value that represents one beat (the denominator).
     * @var int
     */
    private $beatType = 4;
    
    /**
     * TimeSignature constructor.
     *
     * @param array $msg The raw parsed MIDI message from the parser. e.g., `[0, 'TimeSig', '4/4', 24, 8]`
     */
    public function __construct($msg)
    {
        $this->time = $msg[0];
        $arr = explode("/", $msg[2]);
        $this->beats = (int) $arr[0];
        $this->beatType = (int) $arr[1];
    }

    /**
     * Gets the time of the event in MIDI ticks.
     */ 
    public function getTime()
    {
        return $this->time;
    }

    /**
     * Sets the time of the event in MIDI ticks.
     *
     * @param int $time The time of the event in ticks.
     * @return  self
     */ 
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }

    /**
     * Gets the number of beats per measure (numerator).
     */ 
    public function getBeats()
    {
        return $this->beats;
    }

    /**
     * Sets the number of beats per measure (numerator).
     *
     * @param int $beats The number of beats per measure.
     * @return  self
     */ 
    public function setBeats($beats)
    {
        $this->beats = $beats;

        return $this;
    }

    /**
     * Gets the note value that represents one beat (denominator).
     */ 
    public function getBeatType()
    {
        return $this->beatType;
    }

    /**
     * Sets the note value that represents one beat (denominator).
     *
     * @param int $beatType The note value that represents one beat (e.g., 4 for a quarter note).
     * @return  self
     */ 
    public function setBeatType($beatType)
    {
        $this->beatType = $beatType;

        return $this;
    }
}
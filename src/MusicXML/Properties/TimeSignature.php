<?php

namespace MusicXML\Properties;

/**
 * Represents a time signature event from a MIDI file.
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
     * Get the value of time
     */ 
    public function getTime()
    {
        return $this->time;
    }

    /**
     * Set the value of time
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
     * Get the value of beats
     */ 
    public function getBeats()
    {
        return $this->beats;
    }

    /**
     * Set the value of beats
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
     * Get the value of beatType
     */ 
    public function getBeatType()
    {
        return $this->beatType;
    }

    /**
     * Set the value of beatType
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
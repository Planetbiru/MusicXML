<?php

namespace MusicXML\Properties;

/**
 * A data transfer object for storing MIDI control events within a measure.
 * This class holds collections of tempo and key signature changes.
 * 
 * @author Kamshory
 */
class MidiEvent 
{
    /**
     * An associative array of tempo events, keyed by time. e.g., `['rawtime' => 0, 'tempo' => 500000, 'bpm' => 120]`
     * 
     * @var int[]
     */
    private $tempoList = array();

    /**
     * An associative array of key signature events, keyed by time. e.g., `['fifths' => 0, 'mode' => 'major']`
     * 
     * @var array[]
     */
    private $keySignatureList = array();

    /**
     * MidiEvent constructor.
     *
     * @param array $tempoList A list of tempo events.
     * @param array $keySignatureList A list of key signature events.
     */
    public function __construct($tempoList, $keySignatureList)
    {
        $this->setTempoList($tempoList);
        $this->setKeySignatureList($keySignatureList);
    }

    /**
     * Gets the list of tempo events.
     */ 
    public function getTempoList()
    {
        return $this->tempoList;
    }

    /**
     * Sets the list of tempo events.
     * 
     * @param int[] $tempoList An associative array of tempo events.
     * @return  self
     */ 
    public function setTempoList($tempoList)
    {
        $this->tempoList = $tempoList;

        return $this;
    }

    /**
     * Gets the list of key signature events.
     */ 
    public function getKeySignatureList()
    {
        return $this->keySignatureList;
    }

    /**
     * Sets the list of key signature events.
     *
     * @param array[] $keySignatureList An associative array of key signature events.
     * @return  self
     */ 
    public function setKeySignatureList($keySignatureList)
    {
        $this->keySignatureList = $keySignatureList;

        return $this;
    }
}
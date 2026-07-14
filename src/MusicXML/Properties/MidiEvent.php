<?php

namespace MusicXML\Properties;

class MidiEvent 
{
    /**
     * Tempo list
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
     * Constructor
     *
     * @param array $tempoList
     * @param array $keySignatureList
     */
    public function __construct($tempoList, $keySignatureList)
    {
        $this->setTempoList($tempoList);
        $this->setKeySignatureList($keySignatureList);
    }

    /**
     * Get the value of tempoList
     */ 
    public function getTempoList()
    {
        return $this->tempoList;
    }

    /**
     * Set the value of tempoList
     * 
     * @param int[] $tempoList
     * @return  self
     */ 
    public function setTempoList($tempoList)
    {
        $this->tempoList = $tempoList;

        return $this;
    }

    /**
     * Get the value of keySignatureList
     */ 
    public function getKeySignatureList()
    {
        return $this->keySignatureList;
    }

    /**
     * Set the value of keySignatureList
     *
     * @param TimeSignature[] $keySignatureList
     * @return  self
     */ 
    public function setKeySignatureList($keySignatureList)
    {
        $this->keySignatureList = $keySignatureList;

        return $this;
    }
}
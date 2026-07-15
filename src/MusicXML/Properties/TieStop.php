<?php

namespace MusicXML\Properties;

use MusicXML\Model\Duration;
use MusicXML\Model\Notations;
use MusicXML\Model\Note;
use MusicXML\Model\Tie;
use MusicXML\Model\Tied;
use MusicXML\Model\Type;
use MusicXML\MusicXMLUtil;

/**
 * Represents the "stop" portion of a tied note that crosses a measure boundary.
 *
 * This class creates a new note object with the appropriate 'stop' tie notation
 * and calculates the remaining duration for the note in the new measure.
 * 
 * @author Kamshory
 */
class TieStop
{
    /**
     * The index of the measure where the tie stops.
     *
     * @var integer
     */
    private $targetMeasureIndex = 0;

    /**
     * The index of the measure where the tie started.
     *
     * @var integer
     */
    private $originMeasureIndex = 0;

    /**
     * The generated Note object representing the end of the tie.
     *
     * @var Note
     */
    private $note = null;

    /**
     * The number of measures the tie spans.
     *
     * @var integer
     */
    private $tieRange = 0; 

    /**
     * The remaining duration of the note in the target measure, in MusicXML divisions.
     *
     * @var integer
     */
    private $durationRemaining = 0;

    /**
     * The remaining duration of the note in the target measure, in MIDI ticks.
     *
     * @var integer
     */
    private $timeRemaining = 0;

    /**
     * TieStop constructor.
     *
     * @param int  $targetMeasureIndex The index of the measure where the tie stops.
     * @param int  $originMeasureIndex The index of the measure where the tie started.
     * @param Note $note               The original note object from which to copy pitch information.
     * @param int  $tieRange           The number of measures the tie spans.
     * @param int  $durationRemaining  The remaining duration in MusicXML divisions for the new measure.
     * @param int  $timeRemaining      The remaining duration in MIDI ticks for the new measure.
     * @param int  $divisions          The divisions per quarter note for the measure.
     */
    public function __construct($targetMeasureIndex, $originMeasureIndex, $note, $tieRange, $durationRemaining, $timeRemaining, $divisions)
    {
        $this->targetMeasureIndex = $targetMeasureIndex;
        $this->originMeasureIndex = $originMeasureIndex;
        $newNote = new Note();
        $newNote->pitch = $note->pitch;
        
        $tie = new Tie();
        $tie->type = 'stop';
        $tied = new Tied();
        $tied->type = 'stop';
        $note->type = new Type(MusicXMLUtil::getNoteType($durationRemaining, $divisions));

        $notations = new Notations();
        $notations->tied = $tied;

        $newNote->tie = $tie;
        $newNote->notations = $notations;
        $newNote->duration = new Duration($durationRemaining);
        
        $this->note = $newNote;
        $this->tieRange = $tieRange; 
        $this->durationRemaining = $durationRemaining;
        $this->timeRemaining = $timeRemaining;
    }

    /**
     * Gets the index of the measure where the tie stops.
     *
     * @return  integer
     */ 
    public function getTargetMeasureIndex()
    {
        return $this->targetMeasureIndex;
    }

    /**
     * Gets the index of the measure where the tie started.
     *
     * @return  integer
     */ 
    public function getOriginMeasureIndex()
    {
        return $this->originMeasureIndex;
    }

    /**
     * Gets the generated Note object representing the end of the tie.
     *
     * @return  Note
     */ 
    public function getNote()
    {
        return $this->note;
    }

    /**
     * Gets the number of measures the tie spans.
     *
     * @return  integer
     */ 
    public function getTieRange()
    {
        return $this->tieRange;
    }

    /**
     * Gets the remaining duration in MusicXML divisions.
     *
     * @return  integer
     */ 
    public function getDurationRemaining()
    {
        return $this->durationRemaining;
    }

    /**
     * Gets the remaining duration in MIDI ticks.
     *
     * @return  integer
     */ 
    public function getTimeRemaining()
    {
        return $this->timeRemaining;
    }
}
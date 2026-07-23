<?php

namespace MusicXML\Properties;

use MusicXML\Model\Beam;

/**
 * Represents a note's participation in a beam group.
 *
 * This class holds a `Beam` object and the index of the note element it applies to,
 * facilitating the construction of correct `begin`, `continue`, and `end` beam notations.
 * 
 * @author Kamshory
 */
class BeamNote
{
    const TYPE_BACKWARD_HOOK = "backward hook";
    const TYPE_BEGIN = "begin";
    const TYPE_CONTINUE = "continue";
    const TYPE_END = "end";
    const TYPE_FORWARD_HOOK = "forward hook";
    /**
     * The index of the note element within its measure's element list.
     *
     * @var integer
     */
    public $index;
    /**
     * The Beam object containing type and level information.
     *
     * @var Beam
     */
    public $beam;
    
    /**
     * BeamNote constructor.
     *
     * @param int $number
     * @param int $beamIndex
     * @param int $elementIndex
     */
    public function __construct($number, $beamIndex, $elementIndex)
    {
        $this->beam = new Beam($beamIndex == 0 ? self::TYPE_BEGIN : self::TYPE_CONTINUE);
        $this->beam->number = $number + 1;
        $this->index = $elementIndex;
    }

    /**
     * Creates and finalizes a set of BeamNote objects for a given array of notes within a single beat.
     *
     * @param array $notesInBeat Array of note messages for one beat.
     * @param int $beatIndex The index of the beat.
     * @return self[] An array of finalized BeamNote objects for this beat.
     */
    public static function createFromNotes($notesInBeat, $beatIndex)
    {
        $beamNotes = array();
        $noteCounter = 0;
        foreach ($notesInBeat as $note) {
            $elementIndex = \MusicXML\MusicXMLUtil::getElementIndexFromNoteIndex($note);
            if ($elementIndex !== false) {
                $beamNotes[] = new self($beatIndex, $noteCounter, $elementIndex);
                $noteCounter++;
            }
        }
        return self::closeBeams($beamNotes);
    }
    
    /**
     * Finalizes beam types in a set of BeamNote objects, setting the last one of each level to 'end'.
     *
     * @param self[] $beamNotes
     * @return self[]
     */
    public static function closeBeams($beamNotes)
    {
        if (empty($beamNotes)) {
            return [];
        }

        $maxBeamLevel = 0;
        foreach ($beamNotes as $beamNote) {
            if ($beamNote->beam->number > $maxBeamLevel) {
                $maxBeamLevel = $beamNote->beam->number;
            }
        }

        for ($level = 1; $level <= $maxBeamLevel; $level++) {
            $firstNoteIndexForLevel = -1;
            $lastNoteIndexForLevel = -1;

            $notesInLevel = 0;
            foreach ($beamNotes as $index => $beamNote) {
                if ($beamNote->beam->number >= $level) {
                    if ($firstNoteIndexForLevel === -1) {
                        $firstNoteIndexForLevel = $index;
                    }
                    $lastNoteIndexForLevel = $index;
                    $notesInLevel++;
                }
            }

            if ($notesInLevel > 1) {
                $beamNotes[$firstNoteIndexForLevel]->beam->textContent = self::TYPE_BEGIN;
                $beamNotes[$lastNoteIndexForLevel]->beam->textContent = self::TYPE_END;
            }
        }
        return $beamNotes;
    }
}
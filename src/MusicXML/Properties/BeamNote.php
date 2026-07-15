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
     * Finalizes beam types in a set of BeamNote objects, setting the last one of each level to 'end'.
     *
     * @param self[] $beamNotes
     * @return self[]
     */
    public static function closeBeams($beamNotes)
    {
        $numbers = 0;
        foreach($beamNotes as $beamNote)
        {
            if($numbers < $beamNote->beam->number)
            {
                $numbers = $beamNote->beam->number;
            }
        }
        $length = count($beamNotes);
        for($number = $numbers; $number >= 1; $number--)
        {
            for($i = $length -1; $i >= 0; $i--)
            {
                if(isset($beamNotes[$i]) && $beamNotes[$i]->beam->number == $number)
                {
                    $beamNotes[$i]->beam->textContent = self::TYPE_END;
                    break;
                }
            }
        }
        return $beamNotes;
    }
}
<?php

namespace MusicXML\Properties;

use MusicXML\Model\MeasurePartwise;

/**
 * A container class to hold a MeasurePartwise object and its associated note messages.
 *
 * This is used as a data transfer object to pass both the constructed measure and the
 * original note data together between processing steps.
 * 
 * @author Kamshory
 */
class MeasurePartwiseContainer
{
    /**
     * The constructed MeasurePartwise object.
     *
     * @var MeasurePartwise
     */
    private $measurePartwise;

    /**
     * The array of raw note messages for the measure.
     *
     * @var array
     */
    private $noteMessages;

    /**
     * MeasurePartwiseContainer constructor.
     *
     * @param MeasurePartwise $measurePartwise The measure object.
     * @param array $noteMessages The associated note messages.
     */
    public function __construct($measurePartwise, $noteMessages)
    {
        $this->measurePartwise = $measurePartwise;
        $this->noteMessages = $noteMessages;
    }

    /**
     * Gets the MeasurePartwise object.
     *
     * @return  MeasurePartwise
     */ 
    public function getMeasurePartwise()
    {
        return $this->measurePartwise;
    }

    /**
     * Gets the array of note messages.
     *
     * @return  array
     */ 
    public function getNoteMessages()
    {
        return $this->noteMessages;
    }
}
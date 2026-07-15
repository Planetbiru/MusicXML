<?php

namespace MusicXML\Properties;

/**
 * Calculates the optimal `divisions` value for a measure.
 *
 * This class analyzes the durations and start times of all notes within a measure
 * to find the smallest common divisor, which becomes the ideal `divisions` value
 * for accurately representing all rhythms without unnecessary complexity.
 * 
 * @author Kamshory
 */
class MeasureDivision
{
    private $minimum = 0;
    private $maximum = 0;
    private $division = 0;
    private static $divisors = array();
    
    /**
     * MeasureDivision constructor.
     *
     * @param int $timebase The MIDI file's timebase (ticks per quarter note).
     * @param array $notes  An array of note messages from the measure.
     */
    public function __construct($timebase, $notes)
    {
        $arr = array();
        foreach($notes as $note)
        {
            $mod = $note['abstime'] % $timebase;
            if($mod != 0)
            {
                $arr[] = $mod;
            }
            if(isset($note['duration']) && $note['duration'] != 0)
            {
                $arr[] = $note['duration'];
            }
        }
        if(empty($arr))
        {
            $this->minimum = 0;
            $this->maximum = 0;
            $this->division = $timebase; // Default to timebase if no notes to analyze
            return;
        }
        sort($arr);
        $this->minimum = $arr[0];
        $this->maximum = $arr[count($arr) -1];
        $this->division = $this->calculate($timebase, $arr);
    }
    
    /**
     * Finds all divisors of a given integer.
     *
     * @param int $n The integer to find divisors for.
     * @return integer[] An array of divisors.
     */
    private static function getDivisor($n) {
        $arr = array();
        for($i = 1; $i <= $n; $i++) 
        {
            if($n % $i == 0)
            {
                $arr[] = $i;
            }
        }
        return $arr;
    }
    
    /**
     * Calculates the best divisions value based on note timings.
     *
     * It iterates through the divisors of the timebase to find the smallest
     * one that can accurately represent all note start times and durations.
     *
     * @param int   $timebase The MIDI file's timebase.
     * @param array $array    An array of note start times and durations in MIDI ticks.
     * @return int The calculated optimal divisions value.
     */
    private function calculate($timebase, $array)
    {
        if(!isset(self::$divisors[$timebase]))
        {
            self::$divisors[$timebase] = self::getDivisor($timebase);
        }
        $divs = self::$divisors[$timebase];

        $i = 0;
        $factor = $timebase / $divs[$i];
        $arr = $array;
        
        foreach($arr as $idx=>$element)
        {
            if($element % $factor == 0)
            {
                unset($arr[$idx]);
            }
        }
        if(empty($arr))
        {
            return $divs[$i];
        }
        do
        {
            $i++;
            $factor = $timebase / $divs[$i];
            $arr = $array;
            foreach($arr as $idx=>$element)
            {
                if($element % $factor == 0)
                {
                    unset($arr[$idx]);
                }
            }
            if(empty($arr))
            {
                return $divs[$i];
            }
                
        }
        while($factor < $timebase);
        return $timebase;
    }

    /**
     * Gets the calculated optimal divisions value for the measure.
     * @return int
     */ 
    public function getDivision()
    {
        return $this->division;
    }

    /**
     * Gets the minimum note start time or duration found in the measure.
     * @return int
     */ 
    public function getMinimum()
    {
        return $this->minimum;
    }

    /**
     * Gets the maximum note start time or duration found in the measure.
     * @return int
     */ 
    public function getMaximum()
    {
        return $this->maximum;
    }
}
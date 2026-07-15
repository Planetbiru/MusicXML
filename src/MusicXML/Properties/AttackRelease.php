<?php

namespace MusicXML\Properties;

/**
 * A data transfer object representing the attack and release times of a note.
 * These values are typically relative to the start of a measure.
 * 
 * @author Kamshory
 */
class AttackRelease
{
    /**
     * The attack time of the note, typically in beats or a similar metric.
     *
     * @var integer
     */
    private $attack;
    
    /**
     * The release time of the note, typically in beats or a similar metric.
     *
     * @var integer
     */
    private $release;
    
    /**
     * AttackRelease constructor.
     * 
     * @param float $attack The note's starting time.
     * @param float $release The note's ending time.
     */
    public function __construct($attack, $release)
    {
        $this->attack = round($attack);
        $this->release = round($release);
    }

    

    /**
     * Gets the attack time.
     *
     * @return  integer
     */ 
    public function getAttack()
    {
        return $this->attack;
    }

    /**
     * Sets the attack time.
     *
     * @param  integer  $attack  Attack
     *
     * @return  self
     */ 
    public function setAttack($attack)
    {
        $this->attack = $attack;

        return $this;
    }

    /**
     * Gets the release time.
     *
     * @return  integer
     */ 
    public function getRelease()
    {
        return $this->release;
    }

    /**
     * Sets the release time.
     *
     * @param  integer  $release  Release
     *
     * @return  self
     */ 
    public function setRelease($release)
    {
        $this->release = $release;

        return $this;
    }
}
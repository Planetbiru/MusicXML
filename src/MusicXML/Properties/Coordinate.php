<?php

namespace MusicXML\Properties;

/**
 * A data transfer object for storing positional coordinates of a musical element.
 * 
 * @author Kamshory
 */
class Coordinate
{
    /**
     * The absolute X position.
     * 
     * @var float
     */
    public $defaultX;
    
    /**
     * The absolute Y position.
     * 
     * @var float
     */
    public $defaultY;
    
    /**
     * The X position relative to a parent element.
     * 
     * @var string
     */
    public $relativeX;
    
    /**
     * The Y position relative to a parent element.
     * 
     * @var string
     */
    public $relativeY;
    
    /**
     * Returns a string representation of the coordinate object.
     * @return string
     */
    public function __toString()
    {
        return json_encode($this);
    }
}
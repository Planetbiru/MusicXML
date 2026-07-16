<?php

namespace MusicXML\Map;

/**
 * A simple in-memory cache for parsed model data.
 *
 * This class provides a static property to store and retrieve parsed model
 * information, preventing redundant parsing operations.
 *
 * @author Kamshory
 */
class ModelCache
{
    /**
     * The static cache storage. Keys are class names, and values are the parsed model data.
     * @var array
     */
    public static $cache = array();
}
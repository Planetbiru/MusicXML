<?php

namespace MusicXML\Map;

/**
 * Parses model class annotations to extract mapping information.
 *
 * This class is responsible for reflecting on model classes, parsing their
 * docblock annotations, and caching the results for efficient reuse.
 * The current implementation is a placeholder.
 *
 * @author Kamshory
 */
class ModelParser
{
    /**
     * Parses the annotations of a given model class.
     *
     * @param string $className The fully qualified name of the class to parse.
     * @param object $object The object instance (currently unused).
     * @return array
     */
    public static function parseModel($className, $object)
    {
        // parse here
        if(isset(ModelCache::$cache[$className]))
        {
            return ModelCache::$cache[$className];
        }
        
        
        
        return array();
    }
}
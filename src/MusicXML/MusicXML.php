<?php

namespace MusicXML;

use DOMDocument;
use MusicXML\Map\ModelMap;
use MusicXML\Map\NodeType;

/**
 * A high-level class for interacting with MusicXML files.
 *
 * This class provides functionality to load and parse MusicXML files into
 * a structured object model. It serves as a primary entry point for
 * reading existing MusicXML documents.
 * 
 * @author Kamshory
 */
class MusicXML extends MusicXMLBase
{
    /**
     * Load XML
     * @param string $path Path to the XML file
     */
    public function loadXml($path)
    {
        $domdoc = new DOMDocument();
        $domdoc->loadXML(file_get_contents($path));
        $nodes = $domdoc->childNodes;
        $object = null;
        foreach($nodes as $node)
        {
            if($node->nodeType == NodeType::ELEMENT && isset(ModelMap::CLASS_MAP[$node->nodeName]))
            {
                $className = ModelMap::CLASS_MAP[$node->nodeName];
                $object = new $className($node);
                break;
            }
        }
        echo $object;

    }
}

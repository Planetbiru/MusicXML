<?php

namespace MusicXML;

use DOMDocument;
use MusicXML\Map\ModelMap;
use MusicXML\Map\NodeType;

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

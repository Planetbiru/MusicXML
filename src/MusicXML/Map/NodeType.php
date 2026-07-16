<?php

namespace MusicXML\Map;

/**
 * Defines constants for different types of XML nodes.
 *
 * This provides a more readable way to identify node types, similar to
 * the constants available in the DOM extension (e.g., XML_ELEMENT_NODE).
 *
 * @author Kamshory
 */
class NodeType
{
    /**
     * An element node, e.g., `<note>`.
     */
    const ELEMENT = 1;

    /**
     * An attribute node, e.g., `id="P1"`.
     */
    const ATTRIBUTE = 2;

    /**
     * A text node, the content inside an element.
     */
    const TEXT = 3;

    /**
     * A CDATA section node, e.g., `<![CDATA[...]]>`.
     */
    const CDATA_SECTION = 4;
}
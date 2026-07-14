<?php

namespace Midi;

/**
 * Planetbiru Composer - MIDI Validation Utility
 */

class MidiValidator {
    /**
     * Validates if a base64 encoded string is a valid MIDI file.
     * Expects either a raw base64 string or a data:audio/midi;base64,... URI.
     * 
     * @param string $content
     * @return bool
     */
    public static function isValid($content) {
        if (empty($content)) return false;

        // Handle data URI (e.g. data:audio/midi;base64,...)
        if (strpos($content, ',') !== false) {
            $parts = explode(',', $content);
            if (count($parts) < 2) return false;
            $content = $parts[1];
        }

        // Clean base64 string from possible whitespace/newlines
        $cleaned = preg_replace('#\s+#', '', $content);
        $binary = base64_decode($cleaned, true);
        
        if ($binary === false) return false;

        return self::isValidBinary($binary);
    }

    /**
     * Performs structural validation on MIDI binary data.
     * 
     * @param string $data
     * @return bool
     */
    public static function isValidBinary($data) {
        $len = strlen($data);
        // Minimum MIDI file: MThd chunk (14 bytes) + at least one MTrk chunk (8 bytes header + events)
        if ($len < 22) return false;

        // Check MThd signature (Big-Endian "MThd")
        if (substr($data, 0, 4) !== "MThd") return false;

        // Check header size (usually 6 bytes, Big-Endian 32-bit int)
        $headerSizeData = unpack("Nval", substr($data, 4, 4));
        $headerSize = $headerSizeData['val'];
        
        if ($headerSize < 6) return false;
        if ($len < (8 + $headerSize)) return false;

        // Check for at least one MTrk chunk
        $offset = 8 + $headerSize;
        $mtrkFound = false;

        // Iterate through chunks to find MTrk
        while ($offset < $len) {
            if ($offset + 8 > $len) break;
            
            $chunkId = substr($data, $offset, 4);
            $chunkLenData = unpack("Nval", substr($data, $offset + 4, 4));
            $chunkLen = $chunkLenData['val'];
            
            if ($chunkId === "MTrk") {
                $mtrkFound = true;
            }
            
            $offset += 8 + $chunkLen;
            
            // Sanity check to avoid infinite loops or memory issues with garbage data
            if ($offset < 0 || $offset > $len + 8) break;
        }

        return $mtrkFound;
    }
}
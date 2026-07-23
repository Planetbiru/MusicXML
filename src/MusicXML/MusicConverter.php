<?php

namespace MusicXML;

use DAWProject\DAWProjectFromMIDI;
use DAWProject\DAWProjectToMIDI;
use Exception;
use MusicXML\Util\MXL;
use SimpleXMLElement;

/**
 * A high-level facade for converting MIDI or MusicXML data into sheet music.
 *
 * This class orchestrates the conversion process from a source format (MIDI or MusicXML)
 * into a visual representation like PDF or SVG. It simplifies complex operations,
 * such as part detection, rendering, and layout, into straightforward method calls.
 *
 * Basic Usage:
 * ```php
 * require 'vendor/autoload.php';
 * use MusicXML\MusicConverter;
 *
 * $converter = new MusicConverter();
 * $pdfContent = $converter->midiToPDF(file_get_contents('song.mid'), 'My Song');
 * file_put_contents('song.pdf', $pdfContent);
 * ```
 * 
 * **Public Methods**
 * - midiToMusicXML
 * - musicXMLToMIDI
 * - midiToDAWProject
 * - dawProjectToMidi
 * - midiToPDF
 * - musicXMLToPDF
 * - dawProjectToPDF
 * - midiToSVG
 * - musicXMLToSVG
 * - dawProjectToSVG
 * 
 * @author Kamshory
 */
class MusicConverter
{
    /**
     * The font family to use for text rendering (e.g., 'Times', 'Arial').
     * 
     * @var string
     */
    private $fontFamily = 'Times';

    /**
     * The target output format ('pdf' or 'svg').
     * 
     * @var string
     */
    private $format = 'pdf';

    /**
     * If true, consecutive empty measures will be collapsed into a single multi-measure rest.
     * 
     * @var bool
     */
    private $compressEmptyMeasures = false;

    /**
     * If true, tempo change markings will be displayed on the score.
     * 
     * @var bool
     */
    private $showTempoChanges = true;

    /**
     * Flag to determine which rest filling method to use. If true, uses an alternative algorithm for filling gaps with rests.
     * 
     * @var bool
     */
    private $useRestFilling = false;

    /**
     * The font size for lyrics, in points.
     * 
     * @var float
     */
    private $lyricFontSize = 6.0;

    /**
     * The vertical height of a single staff system in millimeters, including space for lyrics.
     * @var int
     */
    private $systemHeight = 28;

    /**
     * Maps a pitch class (0-11) to its diatonic step index within an octave.
     * Used for calculating a note's vertical position on the staff.
     * 
     * @var int[]
     */
    private $pitchClasses = array(
        0 => 0,  // C
        1 => 0,  // C#
        2 => 1,  // D
        3 => 1,  // D#
        4 => 2,  // E
        5 => 3,  // F
        6 => 3,  // F#
        7 => 4,  // G
        8 => 4,  // G#
        9 => 5,  // A
        10 => 5,  // A#
        11 => 6   // B
    );

    private $mobile = false;
    private $drawBeam = false;

    /**
     * MusicConverter constructor.
     *
     * @param bool  $compressEmptyMeasures If true, consecutive empty measures are collapsed into a single multi-measure rest.
     * @param bool  $showTempoChanges      If true, tempo change markings (e.g., "Tempo: = 120") are displayed on the score.
     * @param bool  $useRestFilling        If true, uses an alternative algorithm for filling gaps with rests.
     * @param float $lyricFontSize         The font size for lyrics, in points.
     * @param int   $systemHeight          The vertical height of a single staff system in millimeters, including space for lyrics.
     * @param bool  $drawBeam              If true, draw beam
     */
    public function __construct($compressEmptyMeasures = false, $showTempoChanges = true, $useRestFilling = false, $lyricFontSize = 6.0, $systemHeight = 28, $drawBeam = false)
    {
        $this->compressEmptyMeasures = $compressEmptyMeasures;
        $this->showTempoChanges = $showTempoChanges;
        $this->useRestFilling = $useRestFilling;
        $this->lyricFontSize = $lyricFontSize;
        $this->systemHeight = $systemHeight;
        $this->drawBeam = $drawBeam;
    }

    /**
     * Converts MIDI data into a .dawproject file format.
     *
     * This method takes binary MIDI data and converts it into a ZIP archive
     * containing `project.xml` and `metadata.xml`, which is compatible with
     * DAWs like Bitwig Studio.
     *
     * @param string $midiData The binary content of the MIDI file.
     * @return string The binary content of the generated .dawproject (ZIP) file.
     */
    public function midiToDAWProject($midiData)
    {
        $converter = new DAWProjectFromMIDI();
        return $converter->convert($midiData);
    }

    /**
     * Converts a .dawproject file back into MIDI data.
     *
     * This method reads a .dawproject ZIP archive, parses its contents,
     * and reconstructs the corresponding binary MIDI data.
     *
     * @param string $dawProjectData The binary content of the .dawproject file.
     * @return string The binary content of the generated MIDI file.
     */
    public function dawProjectToMIDI($dawProjectData)
    {
        $converter = new DAWProjectToMIDI();
        return $converter->convert($dawProjectData);
    }

    /**
     * Converts a compressed MusicXML (.mxl) file into an uncompressed MusicXML string.
     *
     * @param string $mxl The binary content of the .mxl file.
     * @return string The uncompressed MusicXML content as a string.
     */
    public function mxlToXML($mxl) 
    {
        return (new MXL())->mxlToXML($mxl);
    }

    /**
     * Converts a compressed MusicXML (.mxl) file into a binary MIDI data string.
     *
     * @param string $mxl The binary content of the .mxl file.
     * @return string The binary MIDI data.
     */
    public function mxlToMIDI($mxl)
    {
        return $this->musicXMLToMIDI($this->mxlToXML($mxl));
    }

    /**
     * Converts a compressed MusicXML (.mxl) file into a PDF file.
     *
     * @param string $mxl The binary content of the .mxl file.
     * @param string $songTitle The title to be displayed on the sheet music.
     * @param string $composer The composer's name to be displayed.
     * @param int|string|null $targetChannelOrPartId The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
     * @param bool $showLyric If true, forces lyrics to be displayed if they exist in the selected part.
     * @param string $year The year of the score.
     * @return string Raw PDF data string.
     */
    public function mxlToPDF($mxl, $songTitle = "Untitled", $composer = "Unknown", $targetChannelOrPartId = null, $showLyric = false, $year = null)
    {
        return $this->musicXMLToPDF($this->mxlToXML($mxl), $songTitle, $composer, $targetChannelOrPartId, $showLyric, $year);
    }

    /**
     * Converts a compressed MusicXML (.mxl) file into an SVG image.
     *
     * @param string $mxl The binary content of the .mxl file.
     * @param string $songTitle The title to be displayed on the sheet music.
     * @param string $composer The composer's name to be displayed.
     * @param int|string|null $targetChannelOrPartId The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
     * @param bool $showLyric If true, forces lyrics to be displayed if they exist in the selected part.
     * @param bool $singlePage If true, generates a single continuous SVG. If false, generates stacked, page-like layouts within one SVG.
     * @param bool $mobile If true, optimizes the layout for mobile devices by rendering one measure per system.
     * @return string Raw SVG data string.
     */
    public function mxlToSVG($mxl, $songTitle = "Untitled", $composer = "Unknown", $targetChannelOrPartId = null, $showLyric = false, $singlePage = true, $mobile = false)
    {
        return $this->musicXMLToSVG($this->mxlToXML($mxl), $songTitle, $composer, $targetChannelOrPartId, $showLyric, $singlePage, $mobile);
    }

    /**
     * Converts MIDI data into a MusicXML string.
     *
     * This is a direct conversion method that produces the raw MusicXML content,
     * which can then be used for further processing or saved to a file.
     *
     * @param string $midiData  The binary content of a MIDI file.
     * @param string $songTitle The title for the musical work.
     * @param string $version   The MusicXML version to use (e.g., "4.0").
     * @param string $format    The output format, either 'xml' (default) or 'mxl' (compressed).
     * @return string The generated MusicXML or MXL content as a string.
     * @throws Exception If the MIDI data is invalid.
     */
    public function midiToMusicXML($midiData, $songTitle = "Untitled", $version = "4.0", $format = "musicxml")
    {
        if (empty($midiData)) {
            throw new Exception("Invalid input MIDI data.");
        }
        $converter = new MusicXMLFromMIDI();
        $converter->setUseRestFilling($this->useRestFilling);
        $midi = $converter->loadMidiString($midiData);
        $xmlStr = $converter->midiToMusicXML($midi, $songTitle, $version, $format);
        return $xmlStr;
    }

    /**
     * Converts a MusicXML string into a binary MIDI data string.
     *
     * @param string $musicXmlContent The MusicXML content as a string.
     * @return string The binary MIDI data.
     * @throws Exception if the MusicXML content is invalid or cannot be parsed.
     */
    public function musicXMLToMIDI($musicXmlContent)
    {
        $toMidiConverter = new MusicXMLToMIDI();
        return $toMidiConverter->fromXmlString($musicXmlContent);
    }

    /**
     * Converts MIDI data into a PDF file.
     *
     * This method handles the full pipeline: MIDI -> MusicXML -> PDF. It automatically
     * detects the most suitable part to render unless a specific channel or part is specified.
     *
     * @param string          $midiData              The binary content of a MIDI file.
     * @param string          $songTitle             The title to be displayed on the sheet music.
     * @param string          $composer              The composer's name to be displayed.
     * @param int|string|null $targetChannelOrPartId The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
     * @param int|null        $mainMelody            The MIDI channel number (1-16) considered to be the main melody, used to prioritize lyric display.
     * @param string          $year                  The year of the score.
     * @return string Raw PDF data string
     * @throws Exception
     */
    public function midiToPDF($midiData, $songTitle = "Untitled", $composer = "Unknown", $targetChannelOrPartId = null, $mainMelody = 3, $year = null)
    {
        if (empty($midiData)) {
            throw new Exception("Invalid input MIDI data.");
        }

        $this->format = 'pdf';

        // 1. Convert MIDI to MusicXML content using the PHP converter
        $converter = new MusicXMLFromMIDI();
        $converter->setUseRestFilling($this->useRestFilling);
        $midi = $converter->loadMidiString($midiData);
        $xmlStr = $converter->midiToMusicXML($midi, $songTitle);
        $showLyric = in_array($mainMelody, $midi->getMidiChannels());

        return $this->musicXMLToPDF($xmlStr, $songTitle, $composer, $targetChannelOrPartId, $showLyric, $year);
    }

    /**
     * Converts a MusicXML string into a PDF file.
     *
     * @param string          $xmlStr                The string content of a MusicXML file.
     * @param string          $songTitle             The title to be displayed on the sheet music.
     * @param string          $composer              The composer's name to be displayed.
     * @param int|string|null $targetChannelOrPartId The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
     * @param bool            $showLyric             If true, forces lyrics to be displayed if they exist in the selected part.
     * @param string          $year                  The year of the score.
     * @return string Raw PDF data string
     * @throws Exception
     */
    public function musicXMLToPDF($xmlStr, $songTitle = "Untitled", $composer = "Unknown", $targetChannelOrPartId = null, $showLyric = false, $year = null)
    {
        if (empty($xmlStr)) {
            throw new Exception("Invalid input MusicXML data.");
        }

        list($xml, $partId, $tempoMap) = $this->getPartAndTempoMap($xmlStr, $targetChannelOrPartId, $this->showTempoChanges);

        // 4. Render the part to PDF
        return $this->renderPartToPDF($xml, $partId, $songTitle, $composer, $tempoMap, $showLyric, $year);             
    }

    /**
     * Converts MIDI data into an SVG image.
     *
     * This method handles the full pipeline: MIDI -> MusicXML -> SVG. The resulting SVG is interactive,
     * with `data-*` attributes on notes and measures for easy synchronization with an audio player.
     *
     * @param string          $midiData              The binary content of a MIDI file.
     * @param string          $songTitle             The title to be displayed on the sheet music.
     * @param string          $composer              The composer's name to be displayed.
     * @param int|string|null $targetChannelOrPartId The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
     * @param int|null        $mainMelody            The MIDI channel number (1-16) considered to be the main melody, used to prioritize lyric display.
     * @param bool            $singlePage            If true, generates a single continuous SVG. If false, generates stacked, page-like layouts within one SVG.
     * @param bool            $mobile                If true, optimizes the layout for mobile devices by rendering one measure per system.
     * @return string Raw SVG data string
     * @throws Exception
     */
    public function midiToSVG($midiData, $songTitle = "Untitled", $composer = "Unknown", $targetChannelOrPartId = null, $mainMelody = 3, $singlePage = true, $mobile = false)
    {
        if (empty($midiData)) {
            throw new Exception("Invalid input MIDI data.");
        }

        $this->format = 'svg';
        $this->mobile = $mobile;

        // 1. Convert MIDI to MusicXML content using the PHP converter
        $converter = new MusicXMLFromMIDI();
        $converter->setUseRestFilling(false); // Enable rest filling for better measure representation
        $midi = $converter->loadMidiString($midiData);
        $xmlStr = $converter->midiToMusicXML($midi, $songTitle);
        $showLyric = in_array($mainMelody, $midi->getMidiChannels());
        
        return $this->musicXMLToSVG($xmlStr, $songTitle, $composer, $targetChannelOrPartId, $showLyric, $singlePage, $mobile);
    }

    /**
     * Converts a MusicXML string into an SVG image.
     *
     * The resulting SVG is interactive, with `data-*` attributes on notes and measures
     * for easy synchronization with an audio player.
     *
     * @param string          $xmlStr                The string content of a MusicXML file.
     * @param string          $songTitle             The title to be displayed on the sheet music.
     * @param string          $composer              The composer's name to be displayed.
     * @param int|string|null $targetChannelOrPartId The specific MIDI channel (1-16) or MusicXML part ID (e.g., "P1") to render. If null, the best part is auto-detected.
     * @param bool            $showLyric             If true, forces lyrics to be displayed if they exist in the selected part.
     * @param bool            $singlePage            If true, generates a single continuous SVG. If false, generates stacked, page-like layouts within one SVG.
     * @param bool            $mobile                If true, optimizes the layout for mobile devices by rendering one measure per system.
     * @return string Raw SVG data string
     * @throws Exception
     */
    public function musicXMLToSVG($xmlStr, $songTitle = "Untitled", $composer = "Unknown", $targetChannelOrPartId = null, $showLyric = false, $singlePage = true, $mobile = false)
    {
        $this->mobile = $mobile;

        if (empty($xmlStr)) {
            throw new Exception("Invalid input MusicXML data.");
        }

        list($xml, $partId, $tempoMap) = $this->getPartAndTempoMap($xmlStr, $targetChannelOrPartId, $this->showTempoChanges); //NOSONAR

        // 4. Render the part to SVG
        return $this->renderPartToSVG($xml, $partId, $songTitle, $composer, $tempoMap, $showLyric, $singlePage);
    }

    /**
     * Converts a .dawproject file into a PDF file.
     *
     * This method first converts the .dawproject data into an intermediate MIDI format,
     * and then renders that MIDI data to a PDF.
     *
     * @param string $dawProjectData The binary content of the .dawproject file.
     * @param string $songTitle The title to be displayed on the sheet music.
     * @param string $composer The composer's name to be displayed.
     * @param int|string|null $targetChannelOrPartId The specific MIDI channel (1-16) or part ID to render.
     * @param string $year The year of the score.
     * @return string Raw PDF data string.
     */
    public function dawProjectToPDF($dawProjectData, $songTitle = "Untitled", $composer = "Unknown", $targetChannelOrPartId = null, $year = null) {
        $this->format = 'pdf';
        $converter1 = new DAWProjectFromMIDI();
        $midiData = $converter1->convert($dawProjectData);
        return $this->midiToPDF($midiData, $songTitle, $composer, $targetChannelOrPartId, null, $year);
    }
    /**
     * Converts a .dawproject file into an SVG image.
     *
     * This method first converts the .dawproject data into an intermediate MIDI format,
     * and then renders that MIDI data to an SVG.
     *
     * @param string $dawProjectData The binary content of the .dawproject file.
     * @param string $songTitle The title to be displayed on the sheet music.
     * @param string $composer The composer's name to be displayed.
     * @param int|string|null $targetChannelOrPartId The specific MIDI channel (1-16) or part ID to render.
     * @param bool $singlePage If true, generates a single continuous SVG. If false, generates stacked pages.
     * @param bool $mobile If true, optimizes the layout for mobile devices by rendering one measure per system.
     * @return string Raw SVG data string.
     */
    public function dawProjectToSVG($dawProjectData, $songTitle = "Untitled", $composer = "Unknown", $targetChannelOrPartId = null, $singlePage = true, $mobile = false)
    {
        $this->format = 'svg';
        $this->mobile = $mobile;
        $converter1 = new DAWProjectFromMIDI();
        $midiData = $converter1->convert($dawProjectData);
        return $this->midiToSVG($midiData, $songTitle, $composer, $targetChannelOrPartId, null, $singlePage);
    }

    /**
     * Parses MusicXML, selects the most appropriate part, and extracts the tempo map.
     *
     * @param string          $xmlStr                The MusicXML string content.
     * @param int|string|null $targetChannelOrPartId The desired channel (1-16) or part ID (e.g., "P1").
     * @param bool            $showTempoChanges      If true, extracts all tempo changes. If false, only the initial tempo is used.
     * @return array An array containing the SimpleXMLElement, the resolved part ID, and the tempo map.
     * @throws Exception If the MusicXML content is invalid.
     */
    private function getPartAndTempoMap($xmlStr, $targetChannelOrPartId, $showTempoChanges)
    {
        // 2. Parse the MusicXML content
        $xml = simplexml_load_string($xmlStr);
        if ($xml === false) {
            throw new Exception("Failed to parse generated MusicXML content.");
        }

        // 3. Resolve Part ID from target channel or part ID
        $partId = null;
        if ($targetChannelOrPartId !== null) {
            if (is_numeric($targetChannelOrPartId)) {
                $targetChannel = (int)$targetChannelOrPartId;
                foreach ($xml->{'part-list'}->{'score-part'} as $scorePart) {
                    $partIdVal = (string)$scorePart['id'];
                    foreach ($scorePart->{'midi-instrument'} as $midiInst) { //NOSONAR
                        if (isset($midiInst->{'midi-channel'}) && (int)$midiInst->{'midi-channel'} === $targetChannel) {
                            $partId = $partIdVal;
                            break 2;
                        }
                    }
                }
                // Fallback: if not matched inside midi-instrument channels, try P<channel>
                if ($partId === null) {
                    $partId = 'P' . $targetChannel;
                }
            } else {
                $partId = $targetChannelOrPartId;
            }
        }

        if ($partId === null || !$this->partExists($xml, $partId)) {
            $partId = $this->detectBestPart($xml);
        }

        if ($this->partExists($xml, $partId) && $this->hasLyricsInPart($xml, $partId) === false) {
            $lyricPartId = $this->findPartWithLyrics($xml);
            if ($lyricPartId !== null) {
                $partId = $lyricPartId;
            }
        }

        $tempoMap = array();
        if (isset($xml->part)) {
            foreach ($xml->part as $part) {
                if (isset($part->measure)) {
                    $mIdx = 0;
                    foreach ($part->measure as $measure) {
                        if (isset($measure->direction)) {
                            foreach ($measure->direction as $direction) {
                                if (isset($direction->sound) && isset($direction->sound['tempo'])) {
                                    $tempoMap[$mIdx] = round((float)$direction->sound['tempo']);
                                }
                                if (isset($direction->{'direction-type'}->metronome->{'per-minute'})) {
                                    $tempoMap[$mIdx] = round((float)$direction->{'direction-type'}->metronome->{'per-minute'});
                                }
                            }
                        }
                        $mIdx++;
                    }
                }
            }
        }

        $firstTempo = 120;
        foreach ($tempoMap as $m => $t) {
            $firstTempo = $t;
            break;
        }
        if (!isset($tempoMap[0])) {
            $tempoMap[0] = $firstTempo;
        }

        if (!$showTempoChanges) {
            $tempoMap = array(0 => $tempoMap[0]);
        }

        return array($xml, $partId, $tempoMap);
    }

    /**
     * Checks if a part with the given ID exists in the MusicXML data.
     *
     * @param SimpleXMLElement $xml The parsed MusicXML object.
     * @param string|null $partId The part ID to check.
     * @return bool True if the part exists, false otherwise.
     */
    private function partExists($xml, $partId)
    {
        if ($partId === null || $partId === '') {
            return false;
        }

        foreach ($xml->part as $part) {
            if ((string)$part['id'] === $partId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a specific part contains any lyric elements.
     *
     * @param SimpleXMLElement $xml The parsed MusicXML object.
     * @param string $partId The ID of the part to check.
     * @return bool True if lyrics are found, false otherwise.
     */
    private function hasLyricsInPart($xml, $partId)
    {
        foreach ($xml->part as $part) {
            if ((string)$part['id'] !== $partId) {
                continue;
            }

            foreach ($part->measure as $measure) {
                foreach ($measure->note as $note) {
                    $lyricText = $this->getNoteLyricText($note);
                    if ($lyricText !== null && $lyricText !== '') {
                        return true;
                    }
                }
            }
            break;
        }

        return false;
    }

    /**
     * Finds the first part in the MusicXML data that contains lyrics.
     *
     * @param SimpleXMLElement $xml The parsed MusicXML object.
     * @return string|null The ID of the first part with lyrics, or null if none are found.
     */
    private function findPartWithLyrics($xml)
    {
        foreach ($xml->part as $part) {
            foreach ($part->measure as $measure) {
                foreach ($measure->note as $note) {
                    $lyricText = $this->getNoteLyricText($note);
                    if ($lyricText !== null && $lyricText !== '') {
                        return (string)$part['id'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Detects the most suitable part to render from the MusicXML data.
     *
     * The logic prioritizes the part with the most lyric events. If no parts have lyrics,
     * it falls back to the part with the highest number of note events.
     *
     * @param SimpleXMLElement $xml The parsed MusicXML object.
     * @return string Part ID
     */
    private function detectBestPart($xml)
    {
        $bestPartId = 'P1';
        $maxLyrics = -1;
        $maxNotes = -1;
        
        foreach ($xml->part as $part) {
            $partId = (string)$part['id'];
            $lyricsCount = 0;
            $notesCount = 0;
            foreach ($part->measure as $measure) {
                foreach ($measure->note as $note) {
                    $lyricText = $this->getNoteLyricText($note);
                    if ($lyricText !== null && $lyricText !== '') {
                        $lyricsCount++;
                    }
                    if (isset($note->pitch) || isset($note->unpitched)) {
                        $notesCount++;
                    }
                }
            }
            
            if ($lyricsCount > $maxLyrics) {
                $maxLyrics = $lyricsCount;
                $bestPartId = $partId;
            }
            if ($lyricsCount == 0 && $notesCount > $maxNotes) {
                $maxNotes = $notesCount;
                if ($maxLyrics <= 0) {
                    $bestPartId = $partId;
                }
            }
        }
        
        return $bestPartId;
    }

    /**
     * Extracts the text content from a <lyric> element within a <note>.
     *
     * @param SimpleXMLElement $note The <note> element.
     * @return string|null The combined lyric text, or null if no lyrics are found.
     */
    private function getNoteLyricText($note)
    {
        if (!isset($note->lyric)) {
            return null;
        }

        $lyrics = $note->lyric;
        if (!is_array($lyrics) && !($lyrics instanceof SimpleXMLElement)) {
            return null;
        }

        if (!is_array($lyrics)) {
            $lyrics = array($lyrics);
        }

        foreach ($lyrics as $lyricNode) {
            if (!($lyricNode instanceof SimpleXMLElement)) {
                continue;
            }

            $textParts = array();
            if (isset($lyricNode->text)) {
                foreach ($lyricNode->text as $textNode) {
                    $textValue = trim((string)$textNode);
                    if ($textValue !== '') {
                        $textParts[] = $textValue;
                    }
                }
            }

            if (!empty($textParts)) {
                return implode(' ', $textParts);
            }

            $textValue = trim((string)$lyricNode);
            if ($textValue !== '') {
                return $textValue;
            }
        }

        return null;
    }

    /**
     * Renders the selected part to a PDF file.
     *
     * @param SimpleXMLElement $xml       The parsed MusicXML object.
     * @param string           $partId    The ID of the part to render.
     * @param string           $songTitle The title for the score.
     * @param string           $composer  The composer's name for the score.
     * @param array            $tempoMap  An associative array mapping measure indices to BPM values.
     * @param bool             $showLyric If true, lyrics will be rendered if present.
     * @param string           $year      The year of the score.
     * @return string Raw PDF data string
     * @throws Exception
     */
    private function renderPartToPDF($xml, $partId, $songTitle, $composer, $tempoMap = array(), $showLyric = false, $year = null)
    {
        if(!isset($year))
        {
            $year = date('Y');
        }

        $pdf = new SheetMusicPDF('P', 'mm', 'A4');
        $pdf->composer = $composer;
        $pdf->year = $year;
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();


        $this->renderPart($pdf, $xml, $partId, $songTitle, $composer, $tempoMap, $showLyric);
        return $pdf->Output('S');
    }

    /**
     * Renders the selected part to an SVG string.
     *
     * @param SimpleXMLElement $xml        The parsed MusicXML object.
     * @param string           $partId     The ID of the part to render.
     * @param string           $songTitle  The title for the score.
     * @param string           $composer   The composer's name for the score.
     * @param array            $tempoMap   An associative array mapping measure indices to BPM values.
     * @param bool             $showLyric  If true, lyrics will be rendered if present.
     * @param bool             $singlePage If true, generates a single continuous SVG.
     * @param string           $year       The year of the score.
     * @return string Raw SVG data string.
     */
    private function renderPartToSVG($xml, $partId, $songTitle, $composer, $tempoMap = array(), $showLyric = false, $singlePage = true, $year = null)
    {
        if(!isset($year))
        {
            $year = date('Y');
        }

        $pdf = new SheetMusicSVG('P', 'mm', 'A4', $singlePage, $this->mobile);
        $pdf->composer = $composer;
        $pdf->year = $year;
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(false);

        $this->renderPart($pdf, $xml, $partId, $songTitle, $composer, $tempoMap, $showLyric);
        return $pdf->Output(); //NOSONAR
    }

    /**
     * Renders a single part from the MusicXML data onto the provided PDF or SVG canvas.
     * This is the core rendering engine that iterates through measures and notes.
     *
     * @param SheetMusicPDF|SheetMusicSVG $pdf       The rendering canvas object (FPDF or SVG wrapper).
     * @param SimpleXMLElement            $xml       The parsed MusicXML data.
     * @param string                      $partId    The ID of the part to render.
     * @param string                      $songTitle The title of the song.
     * @param string                      $composer  The composer of the song.
     * @param array                       $tempoMap  An associative array mapping measure indices to BPM values.
     * @param bool                        $showLyric A flag to control lyric rendering.
     * @return SheetMusicPDF|SheetMusicSVG The canvas object after rendering.
     */
    private function renderPart($pdf, $xml, $partId, $songTitle, $composer, $tempoMap = array(), $showLyric = false)
    {
        $systemStartX = 0;
        $systemStartY = 0;
        $systemAttributesIndex = 0;
        // Find part element and part name
        $targetPart = null;
        foreach ($xml->part as $part) {
            if ((string)$part['id'] === $partId) {
                $targetPart = $part;
                break;
            }
        }

        if ($targetPart === null) {
            throw new Exception("Part $partId not found in MusicXML.");
        }

        // Get part display name
        $partNameStr = "Score";
        foreach ($xml->{'part-list'}->{'score-part'} as $scorePart) {
            if ((string)$scorePart['id'] === $partId) {
                $partNameStr = (string)$scorePart->{'part-name'};
                break;
            }
        }

        // Draw title section on first page
        $pdf->SetFont($this->fontFamily, 'B', 18);
        $pdf->Cell(0, 10, $songTitle, 0, 1, 'C');
        $pdf->SetFont($this->fontFamily, 'I', 12);
        $pdf->Cell(0, 6, $partNameStr, 0, 1, 'C');
        $pdf->SetFont($this->fontFamily, '', 10);
        $pdf->Cell(0, 5, $composer, 0, 1, 'C');
        $pdf->Ln(5);

        // Start the main page group for SVG
        if ($pdf instanceof SheetMusicSVG) {
            $pdf->startGroup(array('data-page-number' => $pdf->PageNo()));
        }

        // Detect if the target part has lyrics
        $hasLyrics = false;
        foreach ($targetPart->measure as $measure) {
            foreach ($measure->note as $note) {
                if (isset($note->lyric)) {
                    $hasLyrics = true;
                    break 2;
                }
            }
        }

        // Grid parameters
        $tempoXOffset = -8.8; // shift tempo text slightly left to avoid overlapping with barline
        $tempoYOffset = -7; // 
        $systemX = 12; // horizontal start on page 1
        $systemY = 40; // vertical start on page 1
        $printableWidth = 185; // A4 width 210mm - 15mm left margin - 11mm right margin

        if($this->mobile)
        {
            $printableWidth = 92.5; // A5
            $systemX = 6;
        }

        $lineSpacing = 2; // distance between staff lines in mm

        // Default attributes
        $divisions = 4;
        $fifths = 0;
        $beats = 4;
        $beatType = 4;
        $tieStartX = 9; // X offset for tie start point relative to systemX
        
        // Detect if it is percussion track
        $isPercussion = (stripos($partNameStr, 'drum') !== false || stripos($partNameStr, 'percussion') !== false);

        $elevation = $this->format == 'pdf' ? 15 : -15; // Elevation for note heads in SVG is inverted compared to PDF
        $tempoTextOffset = $this->format == 'pdf' ? -4 : -3; // Fix text offset

        if ($this->mobile) {
            $measuresPerSystem = 1;
        } else {
            if ($hasLyrics || $isPercussion) {
                $measuresPerSystem = 2;
            } else {
                $measuresPerSystem = 3;
            }
        }
        $measures = $targetPart->measure;
        $totalMeasures = count($measures);

        $measureLayoutIdx = array();
        $collapseCount = array();
        $currLayoutIdx = 0;
        for ($i = 0; $i < $totalMeasures; $i++) {
            $measureLayoutIdx[$i] = $currLayoutIdx;
            
            $c = 1;
            if ($this->compressEmptyMeasures) {
                // Check if measure $i is blank
                $isBlank = true;
                if (isset($measures[$i]->note)) {
                    foreach ($measures[$i]->note as $note) {
                        if (!isset($note->rest)) {
                            $isBlank = false;
                            break;
                        }
                    }
                }
                
                if ($isBlank) {
                    while ($i + $c < $totalMeasures) {
                        $nextMeasure = $measures[$i + $c];
                        $nextIsBlank = true;
                        if (isset($nextMeasure->note)) {
                            foreach ($nextMeasure->note as $note) {
                                if (!isset($note->rest)) {
                                    $nextIsBlank = false;
                                    break;
                                }
                            }
                        }
                        if (!$nextIsBlank) {
                            break;
                        }
                        if (isset($nextMeasure->attributes)) {
                            break;
                        }
                        $c++;
                    }
                }
            }
            
            if ($c > 1) {
                $collapseCount[$i] = $c;
                for ($j = 0; $j < $c; $j++) {
                    $measureLayoutIdx[$i + $j] = $currLayoutIdx;
                }
                $i += $c - 1;
            } else {
                $collapseCount[$i] = 1;
            }
            $currLayoutIdx++;
        }

        $activeTies = array();
        $clefSign = 'G'; // default clef sign is Treble (G)
        $tieQueue = array(); // Antrian untuk menunda penggambaran tie
        $tickAccumulator = 0; // Akumulator untuk start-tick yang akurat

        for ($mIdx = 0; $mIdx < $totalMeasures; $mIdx++) {
            $measure = $measures[$mIdx];
            
            // Check attributes if defined in this measure
            if (isset($measure->attributes)) {
                if (isset($measure->attributes->divisions)) {
                    $divisions = (int)$measure->attributes->divisions;
                }
                if (isset($measure->attributes->key->fifths)) {
                    $fifths = (int)$measure->attributes->key->fifths;
                }
                if (isset($measure->attributes->time->beats)) {
                    $beats = (int)$measure->attributes->time->beats;
                }
                if (isset($measure->attributes->time->{'beat-type'})) {
                    $beatType = (int)$measure->attributes->time->{'beat-type'};
                }
                if (isset($measure->attributes->clef->sign)) {
                    $clefSign = (string)$measure->attributes->clef->sign;
                    $isPercussion = ($clefSign === 'percussion');
                }
            }

            $measureDuration = $beats * $divisions;
            if ($measureDuration <= 0) {
                $measureDuration = 16;
            }

            $layoutIdx = $measureLayoutIdx[$mIdx];

            // Indent for clef and signatures at the start of each system
            $systemStartIndent = ($layoutIdx < $measuresPerSystem) ? 16 : 12;
            $measureWidth = ($printableWidth - $systemStartIndent) / $measuresPerSystem;


            // Start of a new system
            if ($layoutIdx % $measuresPerSystem == 0) {
                // Outgoing ties (first half) crossing into this system were already drawn
                // immediately when the tie-start note was processed (see line ~991).
                // No duplicate drawing needed here.

                // Check if we need to break to a new page
                $isMultiPageSvg = ($pdf instanceof SheetMusicSVG && !$pdf->isSinglePageMode());
                if (($pdf instanceof SheetMusicPDF || $isMultiPageSvg) && ($systemY + $this->systemHeight > 265)) {
                    $pdf->AddPage();
                    // For multi-page SVG, start a new page group
                    if ($isMultiPageSvg) {
                        $pdf->startGroup(array('data-page-number' => $pdf->PageNo()));
                    }
                    $systemY = 40; // Reset system Y for the new page
                }

                // Draw 5 staff lines from systemX to systemX + printableWidth
                $pdf->SetDrawColor(180, 180, 180);
                $pdf->SetLineWidth(0.15);
                for ($line = 0; $line < 5; $line++) {
                    $ly = $systemY + $line * $lineSpacing;
                    $pdf->Line($systemX, $ly, $systemX + $printableWidth, $ly, 'sheet-music-line');
                }

                if ($pdf instanceof SheetMusicSVG) {
                    $pdf->startGroup(array(
                        'data-system-number' => floor($layoutIdx / $measuresPerSystem) + 1,
                        'data-start-tick' => $tickAccumulator
                    ));
                    // Placeholder for system attributes
                    $systemStartX = $systemX;
                    $systemStartY = $systemY - 8; // Approx top of staff
                    $systemEndX = 0; // Will be updated
                    $systemEndY = 0; // Will be updated
                    $systemAttributesIndex = count($pdf->getPageContent($pdf->PageNo() -1)) -1;

                }

                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetLineWidth(0.2);

                // Draw Clef
                if ($isPercussion) {
                    $pdf->DrawPercussionClef($systemX, $systemY);
                } elseif ($clefSign === 'F') {
                    $pdf->DrawBassClef($systemX, $systemY);
                } elseif ($clefSign === 'C') {
                    $pdf->DrawAltoClef($systemX, $systemY);
                } else {
                    $pdf->DrawTrebleClef($systemX, $systemY);
                }

                // Draw Key Signature (flats/sharps)
                if ($fifths == -1 && !$isPercussion) {
                    if ($clefSign === 'F') {
                        // 1 flat (Bb2) on second line from bottom
                        $pdf->DrawFlat($systemX + 9, $systemY + 6.0);
                    } elseif ($clefSign === 'C') {
                        // 1 flat (Bb3) on second space from bottom (space 3 from bottom)
                        $pdf->DrawFlat($systemX + 9, $systemY + 5.0);
                    } else {
                        // 1 flat (Bb4) on middle line
                        $pdf->DrawFlat($systemX + 9, $systemY + 4.0);
                    }
                } elseif ($fifths == 1 && !$isPercussion) {
                    if ($clefSign === 'F') {
                        // 1 sharp (F#3) on fourth line from bottom
                        $pdf->DrawSharp($systemX + 9, $systemY + 2.0);
                    } elseif ($clefSign === 'C') {
                        // 1 sharp (F#4) on second space from top (space 1 from top)
                        $pdf->DrawSharp($systemX + 9, $systemY + 1.0);
                    } else {
                        // 1 sharp (F#5) on top line
                        $pdf->DrawSharp($systemX + 9, $systemY);
                    }
                }

                // Draw Time Signature (first measure of page or song)
                if ($layoutIdx == 0) {
                    $pdf->SetFont($this->fontFamily, 'B', 10);
                    $pdf->SetXY($systemX + 15, $systemY);
                    $pdf->Cell(-3, 4, $beats, 0, 0, 'C');
                    $pdf->SetXY($systemX + 15, $systemY + 4);
                    $pdf->Cell(-3, 4, $beatType, 0, 0, 'C');
                }
            }

            // Calculate current measure start coordinate
            $currentMeasureX = $systemX + $systemStartIndent + (($layoutIdx % $measuresPerSystem) * $measureWidth);

            // Start measure group for SVG
            if ($pdf instanceof SheetMusicSVG) {
                $pdf->startGroup(array(
                    'data-measure-number' => $mIdx + 1, 
                    'data-start-tick' => $tickAccumulator,
                    'x' => round($currentMeasureX, 2),
                    'y' => round($systemY - 8, 2), // Approx top of staff
                    'width' => round($measureWidth, 2),
                    'height' => round($this->systemHeight, 2) // Approx height of staff + lyrics
                ));
            }

            // Dapatkan timebase (PPQN) dari MIDI. Kita asumsikan ini konsisten.
            // Ambil dari measure pertama jika belum ada.
            if (!isset($timebase)) {
                $firstMeasureAttributes = $targetPart->measure[0]->attributes;
                $timebase = isset($firstMeasureAttributes->divisions) ? (int)$firstMeasureAttributes->divisions : 512;
            }

            // Akumulasi tick untuk birama berikutnya
            $tickAccumulator += $beats * $timebase;

            // Draw Tempo from tempoMap if present for this measure
            
            if (isset($tempoMap[$mIdx])) {
                $bpmVal = $tempoMap[$mIdx];
                $pdf->SetFont($this->fontFamily, 'B', 10);

                // offset Y untuk tempo (mudah diatur)
                

                // Teks "Tempo:"
                $pdf->SetXY($currentMeasureX + $tempoXOffset + $tempoTextOffset, $systemY + $tempoYOffset);
                $pdf->Cell(12, 3, "Tempo: ", 0, 0, 'L');
                
                // Draw a tiny quarter note
                $noteX = $currentMeasureX + 10.5;
                $pdf->Ellipse(
                    $noteX + $tempoXOffset, 
                    $systemY + $tempoYOffset + 2, // pakai offset
                    1.035, 0.65, 'FD', $elevation
                );
                $pdf->SetLineWidth(0.2);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->Line(
                    $noteX + $tempoXOffset + 1, 
                    $systemY + $tempoYOffset - 1,   // mulai sedikit di atas
                    $noteX + $tempoXOffset + 1, 
                    $systemY + $tempoYOffset + 2.1    // berakhir lebih bawah
                );

                
                // Draw the "= 120" text
                $pdf->SetXY($noteX + $tempoXOffset + 1.8, $systemY + $tempoYOffset);
                $pdf->Cell(15, 3, "= " . $bpmVal, 0, 0, 'L');
            }


            // Draw vertical barlines (slightly longer and darker for better visibility)
            $pdf->SetLineWidth(0.35);
            $pdf->SetDrawColor(0, 0, 0);
            if ($layoutIdx % $measuresPerSystem == 0) {
                // Draw left barline of first measure in system
                $pdf->Line($currentMeasureX, $systemY - 0.5, $currentMeasureX, $systemY + 8.5);
            }
            // Draw right barline of measure
            $pdf->Line($currentMeasureX + $measureWidth, $systemY - 0.5, $currentMeasureX + $measureWidth, $systemY + 8.5);
            $pdf->SetLineWidth(0.2);

            // Close measure group for SVG
            if ($pdf instanceof SheetMusicSVG) {
                $pdf->endGroup();
            }

            // Handle Multi-measure Rest compression
            $c = isset($collapseCount[$mIdx]) ? $collapseCount[$mIdx] : 1;
            if ($c > 1) {
                // Draw Multi-measure Rest (church rest)
                $pdf->SetLineWidth(1.2);
                $pdf->SetDrawColor(0, 0, 0);
                $restStartY = $systemY + 4.0; // middle staff line (B4)
                $restStartX = $currentMeasureX + 8.0;
                $restEndX = $currentMeasureX + $measureWidth - 8.0;
                
                // Horizontal thick bar
                $pdf->Line($restStartX, $restStartY, $restEndX, $restStartY);
                
                // Vertical end ticks
                $pdf->SetLineWidth(0.4);
                $pdf->Line($restStartX, $restStartY - 1.5, $restStartX, $restStartY + 1.5);
                $pdf->Line($restEndX, $restStartY - 1.5, $restEndX, $restStartY + 1.5);
                
                // Draw the number above the staff
                $pdf->SetFont($this->fontFamily, 'B', 10);
                $pdf->SetXY($currentMeasureX, $systemY - 4.5);
                $pdf->Cell($measureWidth, 4, $c, 0, 0, 'C');

                // Move to next system if we hit measures limit per system
                if ($layoutIdx % $measuresPerSystem == $measuresPerSystem - 1) {
                    $systemY += $this->systemHeight;

                    if ($pdf instanceof SheetMusicSVG) {
                        $systemEndX = $currentMeasureX + $measureWidth;
                        $systemEndY = $systemY - 8; // Approx bottom of staff before moving to next system
                        $systemWidth = $systemEndX - $systemStartX;
                        $systemHeight = $systemEndY - $systemStartY;
                        $pdf->updateGroupAttributes($pdf->PageNo() - 1, $systemAttributesIndex, array(
                            'x' => round($systemStartX, 2), 'y' => round($systemStartY, 2), 
                            'width' => round($systemWidth, 2), 'height' => round($systemHeight, 2), 
                            // End tick is the start tick of the next measure
                            'data-end-tick' => $tickAccumulator 
                        ));
                    }

                    // Close system group for SVG
                    if ($pdf instanceof SheetMusicSVG) {
                        $pdf->endGroup();
                    }
                }

                $mIdx += $c - 1; // skip skipped measures in the XML loop
                continue; // skip drawing notes for this group
            }

            // Draw notes in measure
            $currentDiv = 0;
            $lastDuration = 0;
            $prevNoteX = null;
            $ignoreDefaultX = false;
            $measureNotesData = array();
            $noteIndex = 0;

            foreach ($measure->note as $note) {
                // Start SVG group for note/rest if applicable
                if ($pdf instanceof SheetMusicSVG) {
                    $startTick = $tickAccumulator + (int)round($currentDiv * $timebase / $divisions);
                    $endTick = $startTick + (int)round((isset($note->duration) ? (int)$note->duration : 0) * $timebase / $divisions);
                    $pdf->startGroup(array(
                        'data-element' => 'true',
                        'data-element-type' => isset($note->rest) ? 'rest' : 'note',
                        'data-note-type' => $note->type,
                        'data-start-tick' => $startTick,
                        'data-end-tick' => $endTick
                    ));
                }

                $duration = isset($note->duration) ? (int)$note->duration : 0;
                if ($duration === 0 && isset($note->type)) {
                    $typeStr = (string)$note->type;
                    if ($typeStr === 'whole') $duration = $divisions * 4;
                    elseif ($typeStr === 'half') $duration = $divisions * 2;
                    elseif ($typeStr === 'quarter') $duration = $divisions;
                    elseif ($typeStr === 'eighth') $duration = $divisions / 2;
                    elseif ($typeStr === '16th') $duration = $divisions / 4;
                    elseif ($typeStr === '32nd') $duration = $divisions / 8;
                    elseif ($typeStr === '64th') $duration = $divisions / 16;
                }
                
                // Handle chords: chords align with the start of the previous note
                $isChord = isset($note->chord);
                if ($isChord) {
                    $currentDiv -= $lastDuration;
                }

                $isTieStop = false;
                if (isset($note->tie)) {
                    foreach ($note->tie as $t) {
                        if ((string)$t['type'] === 'stop') {
                            $isTieStop = true;
                            break;
                        }
                    }
                }
                if (isset($note->notations->tied)) {
                    foreach ($note->notations->tied as $t) {
                        if ((string)$t['type'] === 'stop') {
                            $isTieStop = true;
                            break;
                        }
                    }
                }

                // Calculate note X coordinate (with 2mm padding on left/right margins of the measure to avoid barline clashes)
                $padding = 3.5;
                $xRange = $measureWidth - ($padding * 2);
                if ($xRange < 5) {
                    $xRange = 5; // prevent division/range anomalies
                }
                
                if ($isTieStop && $currentDiv === 0 && $mIdx > 0) {
                    $ignoreDefaultX = true;
                }

                if (isset($note['default-x']) && isset($measure['width']) && !$ignoreDefaultX) {
                    $defX = (float)$note['default-x'];
                    $measW = (float)$measure['width'];
                    if ($measW > 120.0) {
                        $ratio = ($defX - 60.0) / ($measW - 120.0);
                    } else {
                        $ratio = ($measureDuration > 0) ? ($currentDiv / $measureDuration) : 0;
                    }
                } else {
                    $ratio = ($measureDuration > 0) ? ($currentDiv / $measureDuration) : 0;
                }
                
                // Fallback to proportional spacing if default-x calculations result in 0 or negative for a non-zero currentDiv
                if ($ratio <= 0 && $currentDiv > 0) {
                    $ratio = ($measureDuration > 0) ? ($currentDiv / $measureDuration) : 0;
                }
                
                if ($ratio < 0) $ratio = 0;
                if ($ratio > 1) $ratio = 1;

                $tieCarryOffset = 0.0;
                if ($isTieStop && $currentDiv === 0 && $mIdx > 0) {
                    $tieCarryOffset = 0;
                }

                $xOffset = $padding + ($ratio * $xRange) + $tieCarryOffset;
                
                $noteX = $currentMeasureX + $xOffset;
                if ($prevNoteX !== null && !$isChord) {
                    $minGap = 4;
                    if ($lastDuration > 0 && isset($divisions) && $divisions > 0) {
                        $dynamicGap = ($lastDuration / $divisions) * 6.5;
                        if ($dynamicGap > $minGap) {
                            $minGap = $dynamicGap;
                        }
                    }
                    if ($noteX < $prevNoteX + $minGap) {
                        $noteX = $prevNoteX + $minGap;
                    }
                }
                if (!$isChord) {
                    $prevNoteX = $noteX;
                }

                // Draw Rest Note
                if (isset($note->rest)) {
                    $pdf->SetDrawColor(0, 0, 0);
                    if ($duration >= $measureDuration) {
                        // Whole rest: box hanging from line 4 (D5, which is systemY + 6.0)
                        $pdf->Rect($noteX - 2, $systemY + 6.0, 4.0, 1.5, 'F');
                    } elseif ($duration >= $measureDuration / 2) {
                        // Half rest: box sitting on line 3 (B4, which is systemY + 4.0)
                        $pdf->Rect($noteX - 2, $systemY + 2.5, 4.0, 1.5, 'F');
                    } else {
                        $typeStr = isset($note->type) ? (string)$note->type : 'quarter';
                        $pdf->DrawRest($noteX - 2, $systemY, $typeStr);
                    }
                } 
                // Draw Sound Note (pitched or unpitched)
                else {
                    $pitchVal = 71; // Default middle B4
                    $hasAlter = false;
                    $alterVal = 0;
                    $notehead = 'normal';

                    if (isset($note->pitch)) {
                        $step = (string)$note->pitch->step;
                        $octave = (int)$note->pitch->octave;
                        $pitchVal = $this->getPitchValue($step, $octave);
                        if (isset($note->pitch->alter)) {
                            $hasAlter = true;
                            $alterVal = (int)$note->pitch->alter;
                        }
                    } elseif (isset($note->unpitched)) {
                        $step = (string)$note->unpitched->{'display-step'};
                        $octave = (int)$note->unpitched->{'display-octave'};
                        $pitchVal = $this->getPitchValue($step, $octave);
                    }

                    if (isset($note->notehead)) {
                        $notehead = (string)$note->notehead;
                    }

                    // Get staff diatonic step based on the active clef
                    if ($clefSign === 'F') {
                        $stepIndex = $this->getBassStep($pitchVal);
                    } elseif ($clefSign === 'C') {
                        $stepIndex = $this->getAltoStep($pitchVal);
                    } else {
                        $stepIndex = $this->getTrebleStep($pitchVal);
                    }
                    // notehead center Y coordinate
                    $noteY = $systemY + 8 - ($stepIndex * 1.0);

                    // Draw accidentals if any (sharp/flat)
                    if ($hasAlter && !$isPercussion) {
                        if ($alterVal > 0) {
                            $pdf->DrawSharp($noteX - 2.8, $noteY);
                        } elseif ($alterVal < 0) {
                            $pdf->DrawFlat($noteX - 2.8, $noteY);
                        }
                    }

                    // Draw Ledger Lines
                    if ($stepIndex <= -2) {
                        for ($lineIdx = -2; $lineIdx >= $stepIndex; $lineIdx -= 2) {
                            $ly = $systemY + 8 - ($lineIdx * 1.0);
                            $pdf->Line($noteX - 3, $ly, $noteX + 3, $ly, 'sheet-music-line');
                        }
                    } elseif ($stepIndex >= 10) {
                        for ($lineIdx = 10; $lineIdx <= $stepIndex; $lineIdx += 2) {
                            $ly = $systemY + 8 - ($lineIdx * 1.0);
                            $pdf->Line($noteX - 3, $ly, $noteX + 3, $ly, 'sheet-music-line');
                        }
                    }

                    // Draw Notehead
                    $typeStr = isset($note->type) ? (string)$note->type : 'quarter';
                    $style = ($typeStr === 'half' || $typeStr === 'whole') ? 'D' : 'FD';
                    

                    if ($notehead === 'x') {
                        // Draw 'x' for hi-hats/cymbals
                        $pdf->SetLineWidth(0.35);
                        $pdf->Line($noteX - 1.2, $noteY - 1.2, $noteX + 1.2, $noteY + 1.2);
                        $pdf->Line($noteX - 1.2, $noteY + 1.2, $noteX + 1.2, $noteY - 1.2);
                        $pdf->SetLineWidth(0.2);
                    } elseif ($notehead === 'slash') {
                        // Draw diagonal slash notehead
                        $pdf->SetLineWidth(0.35);
                        $pdf->Line($noteX - 1.2, $noteY + 1.2, $noteX + 1.2, $noteY - 1.2);
                        $pdf->SetLineWidth(0.2);
                    } else {
                        // Draw augmentation dots if any
                        $dotsCount = 0;
                        if (isset($note->dot)) {
                            $dotsCount = is_array($note->dot) ? count($note->dot) : 1;
                        }
                        if ($dotsCount > 0) {
                            $dotX = $noteX + 2.5; // Position dot to the right of the notehead
                            $dotRadius = 0.35;
                            for ($d = 0; $d < $dotsCount; $d++) {
                                $pdf->Circle($dotX, $noteY, $dotRadius, 'F');
                                $dotX += 1.2; // Space out multiple dots
                            }
                        }
                        // Draw normal oval/tilted notehead
                        $pdf->SetLineWidth(0.35);
                        $pdf->Ellipse($noteX, $noteY, 1.55, 0.92, $style, $elevation);
                    }

                    // Determine stem direction (used for stem drawing and ties)
                    $stemDir = 'up';

                    $stemDir = ($stepIndex >= 4) ? 'down' : 'up';

                    // Store note data for drawing stems and beams later
                    $measureNotesData[$noteIndex] = array(
                        'x' => $noteX,
                        'y' => $noteY,
                        'stemDir' => $stemDir,
                        'typeStr' => $typeStr,
                        'isChord' => $isChord,
                        'isRest' => isset($note->rest),
                        'beam' => isset($note->beam) ? (string)$note->beam[0] : null,
                        'durationDivs' => $duration,
                        'startDiv' => $currentDiv,
                        'beatIndex' => ($divisions > 0) ? floor($currentDiv / $divisions) : 0,
                        'startTick' => $startTick,
                        'endTick' => $endTick
                    );

                    // Draw Tie / Tied Stop
                    if ($isTieStop && isset($activeTies[$pitchVal])) {
                        $startNote = $activeTies[$pitchVal];
                        unset($activeTies[$pitchVal]); // consumed
                        
                        $startSystemIdx = (int)($measureLayoutIdx[$startNote['measureIdx']] / $measuresPerSystem);
                        $endSystemIdx = (int)($measureLayoutIdx[$mIdx] / $measuresPerSystem);
                        
                        if ($startSystemIdx === $endSystemIdx) {
                            // Same system - draw single tie curve
                            $bendDir = ($startNote['stemDir'] === 'up') ? 'down' : 'up';
                            $sx = $startNote['x'] + 1.2;
                            $ex = max($noteX - 1.2, $sx + 1.4);
                            $sy = ($bendDir === 'down') ? ($startNote['y'] + 0.5) : ($startNote['y'] - 0.5);
                            $ey = ($bendDir === 'down') ? ($noteY + 0.5) : ($noteY - 0.5);
                            
                            $pdf->DrawTie($sx, $sy, $ex, $ey, $bendDir);
                        } else {
                            // Different systems - draw the second (incoming) segment.
                            // Span from the left staff margin to just past the notehead center
                            // so it is clearly visible even when the note is the first in the measure.
                            $bendDir = ($stemDir === 'up') ? 'down' : 'up';
                            $ey = ($bendDir === 'down') ? ($noteY + 0.5) : ($noteY - 0.5);
                            
                            // Start from the current system's left staff edge
                            $sx = $systemX + $tieStartX;
                            $sy = $ey; // horizontal
                            // End at the center of the notehead
                            $ex = $noteX + 0.5;
                            
                            $pdf->DrawTie($sx, $sy, $ex, $ey, $bendDir);
                        }
                    }

                    // Draw Tie / Tied Start
                    $isTieStart = false;
                    if (isset($note->tie)) {
                        foreach ($note->tie as $t) {
                            if ((string)$t['type'] === 'start') {
                                $isTieStart = true;
                            }
                        }
                    }
                    if (isset($note->notations->tied)) {
                        foreach ($note->notations->tied as $t) {
                            if ((string)$t['type'] === 'start') {
                                $isTieStart = true;
                            }
                        }
                    }

                    if ($isTieStart) {
                        // Store starting tie info
                        $activeTies[$pitchVal] = array(
                            'x' => $noteX,
                            'y' => $noteY,
                            'stemDir' => $stemDir,
                            'measureIdx' => $mIdx,
                            'measureX' => $currentMeasureX,
                            'measureWidth' => $measureWidth
                        );
                        
                        // Find matching stop note to see if they are in different systems
                        $stopMeasureIdx = -1;
                        for ($i = $mIdx + 1; $i < $totalMeasures; $i++) {
                            $meas = $measures[$i];
                            foreach ($meas->note as $n) {
                                $nPitch = 71;
                                if (isset($n->pitch)) {
                                    $nStep = (string)$n->pitch->step;
                                    $nOct = (int)$n->pitch->octave;
                                    $nPitch = $this->getPitchValue($nStep, $nOct);
                                } elseif (isset($n->unpitched)) {
                                    $nStep = (string)$n->unpitched->{'display-step'};
                                    $nOct = (int)$n->unpitched->{'display-octave'};
                                    $nPitch = $this->getPitchValue($nStep, $nOct);
                                }
                                
                                if ($nPitch === $pitchVal) {
                                    $hasStop = false;
                                    if (isset($n->tie)) {
                                        foreach ($n->tie as $t) {
                                            if ((string)$t['type'] === 'stop') $hasStop = true;
                                        }
                                    }
                                    if (isset($n->notations->tied)) {
                                        foreach ($n->notations->tied as $t) {
                                            if ((string)$t['type'] === 'stop') $hasStop = true;
                                        }
                                    }
                                    if ($hasStop) {
                                        $stopMeasureIdx = $i;
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        if ($stopMeasureIdx !== -1) {
                            $startSystemIdx = (int)($measureLayoutIdx[$mIdx] / $measuresPerSystem);
                            $endSystemIdx = (int)($measureLayoutIdx[$stopMeasureIdx] / $measuresPerSystem);
                            
                            if ($startSystemIdx !== $endSystemIdx) {
                                // Different systems - draw the first segment immediately
                                $bendDir = ($stemDir === 'up') ? 'down' : 'up';
                                $sx = $noteX + 0; // Start tie from center of note head instead of right
                                $sy = $bendDir === 'down' ? ($noteY + 0.5) : ($noteY - 0.5);

                                $ex = $currentMeasureX + $measureWidth; // Draw to the barline
                                $ey = $sy; // keep it horizontal
                                
                                $pdf->DrawTie($sx, $sy, $ex, $ey, $bendDir);
                            }
                        }
                    }

                    // Draw lyric text in a compact position just below the notehead.
                    $lyricText = $this->getNoteLyricText($note);
                    if ($showLyric && $lyricText !== null && $lyricText !== '') {
                         $pdf->SetFont($this->fontFamily, '', $this->lyricFontSize);                        
                        // Hitung lebar teks untuk membuat cell yang pas
                        $textWidth = $pdf->GetStringWidth($lyricText) + 2; // Tambahkan sedikit padding
                        $cellX = $noteX - ($textWidth / 2); // Posisikan cell agar teks berada di tengah
                        $cellY = $systemY + 14; // Sesuaikan posisi vertikal
                        $pdf->SetXY($cellX, $cellY);
                        $pdf->Cell($textWidth, 5, $lyricText, 0, 0, 'C');
                    }
                }
                if ($pdf instanceof SheetMusicSVG) {
                    $pdf->endGroup();
                }
                $lastDuration = $duration;
                $currentDiv += $duration;
                $noteIndex++;
            }

            // Process beams and draw stems
            $beamGroups = array();
            $noteToBeamGroup = array();

            if ($this->drawBeam) {
                $currentBeamGroup = null;

                foreach ($measureNotesData as $idx => $nData) {
                    $isBeamable = in_array($nData['typeStr'], ['eighth', '16th', '32nd', '64th']);

                    // Jika not tidak bisa di-beam atau adalah rest, tutup grup yang ada.
                    if (!$isBeamable || $nData['isRest']) {
                        if ($currentBeamGroup !== null && count($currentBeamGroup['notes']) > 1) {
                            $beamGroups[] = $currentBeamGroup;
                        }
                        $currentBeamGroup = null;
                        continue;
                    }

                    // Logika baru yang lebih kuat
                    if ($nData['beam'] === 'begin') {
                        // Tutup grup lama, mulai yang baru berdasarkan 'begin'
                        if ($currentBeamGroup !== null && count($currentBeamGroup['notes']) > 1) $beamGroups[] = $currentBeamGroup;
                        $currentBeamGroup = ['notes' => [$idx], 'beat' => $nData['beatIndex']];
                    } elseif ($nData['beam'] === 'continue' || $nData['beam'] === 'end') {
                        $beatEndDiv = ($currentBeamGroup['beat'] + 1) * $divisions;
                        $noteEndDiv = $nData['startDiv'] + $nData['durationDivs'];
                        
                        if ($noteEndDiv > $beatEndDiv) {
                            // Paksa tutup grup jika XML mencoba menggabung not yang melintasi batas ketukan
                            if ($currentBeamGroup !== null && count($currentBeamGroup['notes']) > 1) $beamGroups[] = $currentBeamGroup;
                            $currentBeamGroup = ['notes' => [$idx], 'beat' => $nData['beatIndex']];
                        } else {
                            // Lanjutkan grup jika ada
                            if ($currentBeamGroup !== null) {
                                $currentBeamGroup['notes'][] = $idx;
                            }
                            // Tutup grup jika 'end'
                            if ($nData['beam'] === 'end' && $currentBeamGroup !== null) {
                                if (count($currentBeamGroup['notes']) > 1) $beamGroups[] = $currentBeamGroup;
                                $currentBeamGroup = null;
                            }
                        }
                    } else { // Fallback ke auto-beaming per ketukan jika tidak ada info beam
                        if ($currentBeamGroup === null) {
                            // Mulai grup baru
                            $currentBeamGroup = ['notes' => [$idx], 'beat' => $nData['beatIndex'], 'totalDuration' => $nData['durationDivs']];
                        } elseif ($currentBeamGroup['beat'] === $nData['beatIndex'] && !$nData['isChord']) {
                            // Hitung batas divisi (ticks) untuk ketukan saat ini
                            // Default beat durasi adalah $divisions (1 ketukan = 1 quarter note).
                            // Untuk ketepatan, boundary akhir ketukan adalah:
                            $beatEndDiv = ($currentBeamGroup['beat'] + 1) * $divisions;
                            $noteEndDiv = $nData['startDiv'] + $nData['durationDivs'];
                            
                            // Cek apakah penambahan not ini akan membuat durasinya melewati batas ketukan.
                            if ($noteEndDiv > $beatEndDiv) {
                                // Tutup grup lama karena durasi akan meluap (overflow) ke ketukan berikutnya
                                if (count($currentBeamGroup['notes']) > 1) {
                                    $beamGroups[] = $currentBeamGroup;
                                }
                                // Mulai grup baru dengan not saat ini
                                // Not ini mungkin berada di ketukan yang sama secara startDiv, 
                                // tapi kita paksa jadi grup baru.
                                $currentBeamGroup = ['notes' => [$idx], 'beat' => $nData['beatIndex'], 'totalDuration' => $nData['durationDivs']];
                            } else {
                                // Lanjutkan grup: ketukan sama dan tidak meluap
                                $currentBeamGroup['notes'][] = $idx;
                                $currentBeamGroup['totalDuration'] += $nData['durationDivs'];
                            }
                        } else {
                            // Tutup grup lama karena ketukan berbeda, mulai yang baru
                            if (count($currentBeamGroup['notes']) > 1) $beamGroups[] = $currentBeamGroup;
                            $currentBeamGroup = ['notes' => [$idx], 'beat' => $nData['beatIndex'], 'totalDuration' => $nData['durationDivs']];
                        }
                    }
                }
                if ($currentBeamGroup !== null && count($currentBeamGroup['notes']) > 1) {
                    $beamGroups[] = $currentBeamGroup;
                }
            }

            // Unify stem directions for beam groups
            foreach ($beamGroups as $bIdx => $bg) {
                $upCount = 0;
                $downCount = 0;
                foreach ($bg['notes'] as $idx) {
                    if ($measureNotesData[$idx]['stemDir'] === 'up') $upCount++;
                    else $downCount++;
                    $noteToBeamGroup[$idx] = $bIdx;
                }
                $unifiedDir = ($downCount > $upCount) ? 'down' : 'up';
                $beamGroups[$bIdx]['stemDir'] = $unifiedDir;
                foreach ($bg['notes'] as $idx) {
                    $measureNotesData[$idx]['stemDir'] = $unifiedDir;
                }
            }

            // Draw stems, flags, and beams
            foreach ($measureNotesData as $idx => $nData) {
                if ($nData['isRest'] || $nData['typeStr'] === 'whole') continue;

                if ($pdf instanceof SheetMusicSVG) {
                    $pdf->startGroup(array(
                        'data-element' => 'true',
                        'data-element-type' => 'stem',
                        'data-start-tick' => $nData['startTick'],
                        'data-end-tick' => $nData['endTick']
                    ));
                }

                $noteX = $nData['x'];
                $noteY = $nData['y'];
                $stemDir = $nData['stemDir'];
                $typeStr = $nData['typeStr'];

                $pdf->SetLineWidth(0.35);
                if ($stemDir === 'up') {
                    $stemX = $noteX + 1.512; // Posisi X tepi kiri tangkai
                    $stemEndY = $noteY - 8.5; // Ujung atas tangkai
                    $pdf->Line($stemX, $noteY - 0.4, $stemX, $stemEndY + 1.4);
                    if (!isset($noteToBeamGroup[$idx])) {
                        // Posisikan bendera agar menyentuh tengah tangkai
                        $pdf->DrawNoteFlag($stemX + (0.35 / 2), $stemEndY + 1.4, 'up', $typeStr);
                    }
                } else {
                    $stemX = $noteX - 1.512; // Posisi X tepi kanan tangkai
                    $stemEndY = $noteY + 8.5;
                    $pdf->Line($stemX, $noteY + 0.4, $stemX, $stemEndY - 1.4);
                    if (!isset($noteToBeamGroup[$idx])) {
                        $pdf->DrawNoteFlag($noteX - 1.56, $stemEndY - 1.4, 'down', $typeStr);
                    }
                }
                $pdf->SetLineWidth(0.2);

                if ($pdf instanceof SheetMusicSVG) {
                    $pdf->endGroup();
                }
            }

            // Draw Beam Lines
            if ($this->drawBeam) {
                foreach ($beamGroups as $bg) {
                    $notes = $bg['notes'];
                    if (count($notes) < 2) continue;
                    
                    $stemDir = $bg['stemDir'];
                    $pdf->SetLineWidth(0.8); // Ketebalan balok
                    
                    // Ambil data not pertama dan terakhir dalam grup balok
                    $firstNoteData = $measureNotesData[$notes[0]];
                    $lastNoteData = $measureNotesData[end($notes)];
                    
                    if ($pdf instanceof SheetMusicSVG) {
                        $pdf->startGroup(array(
                            'data-element' => 'true',
                            'data-element-type' => 'beam',
                            'data-start-tick' => $firstNoteData['startTick'],
                            'data-end-tick' => $lastNoteData['endTick']
                        ));
                    }
                    
                    // Tentukan posisi Y awal dan akhir untuk balok utama
                    $stemLength = 8.5;
                    $beamWidth = 0.8; // Lebar garis balok
                    
                    // Sesuaikan offset Y agar pusat balok sejajar dengan ujung tangkai
                    $yAdjust = ($stemDir === 'up') ? ($beamWidth / 2) : -($beamWidth / 2);
                    $stemTipOffset = ($stemDir === 'up') ? -$stemLength : $stemLength;
                    
                    $startY = $firstNoteData['y'] + $stemTipOffset + $yAdjust;
                    $endY = $lastNoteData['y'] + $stemTipOffset + $yAdjust;

                    // Batasi kemiringan agar tidak terlalu curam
                    $maxSlope = 0.5; // 0.5 mm per 1 mm horizontal
                    $dx = $lastNoteData['x'] - $firstNoteData['x'];
                    if ($dx > 0) {
                        $slope = ($endY - $startY) / $dx;
                        if (abs($slope) > $maxSlope) {
                            $endY = $startY + ($slope > 0 ? 1 : -1) * $maxSlope * $dx;
                        }
                    }
                    
                    // Max beams needed in this group
                    $maxBeams = 1;
                    $noteBeams = array();
                    foreach ($notes as $i => $nIdx) {
                        $type = $measureNotesData[$nIdx]['typeStr'];
                        $bCount = 1;
                        if ($type === '16th') $bCount = 2;
                        elseif ($type === '32nd') $bCount = 3;
                        elseif ($type === '64th') $bCount = 4;
                        
                        $noteBeams[$i] = $bCount;
                        if ($bCount > $maxBeams) $maxBeams = $bCount;
                    }
                    
                    // Draw each beam level
                    for ($level = 1; $level <= $maxBeams; $level++) {
                        $beamSpacing = ($stemDir === 'up') ? 1.2 : -1.2;
                        
                        $inSegment = false;
                        $segStart = -1;
                        $segEnd = -1;
                        
                        for ($i = 0; $i < count($notes); $i++) {
                            if ($noteBeams[$i] >= $level) {
                                if (!$inSegment) {
                                    $inSegment = true;
                                    $segStart = $i; // Mulai segmen balok
                                }
                                $segEnd = $i; // Akhiri segmen balok
                            } else {
                                if ($inSegment) {
                                    // Draw segment
                                    $stemWidth = 0.35; // Lebar tangkai
                                    $stemEdgeOffset = ($stemDir === 'up') ? 1.512 : -1.512;
                                    $stemCenterOffset = $stemEdgeOffset; // Gambar balok dari tepi tangkai

                                    $startX = $measureNotesData[$notes[$segStart]]['x'] + $stemCenterOffset;
                                    $endX = $measureNotesData[$notes[$segEnd]]['x'] + $stemCenterOffset;

                                    // Hitung posisi Y awal dan akhir untuk balok yang miring
                                    $levelOffset = ($level - 1) * $beamSpacing;
                                    $beamStartY = $startY + $levelOffset;
                                    $beamEndY = $endY + $levelOffset;

                                    // Hitung Y untuk titik awal dan akhir segmen balok saat ini
                                    $ratioStart = ($dx > 0) ? ($measureNotesData[$notes[$segStart]]['x'] - $firstNoteData['x']) / $dx : 0;
                                    $ratioEnd = ($dx > 0) ? ($measureNotesData[$notes[$segEnd]]['x'] - $firstNoteData['x']) / $dx : 0;
                                    $y1 = $beamStartY + ($beamEndY - $beamStartY) * $ratioStart;
                                    $y2 = $beamStartY + ($beamEndY - $beamStartY) * $ratioEnd;
                                    
                                    if ($segStart === $segEnd) {
                                        $hookLength = 3.0;
                                        if ($segStart === 0) {
                                            // Hook ke kanan dari not pertama
                                            $endX = $startX + $hookLength;
                                            // Hitung Y2 berdasarkan kemiringan
                                            $ratioEnd = ($dx > 0) ? (($measureNotesData[$notes[$segStart]]['x'] + $hookLength) - $firstNoteData['x']) / $dx : $ratioStart;
                                            $y2 = $beamStartY + ($beamEndY - $beamStartY) * $ratioEnd;
                                        } else {
                                            // Hook ke kiri dari not terakhir
                                            $startX = $endX - $hookLength;
                                            // Hitung Y1 berdasarkan kemiringan
                                            $ratioStart = ($dx > 0) ? (($measureNotesData[$notes[$segEnd]]['x'] - $hookLength) - $firstNoteData['x']) / $dx : $ratioEnd;
                                            $y1 = $beamStartY + ($beamEndY - $beamStartY) * $ratioStart;
                                        }
                                    }
                                    $pdf->Line($startX, $y1, $endX, $y2);
                                    $inSegment = false;
                                }
                            }
                        }
                        if ($inSegment) {
                            $stemWidth = 0.35; // Lebar tangkai
                            $stemEdgeOffset = ($stemDir === 'up') ? 1.512 : -1.512;
                            $stemCenterOffset = $stemEdgeOffset; // Gambar balok dari tepi tangkai

                            $startX = $measureNotesData[$notes[$segStart]]['x'] + $stemCenterOffset;
                            $endX = $measureNotesData[$notes[$segEnd]]['x'] + $stemCenterOffset;

                            // Hitung posisi Y awal dan akhir untuk balok yang miring
                            $levelOffset = ($level - 1) * $beamSpacing;
                            $beamStartY = $startY + $levelOffset;
                            $beamEndY = $endY + $levelOffset;

                            $ratioStart = ($dx > 0) ? ($measureNotesData[$notes[$segStart]]['x'] - $firstNoteData['x']) / $dx : 0;
                            $ratioEnd = ($dx > 0) ? ($measureNotesData[$notes[$segEnd]]['x'] - $firstNoteData['x']) / $dx : 0;
                            $y1 = $beamStartY + ($beamEndY - $beamStartY) * $ratioStart;
                            $y2 = $beamStartY + ($beamEndY - $beamStartY) * $ratioEnd;
                            
                            if ($segStart === $segEnd) {
                                $hookLength = 3.0;
                                if ($segStart === 0) {
                                    // Hook ke kanan dari not pertama
                                    $endX = $startX + $hookLength;
                                    // Hitung Y2 berdasarkan kemiringan
                                    $ratioEnd = ($dx > 0) ? (($measureNotesData[$notes[$segStart]]['x'] + $hookLength) - $firstNoteData['x']) / $dx : $ratioStart;
                                    $y2 = $beamStartY + ($beamEndY - $beamStartY) * $ratioEnd;
                                } else {
                                    // Hook ke kiri dari not terakhir
                                    $startX = $endX - $hookLength;
                                    // Hitung Y1 berdasarkan kemiringan
                                    $ratioStart = ($dx > 0) ? (($measureNotesData[$notes[$segEnd]]['x'] - $hookLength) - $firstNoteData['x']) / $dx : $ratioEnd;
                                    $y1 = $beamStartY + ($beamEndY - $beamStartY) * $ratioStart;
                                }
                            }
                            $pdf->Line($startX, $y1, $endX, $y2);
                        }
                    }
                    
                    // Extend internal stems to the primary beam
                    $pdf->SetLineWidth(0.35);
                    foreach ($notes as $nIdx) {
                        $noteData = $measureNotesData[$nIdx];
                        $stemWidth = 0.35;
                        $stemOffset = ($stemDir === 'up') ? 1.512 : -1.512;
                        $nX = $noteData['x'] + $stemOffset; // Gambar tangkai dari tepinya
                        $nY = $noteData['y'] + (($stemDir === 'up') ? -0.4 : 0.4);

                        // Hitung Y akhir tangkai pada balok miring
                        $ratio = ($dx > 0) ? ($noteData['x'] - $firstNoteData['x']) / $dx : 0;
                        // Gunakan posisi Y balok yang sudah disesuaikan
                        $stemEndY = $startY + ($endY - $startY) * $ratio - $yAdjust;

                        $pdf->Line($nX, $nY, $nX, $stemEndY);
                    }
                    
                    $pdf->SetLineWidth(0.2);
                    
                    if ($pdf instanceof SheetMusicSVG) {
                        $pdf->endGroup();
                    }
                }
            }

            // Move to next system if we hit measures limit per system
            if ($layoutIdx % $measuresPerSystem == $measuresPerSystem - 1) {
                $systemY += $this->systemHeight;

                if ($pdf instanceof SheetMusicSVG) {
                    $systemEndX = $currentMeasureX + $measureWidth;
                    $systemEndY = $systemY - 8; // Approx bottom of staff before moving to next system
                    $systemWidth = $systemEndX - $systemStartX;
                    $systemHeight = $systemEndY - $systemStartY;
                    $pdf->updateGroupAttributes($pdf->PageNo() - 1, $systemAttributesIndex, array(
                        'x' => round($systemStartX, 2), 'y' => round($systemStartY, 2), 
                        'width' => round($systemWidth, 2), 'height' => round($systemHeight, 2), 
                        // End tick is the start tick of the next measure
                        'data-end-tick' => $tickAccumulator
                    ));
                }

                // Close system group for SVG
                if ($pdf instanceof SheetMusicSVG) {
                    $pdf->endGroup();
                }
            }
        }

        // After all measures are processed, draw any remaining ties that cross the final system break
        foreach ($activeTies as $pitchVal => $startNote) {
            $startSystemIdx = (int)($measureLayoutIdx[$startNote['measureIdx']] / $measuresPerSystem);
            $lastSystemIdx = (int)(($totalMeasures - 1) / $measuresPerSystem);
            if ($startSystemIdx === $lastSystemIdx) {
                $bendDir = ($startNote['stemDir'] === 'up') ? 'down' : 'up';
                $sx = $startNote['x'] + 1.2;
                $sy = ($bendDir === 'down') ? ($startNote['y'] + 0.5) : ($startNote['y'] - 0.5);

                $ex = $startNote['measureX'] + $startNote['measureWidth']; // Draw to the final barline
                $ey = $sy; 
                $tieQueue[] = array('sx' => $sx, 'sy' => $sy, 'ex' => $ex, 'ey' => $ey, 'direction' => $bendDir, 'pageIdx' => $pdf->PageNo() - 1);
            }
        }

        // Gambar semua tie yang ada di antrian setelah semua elemen lain selesai
        // For SVG rendering, Y coordinates must be pre-offset to the correct page
        // before DrawTieAbs() is called, to avoid double page-offset applied by getOffsetY().
        $isSvgRenderer = method_exists($pdf, 'DrawTieAbs');
        foreach ($tieQueue as $tie) {
            if ($isSvgRenderer) {
                // Pre-compute absolute SVG Y using the page offset at the time the tie was queued
                $pageOff = $pdf->getPageOffset(isset($tie['pageIdx']) ? $tie['pageIdx'] : 0);
                $pdf->DrawTieAbs($tie['sx'], $pageOff + $tie['sy'], $tie['ex'], $pageOff + $tie['ey'], $tie['direction']);
            } else {
                $pdf->DrawTie($tie['sx'], $tie['sy'], $tie['ex'], $tie['ey'], $tie['direction']);
            }
        }

        return $pdf;
    }

    /**
     * Map MIDI pitch to staff diatonic step index (E4 = 0)
     *
     * @param int $noteNumber MIDI note number (0-127)
     * @return int Diatonic step index relative to E4 (E4 = 0, F4 = 1, G4 = 2, A4 = 3, B4 = 4, C5 = 5, D5 = 6, E5 = 7, etc.)
     */
    private function getTrebleStep($noteNumber)
    {
        $pc = $noteNumber % 12;
        $oct = (int) floor($noteNumber / 12);
        
        // E4 (MIDI 64): pc = 4, oct = 5.
        // Let's calculate: (5 * 7) + 2 - 37 = 0.
        return ($oct * 7) + $this->pitchClasses[$pc] - 37;
    }

    /**
     * Map MIDI pitch to staff diatonic step index for Bass clef (G2 = 0)
     *
     * @param int $noteNumber MIDI note number (0-127)
     * @return int Diatonic step index relative to G2 (G2 = 0)
     */
    private function getBassStep($noteNumber)
    {
        $pc = $noteNumber % 12;
        $oct = (int) floor($noteNumber / 12);
        
        // G2 (MIDI 43): pc = 7, oct = 3.
        // Let's calculate: (3 * 7) + 4 - 25 = 0.
        return ($oct * 7) + $this->pitchClasses[$pc] - 25;
    }

    /**
     * Map MIDI pitch to staff diatonic step index for Alto clef (F3 = 0)
     *
     * @param int $noteNumber MIDI note number (0-127)
     * @return int Diatonic step index relative to F3 (F3 = 0)
     */
    private function getAltoStep($noteNumber)
    {
        $pc = $noteNumber % 12;
        $oct = (int) floor($noteNumber / 12);
        
        // F3 (MIDI 53): pc = 5, oct = 4.
        // Let's calculate: (4 * 7) + 3 - 31 = 0.
        return ($oct * 7) + $this->pitchClasses[$pc] - 31;
    }

    /**
     * Map MIDI Step and Octave to pitch value
     *
     * @param string $step C, D, E, F, G, A, B
     * @param int $octave Octave number (e.g., 4 for middle C)
     * @return int MIDI note number (0-127)
     */
    private function getPitchValue($step, $octave)
    {
        $stepMap = array('C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11);
        return 12 * ($octave + 1) + $stepMap[strtoupper($step)];
    }

    /**
     * Gets the configured height for each staff system.
     *
     * @return int The height in millimeters.
     */
    public function getSystemHeight()
    {
        return $this->systemHeight;
    }

    /**
     * Sets the height for each staff system.
     *
     * @param int $systemHeight The height in millimeters.
     * @return self
     */
    public function setSystemHeight($systemHeight)
    {
        $this->systemHeight = $systemHeight;

        return $this;
    }
}

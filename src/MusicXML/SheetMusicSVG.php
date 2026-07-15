<?php

namespace MusicXML;

/**
 * SheetMusicSVG class to render MusicXML elements directly to SVG format
 * Matches the public API of SheetMusicPDF to allow generic template rendering
 *
 * This class provides a fluent interface for creating SVG documents, mimicking the
 * FPDF library's methods for drawing shapes, text, and lines. It supports both
 * single-page (continuous) and multi-page (stacked) SVG output.
 * 
 * @author Kamshory
 */
class SheetMusicSVG
{
    use SheetMusicTrait;
    /**
     * The composer's name, used in the multi-page footer.
     * @var string
     */
    public $composer = 'Unknown';
    /**
     * The copyright year, used in the multi-page footer.
     * @var string
     */
    public $year = '';
    
    /**
     * Page width in millimeters (A4 standard).
     * @var int
     */
    public $w = 210;
    /**
     * Page height in millimeters (A4 standard).
     * @var int
     */
    public $h = 297;
    
    /**
     * Scale factor (from FPDF, not actively used in SVG generation).
     * @var int
     */
    public $k = 1;
    
    /** @var int Left margin in mm. */
    public $lMargin = 10;
    /** @var int Right margin in mm. */
    public $rMargin = 10;
    /** @var int Top margin in mm. */
    public $tMargin = 10;
    /** @var int Bottom margin in mm. */
    public $bMargin = 10;
    
    /** @var int Current X coordinate in mm. */
    public $x = 10;
    /** @var int Current Y coordinate in mm. */
    public $y = 10;
    
    /** @var string Current font family. */
    private $fontFamily = 'Times';
    /** @var string Current font style ('normal' or 'italic'). */
    private $fontStyle = 'normal';
    /** @var string Current font weight ('normal' or 'bold'). */
    private $fontWeight = 'normal';
    /** @var int Current font size in points. */
    private $fontSize = 12; // in points
    
    /** @var float Current line width for strokes in mm. */
    private $lineWidth = 0.12;

    /** @var bool Flag for single-page continuous SVG output. */
    private $singlePage = true;

    /**
     * Stores SVG elements for each page in multi-page mode.
     * @var array<int, string[]>
     */
    private $pages = [];
    /** @var int The zero-based index of the current page. */
    private $currentPage = -1;

    /**
     * Stores all SVG elements in single-page mode.
     * @var string[]
     */
    private $svgElements = []; // Used for single page mode
    private $maxY = 0;

    /**
     * Stack to keep track of open group tags.
     * @var integer
     */
    private $openGroupCount = 0;
    
    /**
     * The vertical gap between pages in multi-page mode (in mm).
     * @var int
     */
    private $pageGap = 10;

    /**
     * SheetMusicSVG constructor.
     * 
     * @param string $orientation Page orientation ('P' or 'L'). Not currently used.
     * @param string $unit Measurement unit ('mm'). Not currently used.
     * @param string $size Page size ('A4'). Not currently used.
     * @param bool $singlePage If true, generates a single continuous SVG. If false, generates stacked pages.
     */
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $singlePage = true)
    {
        $this->singlePage = $singlePage;
        if (!$this->singlePage) {
            $this->AddPage(); // Initialize first page for multi-page mode
        }
    }

    /**
     * Checks if the renderer is in single-page mode.
     * 
     * @return bool
     */
    public function isSinglePageMode()
    {
        return $this->singlePage;
    }

    /**
     * Returns the current page number (1-based).
     * 
     * @return int
     */
    public function PageNo()
    {
        return $this->singlePage ? 1 : $this->currentPage + 1;
    }

    /**
     * FPDF compatibility method. Does nothing in this implementation.
     * Total pages are calculated during output.
     * 
     * @return void
     */
    public function AliasNbPages()
    {
        // No-op (handled in Output() footer calculation)
    }

    /**
     * FPDF compatibility method. Does nothing in this implementation.
     * 
     * @param bool $auto
     * @param int $margin
     * @return void
     */
    public function SetAutoPageBreak($auto, $margin = 0)
    {
        // No-op
    }

    /**
     * Adds a new page in multi-page mode, resetting coordinates.
     * 
     * @param string $orientation Not used.
     * @param string $size Not used.
     * @param int $rotation Not used.
     * @return void
     */
    public function AddPage($orientation = '', $size = '', $rotation = 0)
    {
        if ($this->singlePage) {
            // No-op for single-page SVG output.
            return;
        }
        // Close any open groups from the previous page before starting a new one
        while ($this->openGroupCount > 0) {
            $this->endGroup();
        }

        $this->currentPage++;
        $this->pages[$this->currentPage] = [];
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
    }

    /**
     * Sets the font properties for subsequent text elements.
     * 
     * @param string $family The font family name (e.g., 'Times', 'Arial').
     * @param string $style A string containing 'B' for bold and/or 'I' for italic.
     * @param int $size The font size in points.
     * @return void
     */
    public function SetFont($family, $style = '', $size = 10)
    {
        if (!empty($family)) {
            $this->fontFamily = $family;
        }
        
        $this->fontWeight = 'normal';
        $this->fontStyle = 'normal';
        if (!empty($style)) {
            $style = strtoupper($style);
            if (strpos($style, 'B') !== false) {
                $this->fontWeight = 'bold';
            }
            if (strpos($style, 'I') !== false) {
                $this->fontStyle = 'italic';
            }
        }
        
        if ($size > 0) {
            $this->fontSize = $size;
        }
    }

    /**
     * FPDF compatibility method. Does nothing.
     * SVG elements use `currentColor` to allow CSS styling.
     * 
     * @param int $r
     * @param int|null $g
     * @param int|null $b
     * @return void
     */
    public function SetDrawColor($r, $g = null, $b = null)
    {
        // No-op (we default stroke/fill color to currentColor for CSS stylability)
    }

    /**
     * Sets the stroke width for lines and shape outlines.
     * 
     * @param float $width The width in mm.
     * @return void
     */
    public function SetLineWidth($width)
    {
        $this->lineWidth = $width;
    }

    /**
     * Gets the current X coordinate.
     * 
     * @return int
     */
    public function GetX()
    {
        return $this->x;
    }

    /**
     * Gets the current Y coordinate.
     * 
     * @return int
     */
    public function GetY()
    {
        return $this->y;
    }

    /**
     * Sets the current X coordinate.
     * A negative value sets it relative to the right margin.
     * 
     * @param int $x The new X coordinate.
     * @return void
     */
    public function SetX($x)
    {
        if ($x < 0) {
            $this->x = $this->w + $x;
        } else {
            $this->x = $x;
        }
    }

    /**
     * Sets the current Y coordinate.
     * A negative value sets it relative to the bottom margin.
     * 
     * @param int $y The new Y coordinate.
     * @return void
     */
    public function SetY($y)
    {
        if ($y < 0) {
            $this->y = $this->h + $y;
        } else {
            $this->y = $y;
        }
    }

    /**
     * Sets the current X and Y coordinates.
     * 
     * @param int $x The new X coordinate.
     * @param int $y The new Y coordinate.
     * @return void
     */
    public function SetXY($x, $y)
    {
        $this->SetX($x);
        $this->SetY($y);
    }

    /**
     * Moves the current position down to the next line.
     * The X coordinate is reset to the left margin.
     * 
     * @param float|null $h The height of the line break. If null, it's auto-calculated from font size.
     * @return void
     */
    public function Ln($h = null)
    {
        if ($h === null) {
            // Approximate line height based on font size (1pt = 0.3527mm)
            $h = $this->fontSize * 0.3527 * 1.2;
        }
        $this->y += $h;
        $this->x = $this->lMargin;
    }

    /**
     * Calculates the absolute Y coordinate for an element based on the current page.
     * 
     * @param float $y The relative Y coordinate on the current page.
     * @return float The absolute Y coordinate in the final SVG canvas.
     */
    private function getOffsetY($y)
    {
        if ($this->singlePage) {
            return $y;
        }
        // Add vertical offset for stacked pages
        return $this->currentPage * ($this->h + $this->pageGap) + $y;
    }

    /**
     * Draws a line segment.
     * 
     * @param float $x1 The starting X coordinate.
     * @param float $y1 The starting Y coordinate.
     * @param float $x2 The ending X coordinate.
     * @param float $y2 The ending Y coordinate.
     * @param string $class The element class.
     * @return void
     */
    public function Line($x1, $y1, $x2, $y2, $class = null)
    {
        $oy1 = $this->getOffsetY($y1);
        $oy2 = $this->getOffsetY($y2);
        $this->maxY = max($this->maxY, $oy1, $oy2);
        
        $svg = sprintf(
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="currentColor" stroke-width="%.3f" %s />',
            $x1, $oy1, $x2, $oy2, $this->lineWidth, $class ? 'class="' . $class . '"' : ''
        );
        if ($this->singlePage) {
            $this->svgElements[] = $svg;
        } else {
            $this->pages[$this->currentPage][] = $svg;
        }
    }

    /**
     * Draws a rectangle.
     * 
     * @param float $x The X coordinate of the top-left corner.
     * @param float $y The Y coordinate of the top-left corner.
     * @param float $w The width of the rectangle.
     * @param float $h The height of the rectangle.
     * @param string $style Drawing style. 'F' for fill, 'D' for stroke (default), 'FD' for fill and stroke.
     * @return void
     */
    public function Rect($x, $y, $w, $h, $style = '')
    {
        $oy = $this->getOffsetY($y);
        $this->maxY = max($this->maxY, $oy + $h);
        $fill = 'none';
        $stroke = 'currentColor';
        if (strpos($style, 'F') !== false) {
            $fill = 'currentColor';
        }
        if ($style === 'F') {
            $stroke = 'none';
        }
        
        $svg = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" stroke="%s" stroke-width="%.3f" class="sheet-music-rect" />',
            $x, $oy, $w, $h, $fill, $stroke, $this->lineWidth
        );
        if ($this->singlePage) {
            $this->svgElements[] = $svg;
        } else {
            $this->pages[$this->currentPage][] = $svg;
        }
    }

    /**
     * Draws a circle.
     * 
     * @param float $x The X coordinate of the center.
     * @param float $y The Y coordinate of the center.
     * @param float $r The radius of the circle.
     * @param string $style Drawing style. 'F' for fill, 'D' for stroke (default), 'FD' for fill and stroke.
     * @return void
     */
    public function Circle($x, $y, $r, $style = 'D')
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    /**
     * Draws an ellipse.
     * 
     * @param float $x The X coordinate of the center.
     * @param float $y The Y coordinate of the center.
     * @param float $rx The horizontal radius.
     * @param float $ry The vertical radius.
     * @param string $style Drawing style. 'F' for fill, 'D' for stroke (default), 'FD' for fill and stroke.
     * @param float $rotation The rotation angle in degrees.
     * @return void
     */
    public function Ellipse($x, $y, $rx, $ry, $style = 'D', $rotation = 0)
    {
        $oy = $this->getOffsetY($y);
        $this->maxY = max($this->maxY, $oy + $ry);
        $fill = 'none';
        $stroke = 'currentColor';
        if (strpos($style, 'F') !== false) {
            $fill = 'currentColor';
        }
        if ($style === 'F') {
            $stroke = 'none';
        }
        
        $transform = sprintf('translate(%.2f, %.2f)', $x, $oy);
        if ($rotation != 0) {
            $transform .= sprintf(' rotate(%.2f)', $rotation);
        }
        
        $svg = sprintf(
            '<ellipse cx="0" cy="0" rx="%.2f" ry="%.2f" fill="%s" stroke="%s" stroke-width="%.3f" transform="%s" class="sheet-music-ellipse" />',
            $rx, $ry, $fill, $stroke, $this->lineWidth, $transform
        );
        if ($this->singlePage) {
            $this->svgElements[] = $svg;
        } else {
            $this->pages[$this->currentPage][] = $svg;
        }
    }

    /**
     * Draws a text string at a specific position.
     * The position corresponds to the bottom-left of the text.
     * 
     * @param float $x The X coordinate.
     * @param float $y The Y coordinate.
     * @param string $txt The text to draw.
     * @return void
     */
    public function Text($x, $y, $txt)
    {
        $oy = $this->getOffsetY($y);
        $this->maxY = max($this->maxY, $oy);
        $txt = htmlspecialchars($txt, ENT_XML1, 'UTF-8');
        // Convert font size from points to mm (1pt = 0.3527mm)
        $fs = $this->fontSize * 0.3527;
        
        $styleAttr = sprintf(
            'font-family:\'%s\'; font-size:%.2fpx; font-weight:%s; font-style:%s;',
            $this->fontFamily, $fs, $this->fontWeight, $this->fontStyle
        );
        
        $svg = sprintf(
            '<text x="%.2f" y="%.2f" style="%s" fill="currentColor" class="sheet-music-text">%s</text>',
            $x, $oy, $styleAttr, $txt
        );
        if ($this->singlePage) {
            $this->svgElements[] = $svg;
        } else {
            $this->pages[$this->currentPage][] = $svg;
        }
    }

    /**
     * Estimates the width of a string in the current font.
     * This is an approximation as SVG doesn't load font metrics like FPDF.
     * The calculation is based on an average character width factor.
     *
     * @param string $s The string.
     * @return float The estimated width in mm.
     */
    public function GetStringWidth($s)
    {
        $s = (string)$s;
        $charCount = mb_strlen($s, 'UTF-8');
        $fontSizeInMm = $this->fontSize * 0.3527; // Convert points to mm
        $averageCharWidthFactor = 0.45; // Adjusted factor for a font like Times
        return $charCount * $fontSizeInMm * $averageCharWidthFactor;
    }

    /**
     * Draws a cell (a rectangular area) with optional text, border, and background.
     * Mimics FPDF's Cell method.
     * 
     * @param float $w The width of the cell. If 0, it extends to the right margin.
     * @param float $h The height of the cell.
     * @param string $txt The text to print.
     * @param int|string $border Indicates if borders should be drawn. Not fully implemented.
     * @param int $ln Indicates where the current position should go after the call.
     * @param string $align Text alignment ('L', 'C', 'R').
     * @param bool $fill Indicates if the cell background should be filled. Not implemented.
     * @param string $link Not implemented.
     * @return void
     */
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        
        if (!empty($txt)) {
            $oy = $this->getOffsetY($this->y + $h / 2 + ($this->fontSize * 0.3527) / 2 - 0.5);
            $this->maxY = max($this->maxY, $oy);
            
            $textAnchor = 'start';
            $tx = $this->x;
            if ($align === 'C') {
                $textAnchor = 'middle';
                $tx = $this->x + $w / 2;
            } elseif ($align === 'R') {
                $textAnchor = 'end';
                $tx = $this->x + $w;
            }
            
            $txt = htmlspecialchars($txt, ENT_XML1, 'UTF-8');
            $fs = $this->fontSize * 0.3527;
            $styleAttr = sprintf(
                'font-family:\'%s\'; font-size:%.2fpx; font-weight:%s; font-style:%s;',
                $this->fontFamily, $fs, $this->fontWeight, $this->fontStyle
            );
            
            $svg = sprintf(
                '<text x="%.2f" y="%.2f" text-anchor="%s" style="%s" fill="currentColor" class="sheet-music-text">%s</text>',
                $tx, $oy, $textAnchor, $styleAttr, $txt
            );
            if ($this->singlePage) {
                $this->svgElements[] = $svg;
            } else {
                $this->pages[$this->currentPage][] = $svg;
            }
        }
        
        if ($border) {
            $this->Rect($this->x, $this->y, $w, $h);
        }
        
        $this->x += $w;
        if ($ln > 0) {
            $this->Ln($h);
        }
    }

    /**
     * Draws a raw SVG path data string with transformations.
     * 
     * @param string $pathStr The SVG path data (the `d` attribute).
     * @param float $xOffset The X coordinate for the translation.
     * @param float $yOffset The Y coordinate for the translation.
     * @param float $scaleX The horizontal scaling factor.
     * @param float $scaleY The vertical scaling factor.
     * @param bool $fill If true, the path will be filled.
     * @param string|bool $stroke The stroke style ('solid', 'none', or boolean).
     * @return void
     */
    public function DrawSVGPath($pathStr, $xOffset, $yOffset, $scaleX, $scaleY, $fill = true, $stroke = 'solid')
    {
        $oy = $this->getOffsetY($yOffset);
        $this->maxY = max($this->maxY, $oy);
        
        $fillStyle = 'none';
        $strokeStyle = 'none';
        $strokeWidth = $this->lineWidth;

        if ($fill) {
            $fillStyle = 'currentColor';
        }
        if ($stroke === 'solid' || $stroke === true) {
            $strokeStyle = 'currentColor';
        }
        
        $transform = sprintf('translate(%.2f, %.2f) scale(%.4f, %.4f)', $xOffset, $oy, $scaleX, $scaleY);
        
        $svg = sprintf(
            '<path d="%s" fill="%s" stroke="%s" stroke-width="%.3f" transform="%s" class="sheet-music-path" />',
            $pathStr, $fillStyle, $strokeStyle, $strokeWidth, $transform
        );
        if ($this->singlePage) {
            $this->svgElements[] = $svg;
        } else {
            $this->pages[$this->currentPage][] = $svg;
        }
    }

    /**
     * Starts a new SVG group <g> with specified attributes.
     *
     * @param array $attributes Associative array of attributes (e.g., ['data-measure' => 1, 'data-start-tick' => 0])
     */
    public function startGroup($attributes = array())
    {
        $attrStr = '';
        foreach ($attributes as $key => $value) {
            $attrStr .= sprintf(' %s="%s"', htmlspecialchars($key, ENT_QUOTES, 'UTF-8'), htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }
        if ($this->singlePage) {
            $this->svgElements[] = "<g$attrStr>";
        } else {
            $this->pages[$this->currentPage][] = "<g$attrStr>";
        }
        $this->openGroupCount++;
    }

    /**
     * Closes the last opened SVG group </g>.
     */
    public function endGroup()
    {
        if ($this->openGroupCount > 0) {
            if ($this->singlePage) {
                $this->svgElements[] = "</g>";
            } else {
                $this->pages[$this->currentPage][] = "</g>";
            }
            $this->openGroupCount--;
        }
    }

    /**
     * Updates the attributes of a previously created group.
     * 
     * @param int $pageIndex The zero-based index of the page.
     * @param int $groupIndex The index of the <g> tag in the page's content array.
     * @param array $attributes The new attributes to add.
     */
    public function updateGroupAttributes($pageIndex, $groupIndex, $attributes = array())
    {
        $targetArray = $this->singlePage ? $this->svgElements : (isset($this->pages[$pageIndex]) ? $this->pages[$pageIndex] : null);

        if ($targetArray !== null && isset($targetArray[$groupIndex])) {
            $groupTag = $targetArray[$groupIndex];
            // Remove closing '>'
            $groupTag = rtrim($groupTag, '>');
            
            foreach ($attributes as $key => $value) {
                $groupTag .= sprintf(' %s="%s"', htmlspecialchars($key, ENT_QUOTES, 'UTF-8'), htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            }
            if ($this->singlePage) {
                $this->svgElements[$groupIndex] = $groupTag . '>';
            } else {
                $this->pages[$pageIndex][$groupIndex] = $groupTag . '>';
            }
        }
    }

    /**
     * Gets the raw SVG content array for a specific page.
     * 
     * @param int $pageIndex The zero-based index of the page.
     * @return array The array of SVG strings for the page.
     */
    public function getPageContent($pageIndex)
    {
        return $this->singlePage ? $this->svgElements : (isset($this->pages[$pageIndex]) ? $this->pages[$pageIndex] : []);
    }

    /**
     * Draw rest symbol
     *
     * @param float $noteX Horizontal position of the rest
     * @param float $systemY Vertical baseline of the staff system
     * @param string $typeStr Rest type string (e.g. "whole", "half", "quarter", "eighth", "16th", "32nd")
     */
    public function DrawRest($noteX, $systemY, $typeStr)
    {
        switch ($typeStr) {
            case 'whole':
            case '1':
                // Whole rest: hangs below the 4th line (D5)
                $this->Rect($noteX, $systemY + 2.0, 4.0, 1.2, 'F');
                return;

            case 'half':
            case '1/2':
                // Half rest: sits on top of the 3rd line (B4)
                $this->Rect($noteX, $systemY + 2.8, 4.0, 1.2, 'F');
                return;

            case 'quarter':
            case '1/4':
                // New, more standard quarter rest path
                $quarterPath = "M349 372c-14-12-44-43-65-102-21-58 25-95 50-114q12-7-1-21L219 9c-13-17-30-7-20 7 120 171-35 197-35 197s17 44 97 115c-84-22-139 40-97 104 41 64 120 78 127 80s18-4 7-11c-26-17-79-61-54-93 34-42 84-23 97-17 22 11 31-1 8-19";
                $this->DrawSVGPath($quarterPath, $noteX - 1.6, $systemY + 1, 0.012, 0.012, true);
                return;

            case 'eighth':
            case '1/8':
            case '16th':
            case '1/16':
            case '32nd':
            case '1/32':
                // New SVG path provided by user for the rest head/flag.
                $hookPath = "M 1.098 0 C 0.578 0.098 0.18 0.457 0 0.953 C -0.039 1.113 -0.039 1.152 -0.039 1.371 C -0.039 1.672 -0.02 1.832 0.121 2.07 C 0.32 2.469 0.738 2.789 1.215 2.906 C 1.715 3.047 3 3.153 4 2.153 L 4.941 0.598 C 4.844 0.477 4.645 0.438 4.523 0.535 C 4.484 0.574 4.422 0.656 4.383 0.715 C 4.203 1.016 3.746 1.551 3.508 1.75 C 3.289 1.93 3.168 1.949 2.969 1.871 C 2.789 1.773 2.73 1.672 2.609 1.133 C 2.492 0.598 2.352 0.355 2.051 0.156 C 1.773 -0.023 1.414 -0.082 1.098 0 z";
                $scale = 0.55;

                if ($typeStr === 'eighth' || $typeStr === '1/8') {
                    $this->Line($noteX + 3.0, $systemY + 2.0, $noteX + 2.0, $systemY + 8.1);
                    $this->DrawSVGPath($hookPath, $noteX + 0.3, $systemY + 2.2, $scale, $scale, true);
                } elseif ($typeStr === '16th' || $typeStr === '1/16') {
                    $this->Line($noteX + 3.0, $systemY + 2.0, $noteX + 2.0, $systemY + 8.1);
                    $this->DrawSVGPath($hookPath, $noteX + 0.3, $systemY + 2.2, $scale, $scale, true);
                    $this->DrawSVGPath($hookPath, $noteX - 0.1, $systemY + 4.2, $scale, $scale, true);
                } else { // 32nd rest or shorter
                    $this->Line($noteX + 3.0, $systemY + 2.0, $noteX + 2.0, $systemY + 8.1);
                    $this->DrawSVGPath($hookPath, $noteX + 0.3, $systemY + 2.2, $scale, $scale, true);
                    $this->DrawSVGPath($hookPath, $noteX - 0.1, $systemY + 4.2, $scale, $scale, true);
                    $this->DrawSVGPath($hookPath, $noteX - 0.5, $systemY + 6.2, $scale, $scale, true);
                }
                return;
        }
    }

    /**
     * Draws a tie or slur between two points.
     * 
     * @param float $sx The starting X coordinate.
     * @param float $sy The starting Y coordinate.
     * @param float $ex The ending X coordinate.
     * @param float $ey The ending Y coordinate.
     * @param string $direction The curve direction ('up' or 'down').
     * @return void
     */
    public function DrawTie($sx, $sy, $ex, $ey, $direction = 'down')
    {
        $dx = $ex - $sx;
        if ($dx <= 0) return;
        
        $bend = max(1.5, min(3.0, $dx * 0.25));
        $thickness = 0.4;
        
        // Convert Y coordinates to SVG space (top-down)
        $osy = $this->getOffsetY($sy);
        $oey = $this->getOffsetY($ey);

        $this->_drawTieCurve($sx, $osy, $ex, $oey, $direction, $bend, $thickness);
    }

    /**
     * Draws a tie using pre-calculated absolute SVG Y coordinates.
     * This is used for ties that span across page breaks in multi-page mode.
     * @param float $sx The starting X coordinate.
     * @param float $absSy The absolute starting Y coordinate.
     * @param float $ex The ending X coordinate.
     * @param float $absEy The absolute ending Y coordinate.
     * @param string $direction The curve direction ('up' or 'down').
     */
    public function DrawTieAbs($sx, $absSy, $ex, $absEy, $direction = 'down')
    {
        $dx = $ex - $sx;
        if ($dx <= 0) return;
        
        $bend = max(1.5, min(3.0, $dx * 0.25));
        $thickness = 0.4;
        
        $this->_drawTieCurve($sx, $absSy, $ex, $absEy, $direction, $bend, $thickness);
    }

    /**
     * Internal helper: render the actual tie SVG path.
     * 
     * @param float $sx Start X (mm)
     * @param float $absSy Start Y in absolute SVG space (mm, top-down)
     * @param float $ex End X (mm)
     * @param float $absEy End Y in absolute SVG space (mm, top-down)
     * @param string $direction 'up' or 'down'
     * @param float $bend Curve height
     * @param float $thickness Crescent thickness
     */
    private function _drawTieCurve($sx, $absSy, $ex, $absEy, $direction, $bend, $thickness)
    {
        $dx = $ex - $sx;
        $svgDy = $absEy - $absSy;

        // In SVG, Y increases downward, so 'down' bend = positive Y bump
        if ($direction === 'down') {
            $y_outer = $bend;
            $y_inner = $bend - $thickness;
        } else {
            $y_outer = -$bend;
            $y_inner = -$bend + $thickness;
        }

        $cp1x = $dx * 0.25;
        $cp2x = $dx * 0.75;

        // Closed crescent shape using two cubic Bezier curves
        $path = sprintf(
            'M 0 0 C %.2f %.2f, %.2f %.2f, %.2f %.2f C %.2f %.2f, %.2f %.2f, 0 0 Z',
            $cp1x, $y_outer,
            $cp2x, $y_outer + $svgDy,
            $dx, $svgDy,
            $cp2x, $y_inner + $svgDy,
            $cp1x, $y_inner
        );

        // Place on the last (most recently used) page
        $svgElem = sprintf(
            '<g transform="translate(%.2f, %.2f)"><path d="%s" fill="currentColor" stroke="none" class="sheet-music-tie" /></g>',
            $sx, $absSy, $path
        );
        if ($this->singlePage) {
            $this->svgElements[] = $svgElem;
        } else {
            $this->pages[max(0, $this->currentPage)][] = $svgElem;
        }
    }

    /**
     * Returns the absolute Y-offset for a given page index in multi-page mode.
     * 
     * @param int $pageIdx The zero-based index of the page.
     * @return float The Y offset in mm from the top of the SVG canvas.
     */
    public function getPageOffset($pageIdx)
    {
        return $pageIdx * ($this->h + $this->pageGap);
    }

    /**
     * Finalizes and returns the complete SVG string.
     * 
     * @param string $name Not used.
     * @param string $dest Not used.
     * @return string The raw SVG content.
     */
    public function Output($name = '', $dest = '')
    {
        if ($this->singlePage) {
            return $this->outputSinglePage();
        }
        return $this->outputMultiPage();
    }

    /**
     * Generates the SVG for a single, continuous page layout.
     * 
     * @return string The raw SVG content.
     */
    private function outputSinglePage() {
        // Calculate total height based on content, with some padding
        $totalHeight = $this->maxY + $this->bMargin;
        
        // Final check to close any remaining open groups
        while ($this->openGroupCount > 0) {
            $this->endGroup();
        }
        
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f %.2f" class="sheet-music-svg" style="width: 100%%; height: auto; display: block; color: #000000;">',
            $this->w, $totalHeight
        );
        $svg .= "\n<style>
            .sheet-music-svg {
                background: transparent;
            }
            .sheet-music-page-bg {
                fill: var(--sheet-music-bg, #ffffff);
                stroke: none;
            }
            .sheet-music-text {
                fill: var(--sheet-music-color, #000000);
                user-select: none;
            }
            .sheet-music-line, .sheet-music-rect, .sheet-music-ellipse {
                stroke: var(--sheet-music-color, #000000);
            }
            .sheet-music-rect[fill=\"currentColor\"], .sheet-music-ellipse[fill=\"currentColor\"], .sheet-music-path[fill=\"currentColor\"] {
                fill: var(--sheet-music-color, #000000);
            }
            .sheet-music-tie {
                fill: var(--sheet-music-color, #000000);
            }
        </style>\n";
        
        // Draw a single background for the entire content
        $svg .= sprintf(
            '  <rect x="0" y="0" width="%.2f" height="%.2f" class="sheet-music-page-bg" rx="1" />' . "\n",
            $this->w, $totalHeight
        );

        foreach ($this->svgElements as $elem) {
            $svg .= "  " . $elem . "\n";
        }
        
        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Generates the SVG for a multi-page, stacked layout.
     * 
     * @return string The raw SVG content.
     */
    private function outputMultiPage()
    {
        $totalPages = count($this->pages);
        if ($totalPages <= 0) {
            $totalPages = 1;
        }
        
        $totalHeight = $totalPages * $this->h + ($totalPages - 1) * $this->pageGap;
        
        // Final check to close any remaining open groups
        while ($this->openGroupCount > 0) {
            $this->endGroup();
        }
        
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f %.2f" class="sheet-music-svg" style="width: 100%%; height: auto; display: block; color: #000000;">',
            $this->w, $totalHeight
        );
        $svg .= "\n<style>
            .sheet-music-svg { background: transparent; }
            .sheet-music-page-bg { fill: var(--sheet-music-bg, #ffffff); stroke: #ccc; stroke: none; }
            .sheet-music-text { fill: var(--sheet-music-color, #000000); user-select: none; }
            .sheet-music-line, .sheet-music-rect, .sheet-music-ellipse { stroke: var(--sheet-music-color, #000000); }
            .sheet-music-rect[fill=\"currentColor\"], .sheet-music-ellipse[fill=\"currentColor\"], .sheet-music-path[fill=\"currentColor\"] { fill: var(--sheet-music-color, #000000); }
            .sheet-music-tie { fill: var(--sheet-music-color, #000000); }
        </style>\n";
        
        for ($p = 0; $p < $totalPages; $p++) {
            $pageOffsetY = $p * ($this->h + $this->pageGap);
            
            $svg .= sprintf(
                '  <rect x="0" y="%.2f" width="%.2f" height="%.2f" class="sheet-music-page-bg" rx="1" />' . "\n",
                $pageOffsetY, $this->w, $this->h
            );
            
            $yearStr = !empty($this->year) ? $this->year : date('Y');
            $copyrightText = 'Copyright ' . htmlspecialchars($this->composer, ENT_XML1, 'UTF-8') . ' ' . $yearStr;
            $footerY = $pageOffsetY + $this->h - 8;
            $fs = 8 * 0.3527;
            
            $svg .= sprintf('  <text x="%.2f" y="%.2f" text-anchor="start" style="font-family:\'Times\'; font-size:%.2fpx; font-style:italic;" class="sheet-music-text">%s</text>' . "\n", $this->lMargin, $footerY, $fs, $copyrightText);
            $svg .= sprintf('  <text x="%.2f" y="%.2f" text-anchor="end" style="font-family:\'Times\'; font-size:%.2fpx;" class="sheet-music-text">%d of %d</text>' . "\n", $this->w - $this->rMargin, $footerY, $fs, $p + 1, $totalPages);
            
            if (isset($this->pages[$p])) {
                foreach ($this->pages[$p] as $elem) {
                    $svg .= "  " . $elem . "\n";
                }
            }
        }
        
        $svg .= '</svg>';
        return $svg;
    }
}

<?php

namespace MusicXML;

/**
 * SheetMusicPDF class extending FPDF to draw music notation elements
 */
class SheetMusicPDF extends \Fpdf\Fpdf
{
    use SheetMusicTrait;
    /**
     * Composer name
     *
     * @var string
     */
    public $composer = 'Unknown';

    /**
     * Copyright year
     *
     * @var string
     */
    public $year = '';

    private $lineWidth = 0.12;

    /**
     * Draw page footer with copyright and page number
     *
     * @return void
     */
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Times', 'I', 8);
        
        $yearStr = !empty($this->year) ? $this->year : date('Y');
        $copyrightText = 'Copyright ' . $this->composer . ' ' . $yearStr;
        
        $this->SetX(12);
        $this->Cell(0, 10, $copyrightText, 0, 0, 'L');
        
        $this->SetX(12);
        $this->Cell(0, 10, $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }

    /**
     * Draw an ellipse using Bezier curves
     *
     * @param float $x Center X coordinate
     * @param float $y Center Y coordinate
     * @param float $rx Semi-major axis radius
     * @param float $ry Semi-minor axis radius
     * @param string $style Border/Fill style ('D', 'F', 'FD', 'DF')
     * @return void
     */
    public function Ellipse($x, $y, $rx, $ry, $style = 'D', $rotation = 0)
    {
        // Ganti match dengan switch agar kompatibel PHP 5
        switch ($style) {
            case 'F':
                $op = 'f';   // fill saja
                break;
            case 'FD':
            case 'DF':
                $op = 'B';   // fill + stroke
                break;
            case 'D':
            default:
                $op = 'S';   // stroke saja
                break;
        }

        $k = $this->k;
        $h = $this->h;
        $c = 0.5522847498;

        $lx = $rx * $c;
        $ly = $ry * $c;

        // Simpan transformasi lama
        $this->_out('q');

        // Rotasi sistem koordinat di sekitar titik pusat
        $rot = deg2rad($rotation);
        $cosR = cos($rot);
        $sinR = sin($rot);
        $this->_out(sprintf('%.5f %.5f %.5f %.5f %.5f %.5f cm',
            $cosR, $sinR, -$sinR, $cosR, $x * $k, ($h - $y) * $k));

        // Gambar elips di koordinat lokal (tanpa rotasi)
        $this->_out(sprintf(
            '%.2f %.2f m %.2f %.2f %.2f %.2f %.2f %.2f c',
            $rx * $k, 0,
            $rx * $k, -$ly * $k,
            $lx * $k, -$ry * $k,
            0, -$ry * $k
        ));
        $this->_out(sprintf(
            '%.2f %.2f %.2f %.2f %.2f %.2f c',
            -$lx * $k, -$ry * $k,
            -$rx * $k, -$ly * $k,
            -$rx * $k, 0
        ));
        $this->_out(sprintf(
            '%.2f %.2f %.2f %.2f %.2f %.2f c',
            -$rx * $k, $ly * $k,
            -$lx * $k, $ry * $k,
            0, $ry * $k
        ));
        $this->_out(sprintf(
            '%.2f %.2f %.2f %.2f %.2f %.2f c %s',
            $lx * $k, $ry * $k,
            $rx * $k, $ly * $k,
            $rx * $k, 0,
            $op
        ));

        // Kembalikan transformasi
        $this->_out('Q');
    }


    /**
     * Draw a circle using Ellipse
     *
     * @param float $x Center X coordinate
     * @param float $y Center Y coordinate
     * @param float $r Radius
     * @param string $style Border/Fill style ('D', 'F', 'FD', 'DF')
     * @return void
     */
    public function Circle($x, $y, $r, $style = 'D')
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    /**
     * Convert SVG Arc to Cubic Bezier segments
     * Standard implementation of elliptical arc to cubic bezier curves conversion.
     *
     * @param float $x1 Start X coordinate
     * @param float $y1 Start Y coordinate
     * @param float $rx X radius
     * @param float $ry Y radius
     * @param float $angle Rotation angle in degrees
     * @param int $largeArcFlag Large arc flag (0 or 1)
     * @param int $sweepFlag Sweep direction flag (0 or 1)
     * @param float $x2 End X coordinate
     * @param float $y2 End Y coordinate
     * @return array List of bezier control/end points
     */
    private function arcToCubicBezier($x1, $y1, $rx, $ry, $angle, $largeArcFlag, $sweepFlag, $x2, $y2)
    {
        if ($rx == 0 || $ry == 0) {
            return [];
        }

        $rx = abs($rx);
        $ry = abs($ry);

        $phi = deg2rad($angle);
        $cosPhi = cos($phi);
        $sinPhi = sin($phi);

        // Step 1: Translate to origin
        $dx = ($x1 - $x2) / 2.0;
        $dy = ($y1 - $y2) / 2.0;
        $x1p = $cosPhi * $dx + $sinPhi * $dy;
        $y1p = -$sinPhi * $dx + $cosPhi * $dy;

        // Correct radii if necessary
        $prx = $rx * $rx;
        $pry = $ry * $ry;
        $px1p = $x1p * $x1p;
        $py1p = $y1p * $y1p;

        $radiiCheck = $px1p / $prx + $py1p / $pry;
        if ($radiiCheck > 1) {
            $rx = sqrt($radiiCheck) * $rx;
            $ry = sqrt($radiiCheck) * $ry;
            $prx = $rx * $rx;
            $pry = $ry * $ry;
        }

        // Step 2: Compute center
        $sign = ($largeArcFlag == $sweepFlag) ? -1 : 1;
        $sq = (($prx * $pry) - ($prx * $py1p) - ($pry * $px1p)) / (($prx * $py1p) + ($pry * $px1p));
        $sq = max(0, $sq);
        $coef = $sign * sqrt($sq);
        $cxp = $coef * (($rx * $y1p) / $ry);
        $cyp = $coef * (-($ry * $x1p) / $rx);

        $cx = $cosPhi * $cxp - $sinPhi * $cyp + ($x1 + $x2) / 2.0;
        $cy = $sinPhi * $cxp + $cosPhi * $cyp + ($y1 + $y2) / 2.0;

        // Step 3: Compute angles
        $ux = ($x1p - $cxp) / $rx;
        $uy = ($y1p - $cyp) / $ry;
        $vx = (-$x1p - $cxp) / $rx;
        $vy = (-$cyp - $y1p) / $ry;

        $theta1 = atan2($uy, $ux);
        
        $dot = $ux * $vx + $uy * $vy;
        $len = sqrt($ux*$ux + $uy*$uy) * sqrt($vx*$vx + $vy*$vy);
        $dot = max(-1.0, min(1.0, $dot / $len));
        $dTheta = acos($dot);
        if (($ux * $vy - $uy * $vx) < 0) {
            $dTheta = -$dTheta;
        }

        if ($sweepFlag == 0 && $dTheta > 0) {
            $dTheta -= 2.0 * M_PI;
        } elseif ($sweepFlag == 1 && $dTheta < 0) {
            $dTheta += 2.0 * M_PI;
        }

        $segments = ceil(abs($dTheta) / (M_PI / 2.0));
        $curves = [];
        
        $theta = $theta1;
        $delta = $dTheta / $segments;
        
        for ($s = 0; $s < $segments; $s++) {
            $t = $theta;
            $theta += $delta;
            
            $cosT = cos($t);
            $sinT = sin($t);
            $cosTheta = cos($theta);
            $sinTheta = sin($theta);
            
            $sx = $cosPhi * $rx * $cosT - $sinPhi * $ry * $sinT + $cx;
            $sy = $sinPhi * $rx * $cosT + $cosPhi * $ry * $sinT + $cy;
            
            $ex = $cosPhi * $rx * $cosTheta - $sinPhi * $ry * $sinTheta + $cx;
            $ey = $sinPhi * $rx * $cosTheta + $cosPhi * $ry * $sinTheta + $cy;
            
            $alpha = sin($delta) * (sqrt(4.0 + 3.0 * tan($delta / 2.0) * tan($delta / 2.0)) - 1.0) / 3.0;
            
            $dxT = - $cosPhi * $rx * $sinT - $sinPhi * $ry * $cosT;
            $dyT = - $sinPhi * $rx * $sinT + $cosPhi * $ry * $cosT;
            
            $dxTheta = - $cosPhi * $rx * $sinTheta - $sinPhi * $ry * $cosTheta;
            $dyTheta = - $sinPhi * $rx * $sinTheta + $cosPhi * $ry * $cosTheta;
            
            $cp1x = $sx + $alpha * $dxT;
            $cp1y = $sy + $alpha * $dyT;
            
            $cp2x = $ex - $alpha * $dxTheta;
            $cp2y = $ey - $alpha * $dyTheta;
            
            $curves[] = [$cp1x, $cp1y, $cp2x, $cp2y, $ex, $ey];
        }
        
        return $curves;
    }

    /**
     * Draw an SVG path string onto the PDF canvas using raw PDF operators
     *
     * @param string $pathStr SVG path string
     * @param float $xOffset X placement offset
     * @param float $yOffset Y placement offset
     * @param float $scaleX X scaling factor
     * @param float $scaleY Y scaling factor
     * @param bool|string $fill Fill flag or fill/stroke style indicator ('B', true, false)
     * @return void
     */
    public function DrawSVGPath($pathStr, $xOffset, $yOffset, $scaleX, $scaleY, $fill = true, $stroke = 'solid')
    {
        // Tokenize numbers and commands.
        // This regex is improved to handle numbers that are not separated by spaces (e.g., c-14-12...).
        // It looks for a command letter OR a number (positive/negative float/int).
        preg_match_all('/([a-zA-Z])|(-?\d*\.?\d+)/', $pathStr, $matches);
        $tokens = $matches[0];
        
        $k = $this->k;
        $h = $this->h;
        
        $pdfCmds = "";
        $i = 0;
        $count = count($tokens);
        
        $currentX = 0;
        $currentY = 0;
        $cmd = 'M'; // Default command
        $lastQcpX = 0;
        $lastQcpY = 0;
        $lastC2X = 0; // For smooth cubic curves (S, s)
        $lastC2Y = 0; // For smooth cubic curves (S, s)
        $lastCmdWasQ = false;
        
        $xy = function($px, $py) use ($k, $h, $xOffset, $yOffset, $scaleX, $scaleY) {
            $tx = $xOffset + $px * $scaleX;
            $ty = $yOffset + $py * $scaleY;
            return sprintf('%.2f %.2f', $tx * $k, ($h - $ty) * $k);
        };
        
        while ($i < $count) {
            $token = $tokens[$i];
            if (preg_match('/[MLCQHVZTAmlcqhvztas]/i', $token)) {
                $cmd = $token;
                $i++;
            }
            
            if ($i >= $count && preg_match('/[Zz]/', $cmd) === 0) {
                break;
            }
            
            $isQ = false;
            switch ($cmd) {
                case 'M':
                    $px = (float)$tokens[$i++];
                    $py = (float)$tokens[$i++];
                    $pdfCmds .= $xy($px, $py) . " m ";
                    $currentX = $px;
                    $currentY = $py;
                    break;
                case 'm':
                    $px = $currentX + (float)$tokens[$i++];
                    $py = $currentY + (float)$tokens[$i++];
                    $pdfCmds .= $xy($px, $py) . " m ";
                    $currentX = $px;
                    $currentY = $py;
                    break;
                case 'L':
                    $px = (float)$tokens[$i++];
                    $py = (float)$tokens[$i++];
                    $pdfCmds .= $xy($px, $py) . " l ";
                    $currentX = $px;
                    $currentY = $py;
                    break;
                case 'l':
                    $px = $currentX + (float)$tokens[$i++];
                    $py = $currentY + (float)$tokens[$i++];
                    $pdfCmds .= $xy($px, $py) . " l ";
                    $currentX = $px;
                    $currentY = $py;
                    break;
                case 'H':
                    $px = (float)$tokens[$i++];
                    $pdfCmds .= $xy($px, $currentY) . " l ";
                    $currentX = $px;
                    break;
                case 'h':
                    $px = $currentX + (float)$tokens[$i++];
                    $pdfCmds .= $xy($px, $currentY) . " l ";
                    $currentX = $px;
                    break;
                case 'V':
                    $py = (float)$tokens[$i++];
                    $pdfCmds .= $xy($currentX, $py) . " l ";
                    $currentY = $py;
                    break;
                case 'v':
                    $py = $currentY + (float)$tokens[$i++];
                    $pdfCmds .= $xy($currentX, $py) . " l ";
                    $currentY = $py;
                    break;
                case 'C':
                    $x1 = (float)$tokens[$i++];
                    $y1 = (float)$tokens[$i++];
                    $x2 = (float)$tokens[$i++];
                    $y2 = (float)$tokens[$i++];
                    $x3 = (float)$tokens[$i++];
                    $y3 = (float)$tokens[$i++];
                    $pdfCmds .= sprintf(
                        '%s %s %s c ',
                        $xy($x1, $y1),
                        $xy($x2, $y2),
                        $xy($x3, $y3)
                    );
                    $lastC2X = $x2;
                    $lastC2Y = $y2;
                    $currentX = $x3;
                    $currentY = $y3;
                    break;
                case 'c':
                    $x1 = $currentX + (float)$tokens[$i++];
                    $y1 = $currentY + (float)$tokens[$i++];
                    $x2 = $currentX + (float)$tokens[$i++];
                    $y2 = $currentY + (float)$tokens[$i++];
                    $x3 = $currentX + (float)$tokens[$i++];
                    $y3 = $currentY + (float)$tokens[$i++];
                    $pdfCmds .= sprintf(
                        '%s %s %s c ',
                        $xy($x1, $y1),
                        $xy($x2, $y2),
                        $xy($x3, $y3)
                    );
                    $lastC2X = $x2;
                    $lastC2Y = $y2;
                    $currentX = $x3;
                    $currentY = $y3;
                    break;
                case 'S':
                    $x2 = (float)$tokens[$i++];
                    $y2 = (float)$tokens[$i++];
                    $x3 = (float)$tokens[$i++];
                    $y3 = (float)$tokens[$i++];
                    // Reflection of the previous control point
                    $x1 = 2 * $currentX - $lastC2X;
                    $y1 = 2 * $currentY - $lastC2Y;
                    $pdfCmds .= sprintf('%s %s %s c ', $xy($x1, $y1), $xy($x2, $y2), $xy($x3, $y3));
                    $lastC2X = $x2;
                    $lastC2Y = $y2;
                    $currentX = $x3;
                    $currentY = $y3;
                    break;
                case 's':
                    $dx2 = (float)$tokens[$i++];
                    $dy2 = (float)$tokens[$i++];
                    $dx3 = (float)$tokens[$i++];
                    $dy3 = (float)$tokens[$i++];
                    
                    // Reflection of the previous control point
                    $x1 = 2 * $currentX - $lastC2X;
                    $y1 = 2 * $currentY - $lastC2Y;
                    
                    $x2 = $currentX + $dx2;
                    $y2 = $currentY + $dy2;
                    $x3 = $currentX + $dx3;
                    $y3 = $currentY + $dy3;
                    
                    $pdfCmds .= sprintf('%s %s %s c ', $xy($x1, $y1), $xy($x2, $y2), $xy($x3, $y3));
                    $lastC2X = $x2;
                    $lastC2Y = $y2;
                    $currentX = $x3;
                    $currentY = $y3;
                    break;
                case 'Q':
                    $x1 = (float)$tokens[$i++];
                    $y1 = (float)$tokens[$i++];
                    $x2 = (float)$tokens[$i++];
                    $y2 = (float)$tokens[$i++];
                    // Konversi Quadratic ke Cubic Bezier
                    $cx1 = $currentX + (2.0/3.0) * ($x1 - $currentX);
                    $cy1 = $currentY + (2.0/3.0) * ($y1 - $currentY);
                    $cx2 = $x2 + (2.0/3.0) * ($x1 - $x2);
                    $cy2 = $y2 + (2.0/3.0) * ($y1 - $y2);
                    $pdfCmds .= sprintf(
                        '%s %s %s c ',
                        $xy($cx1, $cy1), $xy($cx2, $cy2), $xy($x2, $y2)
                    );
                    $currentX = $x2;
                    $currentY = $y2;
                    break;
                case 'q':
                    $dx1 = (float)$tokens[$i++];
                    $dy1 = (float)$tokens[$i++];
                    $dx2 = (float)$tokens[$i++];
                    $dy2 = (float)$tokens[$i++];
                    $x1 = $currentX + $dx1;
                    $y1 = $currentY + $dy1;
                    $x2 = $currentX + $dx2;
                    $y2 = $currentY + $dy2;
                    // Konversi Quadratic ke Cubic Bezier
                    $cx1 = $currentX + (2.0/3.0) * ($x1 - $currentX);
                    $cy1 = $currentY + (2.0/3.0) * ($y1 - $currentY);
                    $cx2 = $x2 + (2.0/3.0) * ($x1 - $x2);
                    $cy2 = $y2 + (2.0/3.0) * ($y1 - $y2);
                    $pdfCmds .= sprintf(
                        '%s %s %s c ',
                        $xy($cx1, $cy1), $xy($cx2, $cy2), $xy($x2, $y2)
                    );
                    $currentX = $x2;
                    $currentY = $y2;
                    break;
                case 'T':
                    $x2 = (float)$tokens[$i++];
                    $y2 = (float)$tokens[$i++];
                    if ($lastCmdWasQ) {
                        $x1 = 2.0 * $currentX - $lastQcpX;
                        $y1 = 2.0 * $currentY - $lastQcpY;
                    } else {
                        $x1 = $currentX;
                        $y1 = $currentY;
                    }
                    // Quadratic to cubic bezier conversion
                    $cx1 = $currentX + (2.0/3.0) * ($x1 - $currentX);
                    $cy1 = $currentY + (2.0/3.0) * ($y1 - $currentY);
                    $cx2 = $x2 + (2.0/3.0) * ($x1 - $x2);
                    $cy2 = $y2 + (2.0/3.0) * ($y1 - $y2);
                    $pdfCmds .= sprintf(
                        '%s %s %s c ',
                        $xy($cx1, $cy1),
                        $xy($cx2, $cy2),
                        $xy($x2, $y2)
                    );
                    $lastQcpX = $x1;
                    $lastQcpY = $y1;
                    $isQ = true;
                    $currentX = $x2;
                    $currentY = $y2;
                    break;
                case 't':
                    $dx2 = (float)$tokens[$i++];
                    $dy2 = (float)$tokens[$i++];
                    if ($lastCmdWasQ) {
                        $x1 = 2.0 * $currentX - $lastQcpX;
                        $y1 = 2.0 * $currentY - $lastQcpY;
                    } else {
                        $x1 = $currentX;
                        $y1 = $currentY;
                    }
                    $x2 = $currentX + $dx2;
                    $y2 = $currentY + $dy2;
                    // Quadratic to cubic bezier conversion
                    $cx1 = $currentX + (2.0/3.0) * ($x1 - $currentX);
                    $cy1 = $currentY + (2.0/3.0) * ($y1 - $currentY);
                    $cx2 = $x2 + (2.0/3.0) * ($x1 - $x2);
                    $cy2 = $y2 + (2.0/3.0) * ($y1 - $y2);
                    $pdfCmds .= sprintf(
                        '%s %s %s c ',
                        $xy($cx1, $cy1),
                        $xy($cx2, $cy2),
                        $xy($x2, $y2)
                    );
                    $lastQcpX = $x1;
                    $lastQcpY = $y1;
                    $isQ = true;
                    $currentX = $x2;
                    $currentY = $y2;
                    break;
                case 'A':
                    $rx = (float)$tokens[$i++];
                    $ry = isset($tokens[$i]) ? (float)$tokens[$i++] : $rx;
                    $angle = isset($tokens[$i]) ? (float)$tokens[$i++] : 0.0;
                    $largeArcFlag = isset($tokens[$i]) ? (int)$tokens[$i++] : 0;
                    $sweepFlag = isset($tokens[$i]) ? (int)$tokens[$i++] : 0;
                    $x2 = isset($tokens[$i]) ? (float)$tokens[$i++] : $currentX;
                    $y2 = isset($tokens[$i]) ? (float)$tokens[$i++] : $currentY;
                    
                    $curves = $this->arcToCubicBezier($currentX, $currentY, $rx, $ry, $angle, $largeArcFlag, $sweepFlag, $x2, $y2);
                    foreach ($curves as $c) {
                        $pdfCmds .= sprintf(
                            '%s %s %s c ',
                            $xy($c[0], $c[1]),
                            $xy($c[2], $c[3]),
                            $xy($c[4], $c[5])
                        );
                    }
                    $currentX = $x2;
                    $currentY = $y2;
                    break;
                case 'a':
                    $rx = (float)$tokens[$i++];
                    $ry = isset($tokens[$i]) ? (float)$tokens[$i++] : $rx;
                    $angle = isset($tokens[$i]) ? (float)$tokens[$i++] : 0.0;
                    $largeArcFlag = isset($tokens[$i]) ? (int)$tokens[$i++] : 0;
                    $sweepFlag = isset($tokens[$i]) ? (int)$tokens[$i++] : 0;
                    $dx = isset($tokens[$i]) ? (float)$tokens[$i++] : 0.0;
                    $dy = isset($tokens[$i]) ? (float)$tokens[$i++] : 0.0;
                    $x2 = $currentX + $dx;
                    $y2 = $currentY + $dy;
                    
                    $curves = $this->arcToCubicBezier($currentX, $currentY, $rx, $ry, $angle, $largeArcFlag, $sweepFlag, $x2, $y2);
                    foreach ($curves as $c) {
                        $pdfCmds .= sprintf(
                            '%s %s %s c ',
                            $xy($c[0], $c[1]),
                            $xy($c[2], $c[3]),
                            $xy($c[4], $c[5])
                        );
                    }
                    $currentX = $x2;
                    $currentY = $y2;
                    break;
                case 'Z':
                case 'z':
                    $pdfCmds .= " h ";
                    break;
            }
            $lastCmdWasQ = $isQ;
        }
        
        // Tentukan operator akhir berdasarkan fill dan stroke
        if ($stroke === 'none') {
            $op = $fill ? " f* " : ""; // gunakan even‑odd fill rule
        } elseif ($stroke === 'solid' || $stroke === true) {
            $op = $fill ? " f* " : " S ";
        } elseif ($stroke === 'B') {
            $op = " B ";
        } else {
            $op = $fill ? " f* " : " S ";
        }


        $pdfCmds .= $op;
        $this->_out($pdfCmds);

    }
    
    /**
     * Draws a line segment.
     * This method is an FPDF-compatible wrapper. The `$class` parameter is ignored.
     * 
     * @param float $x1 The starting X coordinate.
     * @param float $y1 The starting Y coordinate.
     * @param float $x2 The ending X coordinate.
     * @param float $y2 The ending Y coordinate.
     * @param string|null $class (Ignored) The element class for SVG compatibility.
     * @return void
     */
    public function Line($x1, $y1, $x2, $y2, $class = null) // NOSONAR
    {
        parent::Line($x1, $y1, $x2, $y2);
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
                $quarterPath = "M 349 372 c -14 -12 -44 -43 -65 -102 -21 -58 25 -95 50 -114 q 12 -7 -1 -21 L 219 9 c -13 -17 -30 -7 -20 7 120 171 -35 197 -35 197 s 17 44 97 115 c -84 -22 -139 40 -97 104 41 64 120 78 127 80 s 18 -4 7 -11 c -26 -17 -79 -61 -54 -93 34 -42 84 -23 97 -17 22 11 31 -1 8 -19";
                $this->DrawSVGPath($quarterPath, $noteX - 1.6, $systemY + 1, 0.012, 0.012, true);
                return;

            case 'eighth':
            case '1/8':
            case '16th':
            case '1/16':
            case '32nd':
            case '1/32':
                // Improvement: Using a more standard and accurate SVG path for 1/8, 1/16, and 1/32 rests.
                // This fixes the odd "head" shape and inverted angles.
                $oldWidth = $this->lineWidth;
                $this->SetLineWidth(0.35);
                
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
                $this->SetLineWidth($oldWidth);
                return;
        }
    }

    /**
     * Draw a tie curve between two notes
     *
     * @param float $sx Start X coordinate
     * @param float $sy Start Y coordinate
     * @param float $ex End X coordinate
     * @param float $ey End Y coordinate
     * @param string $direction Bend direction ('up' or 'down')
     * @return void
     */
    public function DrawTie($sx, $sy, $ex, $ey, $direction = 'down')
    {
        $k = $this->k;
        $h_pdf = $this->h;
        
        $dx = $ex - $sx;
        if ($dx <= 0) return;
        
        $bend = max(1.5, min(3.0, $dx * 0.25));
        $thickness = 0.4;
        
        $xy = function($px, $py) use ($k, $h_pdf) {
            return sprintf('%.2f %.2f', $px * $k, ($h_pdf - $py) * $k);
        };
        
        if ($direction === 'up') {
            $cp1x = $sx + $dx * 0.25;
            $cp1y = $sy - $bend;
            $cp2x = $sx + $dx * 0.75;
            $cp2y = $ey - $bend;
            
            $cp3x = $sx + $dx * 0.75;
            $cp3y = $ey - $bend + $thickness;
            $cp4x = $sx + $dx * 0.25;
            $cp4y = $sy - $bend + $thickness;
        } else {
            $cp1x = $sx + $dx * 0.25;
            $cp1y = $sy + $bend;
            $cp2x = $sx + $dx * 0.75;
            $cp2y = $ey + $bend;
            
            $cp3x = $sx + $dx * 0.75;
            $cp3y = $ey + $bend - $thickness;
            $cp4x = $sx + $dx * 0.25;
            $cp4y = $sy + $bend - $thickness;
        }
        
        $pdfCmds = sprintf(
            '%s m %s %s %s c %s %s %s c f',
            $xy($sx, $sy),
            $xy($cp1x, $cp1y),
            $xy($cp2x, $cp2y),
            $xy($ex, $ey),
            $xy($cp3x, $cp3y),
            $xy($cp4x, $cp4y),
            $xy($sx, $sy)
        );
        $this->_out($pdfCmds);
    }
}
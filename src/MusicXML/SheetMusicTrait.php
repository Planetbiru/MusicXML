<?php

namespace MusicXML;

/**
 * Trait containing common drawing methods for music notation elements,
 * shared between SheetMusicPDF and SheetMusicSVG renderers.
 * 
 * @author Kamshory
 */
trait SheetMusicTrait
{
    /**
     * Draw a note flag (eighth, sixteenth, thirty-second) at the specified position and direction
     * 
     * @param float $x X coordinate
     * @param float $y Y coordinate
     * @param string $direction 'up' or 'down'
     * @param string $type 'eighth', '16th', or '32nd'
     */
    public function DrawNoteFlag($x, $y, $direction = 'up', $type = 'eighth')
    {
        // Path SVG daun not ramping dan tajam di ujung
        $flagPathUp = "M -0.112 3.631 
                    C -0.112 0 -0.3031 0 0 0 
                    C 0.28 0.1911 0 0 0 0 
                    C 0.42 0.6879 0.512 0.7834 0.531 0.898 
                    C 1.4 2.8 1.4 2.8 2.327 4.051 
                    C 4.028 5.943 4.525 7.071 4.525 8.581 
                    C 4.506 9.994 3.263 13.014 2.996 12.899 
                    C 3.378 11.829 3.913 10.682 4.047 9.727 
                    C 4.219 8.561 3.741 6.879 1.831 5.16 
                    C 0.779 4.294 0 4.2 -0.112 3.631 Z";

        $flagPathDown = "M -0.112 -3.631 
                        C -0.112 0 -0.3031 0 0 0 
                        C 0.28 -0.1911 0 0 0 0 
                        C 0.42 -0.6879 0.512 -0.7834 0.531 -0.898 
                        C 1.4 -2.8 1.4 -2.8 2.327 -4.051 
                        C 4.028 -5.943 4.525 -7.071 4.525 -8.581 
                        C 4.506 -9.994 3.263 -13.014 2.996 -12.899 
                        C 3.378 -11.829 3.913 -10.682 4.047 -9.727 
                        C 4.219 -8.561 3.741 -6.879 1.831 -5.16 
                        C 0.779 -4.294 0 -4.2 -0.112 -3.631 Z";

        $path = ($direction === 'up') ? $flagPathUp : $flagPathDown;

        // Skala proporsional (lebih kecil agar ramping)
        $scaleX = 0.40;
        $scaleY = 0.32;

        // Gambar sesuai tipe not
        if ($type === 'eighth' || $type === '1/8') {
            $this->DrawSVGPath($path, $x, $y, $scaleX, $scaleY, true);
        } elseif ($type === '16th' || $type === '1/16') {
            $this->DrawSVGPath($path, $x, $y, $scaleX, $scaleY, true);
            $this->DrawSVGPath($path, $x, $y + (($direction === 'up') ? 1.7 : -1.7), $scaleX, $scaleY, true);
        } elseif ($type === '32nd' || $type === '1/32') {
            $this->DrawSVGPath($path, $x, $y, $scaleX, $scaleY, true);
            $this->DrawSVGPath($path, $x, $y + (($direction === 'up') ? 1.7 : -1.7), $scaleX, $scaleY, true);
            $this->DrawSVGPath($path, $x, $y + (($direction === 'up') ? 3.4 : -3.4), $scaleX, $scaleY, true);
        }
    }

    /**
     * Draw the treble clef (G-clef)
     *
     * @param float $x Placement X coordinate
     * @param float $y Placement Y coordinate
     * @return void
     */
    public function DrawTrebleClef($x, $y)
    {
        // High-quality new vector G-Clef path from user
        $path = "M165 177q-24 30-26 60-2 34 19 64 23 32 57 34h21l4 23q3 15 2 26-1 15-9 24-9 10-23 9-6 0-11-3l10-5q9-7 10-19 0-12-6-21-8-9-20-10t-22 9q-7 10-9 22-1 19 14 31 13 11 31 12a52 52 0 0 0 34-9q17-13 18-31 1-15-2-34l-4-29q17-5 28-20 12-15 13-36 3-25-12-46a51 51 0 0 0-46-23l-5-36q20-16 32-42 12-24 14-53 0-17-5-41-7-31-22-33-6 0-12 6a89 89 0 0 0-25 37 167 167 0 0 0-3 89q-31 29-45 45m98 97c0 12-5 31-13 36l-9-63q21 6 22 27m-41-169q1-18 9-37 10-22 16-22h3c5 0 10 2 9 15q-1 17-13 35-10 15-22 25-3-7-2-16m-6 76 3 27q-14 6-23 18-12 13-13 30-1 18 8 31 4 7 12 13c7 5 16 5 18 2q0-4-8-15-4-5-4-13 1-18 16-25l9 70-16 1q-22-2-39-19a48 48 0 0 1-16-38q3-42 53-82";
        
        $this->SetDrawColor(255, 255, 255); // Use white stroke to erode the black fill
        $this->SetLineWidth(0.25); // Control G-clef thinness (higher = thinner, 0.25mm gives a beautiful slim look)
        $this->DrawSVGPath($path, $x - 4.5, $y - 4.0, 0.0415, 0.0415, 'B');
        $this->SetDrawColor(0, 0, 0); // Restore default draw color for subsequent elements
        $this->SetLineWidth(0.2); // Restore default line width
    }

    /**
     * Draw the percussion clef (neutral clef)
     *
     * @param float $x Placement X coordinate
     * @param float $y Placement Y coordinate
     * @return void
     */
    public function DrawPercussionClef($x, $y)
    {
        $this->SetLineWidth(0.8);
        $this->Line($x + 1, $y + 2, $x + 1, $y + 6);
        $this->Line($x + 2.5, $y + 2, $x + 2.5, $y + 6);
        $this->SetLineWidth(0.2);
    }

    /**
     * Draw the bass clef (F-clef)
     *
     * @param float $x Placement X coordinate
     * @param float $y Placement Y coordinate
     * @return void
     */
    public function DrawBassClef($x, $y)
    {
        // User custom vector F-Clef hook path (excluding dots)
        $path = "M205 23c-67 0-107 39-118 77-11 39 3 77 17 98h1a64 64 0 0 0 52 26 64 64 0 0 0 64-64 64 64 0 0 0-64-64 64 64 0 0 0-50 24l3-18c10-33 34-61 95-61 60 0 94 64 92 153-1 80-12 128-60 171q-72 65-180 107c-13 5-1 19 7 16 73-28 145-53 196-98 51-46 96-87 96-198 1-97-44-169-151-169";
        
        $this->SetDrawColor(255, 255, 255); // Use white stroke to erode the black fill
        $this->SetLineWidth(0.25);
        $this->DrawSVGPath($path, $x - 0.5, $y - 0.3, 0.018, 0.018, 'B');
        $this->SetDrawColor(0, 0, 0); // Restore default draw color for subsequent elements
        $this->SetLineWidth(0.2); // Restore default line width

        // Draw the two dots as mathematically perfect filled circles
        $pdfX = $x + 6.25;
        $this->Circle($pdfX, $y + 0.85, 0.45, 'F');
        $this->Circle($pdfX, $y + 3.15, 0.45, 'F');
    }

    /**
     * Draw the alto clef (C-clef)
     *
     * @param float $x Placement X coordinate
     * @param float $y Placement Y coordinate
     * @return void
     */
    public function DrawAltoClef($x, $y)
    {
        // Authentic Wikimedia-based vector C-Clef path shifted to 0,0 origin
        $path = "M0 2362L0 56L0 5L266 5L266 2311L266 2362L0 2362zM400 2362L400 56L400 5L485 5L485 1160C527 1138 570 1093 612 1022C655 952 691 878 719 799C747 720 762 662 764 624C777 708 798 775 826 826C855 876 886 912 922 934C958 955 993 966 1029 966C1118 955 1174 915 1198 834C1222 764 1234 665 1234 548C1234 495 1233 447 1230 405C1227 363 1221 320 1210 277C1200 234 1183 195 1159 161C1134 127 1102 103 1062 91C1026 81 990 76 955 76C923 76 896 82 875 93C853 104 841 121 839 141C844 159 856 180 877 205C898 229 912 248 920 260C928 272 932 289 932 312C932 353 918 387 890 415C862 443 825 458 778 458C732 458 694 441 666 408C638 374 623 334 621 289C625 228 647 175 686 131C726 88 775 55 834 33C893 11 952 0 1012 0C1080 0 1145 12 1207 36C1270 60 1326 96 1374 142C1423 189 1462 246 1490 314C1518 381 1532 458 1532 543C1532 661 1510 759 1467 836C1423 914 1367 972 1299 1008C1230 1044 1157 1064 1080 1066C1000 1061 933 1043 880 1012L774 1184L880 1355C953 1325 1025 1310 1095 1310C1184 1310 1261 1336 1328 1386C1394 1437 1445 1502 1480 1583C1514 1664 1532 1747 1532 1833C1532 1927 1511 2015 1469 2096C1427 2177 1368 2241 1292 2290C1215 2338 1128 2362 1029 2362C914 2357 818 2330 741 2280C664 2231 626 2157 626 2060C630 2013 647 1977 677 1950C707 1922 739 1908 774 1905C816 1905 854 1920 887 1950C920 1979 937 2016 937 2060C937 2077 933 2093 925 2108C917 2122 906 2139 890 2159C874 2178 863 2192 857 2201C851 2211 846 2222 844 2236C844 2254 855 2269 878 2281C900 2293 929 2300 964 2303C1074 2298 1147 2251 1184 2164C1220 2075 1238 1966 1238 1833C1238 1718 1225 1617 1199 1531C1173 1444 1117 1401 1029 1401C951 1401 891 1434 849 1502C807 1569 780 1648 768 1739C755 1662 735 1588 706 1517C677 1445 644 1382 605 1328C568 1275 527 1232 485 1202L485 2362L400 2362z";
        
        $this->SetDrawColor(255, 255, 255); // Use white stroke to erode the black fill
        $this->SetLineWidth(0.25);
        // Scale to fit the staff height of 8.0mm: 8.0 / 2362 = 0.0033868
        $this->DrawSVGPath($path, $x + 1.0, $y, 0.0033868, 0.0033868, 'B');
        $this->SetDrawColor(0, 0, 0); // Restore default draw color for subsequent elements
        $this->SetLineWidth(0.2); // Restore default line width
    }

    /**
     * Draw the sharp accidental symbol
     *
     * @param float $x Placement X coordinate
     * @param float $y Placement Y coordinate
     * @return void
     */
    public function DrawSharp($x, $y)
    {
        // Custom ultra-thin elegant sharp path with shorter stems
        // Dipisah menjadi 4 path agar tidak ada lubang (karena PDF menggunakan Even-Odd fill rule 'f*')
        $path1 = "M1.2 0 L1.6 0 L1.6 10 L1.2 10 Z";
        $path2 = "M3.0 0 L3.4 0 L3.4 10 L3.0 10 Z";
        $path3 = "M0 3.5 L5 2.5 L5 3.0 L0 4.0 Z";
        $path4 = "M0 6.5 L5 5.5 L5 6.0 L0 7.0 Z";
        
        $this->DrawSVGPath($path1, $x - 1.1, $y - 2.1, 0.45, 0.45, true, 'none');
        $this->DrawSVGPath($path2, $x - 1.1, $y - 2.1, 0.45, 0.45, true, 'none');
        $this->DrawSVGPath($path3, $x - 1.1, $y - 2.1, 0.45, 0.45, true, 'none');
        $this->DrawSVGPath($path4, $x - 1.1, $y - 2.1, 0.45, 0.45, true, 'none');
    }

    /**
     * Draw the flat accidental symbol
     *
     * @param float $x Placement X coordinate
     * @param float $y Placement Y coordinate
     * @return void
     */
    public function DrawFlat($x, $y)
    {
        // Closed loop flat path with defined thin stem thickness (0.4 units) so it fills properly
        $path = "M 1.2 0 L 1.6 0 L 1.6 9 C 4.8 9 4.8 18 1.6 18 L 1.2 18 Z";
        $this->DrawSVGPath($path, $x - 0.63, $y - 6.07, 0.45, 0.45, true, 'none');
    }

}
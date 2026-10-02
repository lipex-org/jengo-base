<?php

declare(strict_types=1);

namespace Jengo\Base\Support;

/**
 * Pure-PHP ISO/IEC 18004 compliant QR Code Generator.
 *
 * Generates standards-compliant vector SVG, raster PNG, and base64 Data URIs
 * with full support for finder patterns, separators, timing tracks,
 * alignment patterns, version information, multi-block Reed-Solomon
 * error correction, interleaving, and optimal mask penalty evaluation.
 */
class QrCode
{
    /**
     * Error Correction Level L Block Table (Versions 1 to 20).
     * [ecCodewordsPerBlock, [numBlocksGroup1, dataCodewordsGroup1], [numBlocksGroup2, dataCodewordsGroup2]]
     */
    private static array $blockTable = [
        1  => [7,  [1, 19],  [0, 0]],
        2  => [10, [1, 34],  [0, 0]],
        3  => [15, [1, 55],  [0, 0]],
        4  => [20, [1, 80],  [0, 0]],
        5  => [26, [1, 108], [0, 0]],
        6  => [18, [2, 68],  [0, 0]],
        7  => [20, [2, 78],  [0, 0]],
        8  => [24, [2, 97],  [0, 0]],
        9  => [30, [2, 116], [0, 0]],
        10 => [18, [2, 68],  [2, 69]],
        11 => [20, [4, 81],  [0, 0]],
        12 => [24, [2, 92],  [2, 93]],
        13 => [26, [4, 107], [0, 0]],
        14 => [30, [3, 115], [1, 116]],
        15 => [22, [5, 87],  [1, 88]],
        16 => [24, [5, 98],  [1, 99]],
        17 => [28, [1, 107], [5, 108]],
        18 => [30, [5, 120], [1, 121]],
        19 => [28, [3, 113], [4, 114]],
        20 => [28, [3, 107], [5, 108]],
    ];

    /**
     * Alignment Pattern center coordinate list for versions 1 to 20.
     */
    private static array $alignCoords = [
        1  => [],
        2  => [6, 18],
        3  => [6, 22],
        4  => [6, 26],
        5  => [6, 30],
        6  => [6, 34],
        7  => [6, 22, 38],
        8  => [6, 24, 42],
        9  => [6, 26, 46],
        10 => [6, 28, 50],
        11 => [6, 30, 54],
        12 => [6, 32, 58],
        13 => [6, 34, 62],
        14 => [6, 26, 46, 66],
        15 => [6, 26, 48, 70],
        16 => [6, 26, 50, 74],
        17 => [6, 30, 54, 78],
        18 => [6, 30, 56, 82],
        19 => [6, 30, 58, 86],
        20 => [6, 34, 62, 90],
    ];

    /**
     * Generate an <img> tag with Base64 PNG data for universal compatibility.
     */
    public static function img(
        string $text,
        int $size = 120,
        string $color = '#000000',
        string $bgColor = '#ffffff',
        int $margin = 4,
        string $extraStyle = '',
        string $alt = 'QR Code'
    ): string {
        $uri = self::pngDataUri($text, $size, $color, $bgColor, $margin);
        return sprintf(
            '<img src="%s" width="%d" height="%d" alt="%s" style="display:inline-block; vertical-align:middle; %s" />',
            $uri,
            $size,
            $size,
            htmlspecialchars($alt),
            $extraStyle
        );
    }

    /**
     * Render QR code as either <img>, SVG, or Data URI.
     */
    public static function render(
        string $text,
        int $size = 120,
        string $color = '#000000',
        string $bgColor = '#ffffff',
        int $margin = 4,
        string $format = 'img'
    ): string {
        return match (strtolower($format)) {
            'svg'      => self::svg($text, $size, $color, $bgColor, $margin),
            'data-uri' => self::pngDataUri($text, $size, $color, $bgColor, $margin),
            'svg-uri'  => self::svgDataUri($text, $size, $color, $bgColor, $margin),
            'png'      => self::png($text, $size, $color, $bgColor, $margin),
            default    => self::img($text, $size, $color, $bgColor, $margin),
        };
    }

    /**
     * Generate a binary PNG image for the QR code (pure PHP, zero dependencies).
     */
    public static function png(
        string $text,
        int $size = 120,
        string $color = '#000000',
        string $bgColor = '#ffffff',
        int $margin = 4
    ): string {
        $matrix = self::generateMatrix($text);
        $modules = count($matrix);
        $totalModules = $modules + ($margin * 2);

        // Calculate scale (pixels per module), at least 6px per module for high-DPI raster crispness
        $scale = max(6, (int) round(($size * 2) / $totalModules));
        $imgSize = $totalModules * $scale;

        $fg = self::hexToRgb($color);
        $bg = ($bgColor === 'transparent' || $bgColor === '') ? [255, 255, 255] : self::hexToRgb($bgColor);

        $raw = '';
        for ($r = 0; $r < $imgSize; $r++) {
            $raw .= "\x00"; // PNG filter byte 0 (None)
            $mr = (int) floor($r / $scale) - $margin;
            for ($c = 0; $c < $imgSize; $c++) {
                $mc = (int) floor($c / $scale) - $margin;
                $isDark = ($mr >= 0 && $mr < $modules && $mc >= 0 && $mc < $modules && $matrix[$mr][$mc] === 1);
                $pixelColor = $isDark ? $fg : $bg;
                $raw .= chr($pixelColor[0]) . chr($pixelColor[1]) . chr($pixelColor[2]);
            }
        }

        $compressed = gzcompress($raw, 6);
        $ihdrData = pack('NNCCCCC', $imgSize, $imgSize, 8, 2, 0, 0, 0);
        $ihdr = 'IHDR' . $ihdrData . pack('N', crc32('IHDR' . $ihdrData));
        $idat = 'IDAT' . $compressed . pack('N', crc32('IDAT' . $compressed));
        $iend = 'IEND' . pack('N', crc32('IEND'));

        return "\x89PNG\r\n\x1a\n"
            . pack('N', strlen($ihdrData)) . $ihdr
            . pack('N', strlen($compressed)) . $idat
            . pack('N', 0) . $iend;
    }

    /**
     * Generate a Base64 PNG Data URI for universal web and document rendering.
     */
    public static function pngDataUri(
        string $text,
        int $size = 120,
        string $color = '#000000',
        string $bgColor = '#ffffff',
        int $margin = 4
    ): string {
        $png = self::png($text, $size, $color, $bgColor, $margin);
        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Generate an SVG string representing the QR code.
     */
    public static function svg(
        string $text,
        int $size = 120,
        string $color = '#000000',
        string $bgColor = '#ffffff',
        int $margin = 4
    ): string {
        $matrix = self::generateMatrix($text);
        $modules = count($matrix);
        $totalSize = $modules + ($margin * 2);

        $rects = '';
        if ($bgColor !== 'transparent' && $bgColor !== '') {
            $rects .= sprintf('<rect width="%d" height="%d" fill="%s" />', $totalSize, $totalSize, htmlspecialchars($bgColor));
        }

        for ($r = 0; $r < $modules; $r++) {
            for ($c = 0; $c < $modules; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $rects .= sprintf(
                        '<rect x="%d" y="%d" width="1" height="1" fill="%s" />',
                        $c + $margin,
                        $r + $margin,
                        htmlspecialchars($color)
                    );
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges" style="display:inline-block; vertical-align:middle;">%s</svg>',
            $size,
            $size,
            $totalSize,
            $totalSize,
            $rects
        );
    }

    /**
     * Generate a base64 Data URI for the QR code SVG.
     */
    public static function svgDataUri(
        string $text,
        int $size = 120,
        string $color = '#000000',
        string $bgColor = '#ffffff',
        int $margin = 4
    ): string {
        $svg = self::svg($text, $size, $color, $bgColor, $margin);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Generate a base64 Data URI for the QR code (defaults to PNG).
     */
    public static function dataUri(
        string $text,
        int $size = 120,
        string $color = '#000000',
        string $bgColor = '#ffffff',
        int $margin = 4
    ): string {
        return self::pngDataUri($text, $size, $color, $bgColor, $margin);
    }

    /**
     * Generate the 2D boolean/integer matrix (0=white, 1=black) for the text.
     */
    public static function generateMatrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $len = count($bytes);

        // Determine the minimum QR version needed
        $version = 1;
        $maxVersion = max(array_keys(self::$blockTable));
        for ($v = 1; $v <= $maxVersion; $v++) {
            $info = self::$blockTable[$v];
            $totalData = ($info[1][0] * $info[1][1]) + ($info[2][0] * $info[2][1]);
            $headerBits = ($v < 10) ? 12 : 20; // 4-bit mode + 8/16-bit char count
            if (($len * 8 + $headerBits) <= ($totalData * 8)) {
                $version = $v;
                break;
            }
            $version = $v;
        }

        $size = 17 + (4 * $version);
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // 1. Finder Patterns & White Separators
        self::placeFinder($matrix, $reserved, 0, 0, $size);
        self::placeFinder($matrix, $reserved, 0, $size - 7, $size);
        self::placeFinder($matrix, $reserved, $size - 7, 0, $size);

        // 2. Timing Patterns (Row 6 & Column 6)
        for ($i = 8; $i < $size - 8; $i++) {
            $val = ($i % 2 === 0) ? 1 : 0;
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = $val;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = $val;
                $reserved[$i][6] = true;
            }
        }

        // 3. Alignment Patterns (Version >= 2)
        if ($version >= 2) {
            $coords = self::$alignCoords[$version] ?? [];
            foreach ($coords as $r) {
                foreach ($coords as $c) {
                    if (self::isNearFinder($r, $c, $size)) {
                        continue;
                    }
                    self::placeAlignment($matrix, $reserved, $r, $c);
                }
            }
        }

        // 4. Dark Module at (4*version + 9, 8)
        $darkRow = 4 * $version + 9;
        $matrix[$darkRow][8] = 1;
        $reserved[$darkRow][8] = true;
        $matrix[8][4 * $version + 9] = 1;

        // 5. Reserve Format Information Area
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        // 6. Reserve Version Information Area (Version >= 7)
        if ($version >= 7) {
            for ($r = 0; $r < 6; $r++) {
                for ($c = $size - 11; $c < $size - 8; $c++) {
                    $reserved[$r][$c] = true;
                }
            }
            for ($r = $size - 11; $r < $size - 8; $r++) {
                for ($c = 0; $c < 6; $c++) {
                    $reserved[$r][$c] = true;
                }
            }
        }

        // 7. Encode & Interleave Codewords
        $codewords = self::encodeData($bytes, $version);

        // 8. Find Optimal Mask Pattern (0 to 7) via Penalty Evaluation
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $testMatrix = $matrix;
            self::placeDataAndMask($testMatrix, $reserved, $codewords, $size, $mask);
            self::placeFormatInfo($testMatrix, $size, 0b01000 | $mask); // ECC Level L (01)
            if ($version >= 7) {
                self::placeVersionInfo($testMatrix, $version, $size);
            }
            $penalty = self::calculatePenalty($testMatrix, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $testMatrix;
            }
        }

        return $bestMatrix ?? $matrix;
    }

    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            return [
                (int) hexdec($hex[0] . $hex[0]),
                (int) hexdec($hex[1] . $hex[1]),
                (int) hexdec($hex[2] . $hex[2]),
            ];
        }
        if (strlen($hex) >= 6) {
            return [
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
            ];
        }
        return [0, 0, 0];
    }

    protected static function isNearFinder(int $row, int $col, int $size): bool
    {
        if ($row <= 8 && $col <= 8) {
            return true;
        }
        if ($row <= 8 && $col >= $size - 9) {
            return true;
        }
        if ($row >= $size - 9 && $col <= 8) {
            return true;
        }
        return false;
    }

    protected static function placeFinder(array &$matrix, array &$reserved, int $row, int $col, int $size): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $mr = $row + $r;
                $mc = $col + $c;
                if ($mr >= 0 && $mr < $size && $mc >= 0 && $mc < $size) {
                    if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                        $isBlack = ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                        $matrix[$mr][$mc] = $isBlack ? 1 : 0;
                    } else {
                        $matrix[$mr][$mc] = 0; // White separator
                    }
                    $reserved[$mr][$mc] = true;
                }
            }
        }
    }

    protected static function placeAlignment(array &$matrix, array &$reserved, int $row, int $col): void
    {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $isEdge = (abs($r) === 2 || abs($c) === 2);
                $isCenter = ($r === 0 && $c === 0);
                $matrix[$row + $r][$col + $c] = ($isEdge || $isCenter) ? 1 : 0;
                $reserved[$row + $r][$col + $c] = true;
            }
        }
    }

    protected static function placeFormatInfo(array &$matrix, int $size, int $data5Bits): void
    {
        // 10-bit BCH code calculation (generator polynomial: 0x537)
        $bch = $data5Bits << 10;
        $poly = 0x537;
        for ($i = 4; $i >= 0; $i--) {
            if (($bch >> ($i + 10)) & 1) {
                $bch ^= ($poly << $i);
            }
        }
        $formatWord = (($data5Bits << 10) | $bch) ^ 0x5412;

        $bits = [];
        for ($i = 14; $i >= 0; $i--) {
            $bits[] = ($formatWord >> $i) & 1;
        }

        // Top-Left Row 8 & Col 8
        $matrix[8][0] = $bits[0];
        $matrix[8][1] = $bits[1];
        $matrix[8][2] = $bits[2];
        $matrix[8][3] = $bits[3];
        $matrix[8][4] = $bits[4];
        $matrix[8][5] = $bits[5];
        $matrix[8][7] = $bits[6];
        $matrix[8][8] = $bits[7];

        $matrix[7][8] = $bits[8];
        $matrix[5][8] = $bits[9];
        $matrix[4][8] = $bits[10];
        $matrix[3][8] = $bits[11];
        $matrix[2][8] = $bits[12];
        $matrix[1][8] = $bits[13];
        $matrix[0][8] = $bits[14];

        // Bottom-Left
        $matrix[$size - 1][8] = $bits[0];
        $matrix[$size - 2][8] = $bits[1];
        $matrix[$size - 3][8] = $bits[2];
        $matrix[$size - 4][8] = $bits[3];
        $matrix[$size - 5][8] = $bits[4];
        $matrix[$size - 6][8] = $bits[5];
        $matrix[$size - 7][8] = $bits[6];

        // Top-Right
        $matrix[8][$size - 8] = $bits[7];
        $matrix[8][$size - 7] = $bits[8];
        $matrix[8][$size - 6] = $bits[9];
        $matrix[8][$size - 5] = $bits[10];
        $matrix[8][$size - 4] = $bits[11];
        $matrix[8][$size - 3] = $bits[12];
        $matrix[8][$size - 2] = $bits[13];
        $matrix[8][$size - 1] = $bits[14];
    }

    protected static function placeVersionInfo(array &$matrix, int $version, int $size): void
    {
        $v = $version << 12;
        $poly = 0x1F25;
        for ($i = 5; $i >= 0; $i--) {
            if (($v >> ($i + 12)) & 1) {
                $v ^= ($poly << $i);
            }
        }
        $word = ($version << 12) | $v;

        $bits = [];
        for ($i = 0; $i < 18; $i++) {
            $bits[$i] = ($word >> $i) & 1;
        }

        // Above Bottom-Left: 3 rows x 6 cols
        for ($r = 0; $r < 6; $r++) {
            for ($c = 0; $c < 3; $c++) {
                $matrix[$size - 11 + $c][$r] = $bits[$r * 3 + $c];
            }
        }

        // Left of Top-Right: 6 rows x 3 cols
        for ($r = 0; $r < 6; $r++) {
            for ($c = 0; $c < 3; $c++) {
                $matrix[$r][$size - 11 + $c] = $bits[$r * 3 + $c];
            }
        }
    }

    protected static function encodeData(array $bytes, int $version): array
    {
        $info = self::$blockTable[$version];
        $eccPerBlock = $info[0];
        $g1Count = $info[1][0];
        $g1Data = $info[1][1];
        $g2Count = $info[2][0];
        $g2Data = $info[2][1];
        $totalData = ($g1Count * $g1Data) + ($g2Count * $g2Data);

        // Byte mode (0100)
        $bits = '0100';
        $charBits = ($version < 10) ? 8 : 16;
        $bits .= str_pad(decbin(count($bytes)), $charBits, '0', STR_PAD_LEFT);

        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }

        $maxBits = $totalData * 8;
        if (strlen($bits) < $maxBits) {
            $bits .= substr('0000', 0, min(4, $maxBits - strlen($bits)));
        }
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }

        $dataCodewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataCodewords[] = bindec(substr($bits, $i, 8));
        }

        // Pad codewords
        $pad = [0xEC, 0x11];
        $p = 0;
        while (count($dataCodewords) < $totalData) {
            $dataCodewords[] = $pad[$p % 2];
            $p++;
        }

        // Partition data into blocks
        $blocks = [];
        $offset = 0;
        for ($b = 0; $b < $g1Count; $b++) {
            $blocks[] = array_slice($dataCodewords, $offset, $g1Data);
            $offset += $g1Data;
        }
        for ($b = 0; $b < $g2Count; $b++) {
            $blocks[] = array_slice($dataCodewords, $offset, $g2Data);
            $offset += $g2Data;
        }

        // Calculate Reed-Solomon error correction for each block
        $eccBlocks = [];
        foreach ($blocks as $block) {
            $eccBlocks[] = self::calculateReedSolomon($block, $eccPerBlock);
        }

        // Interleave Data Codewords
        $interleaved = [];
        $maxDataLen = max($g1Data, $g2Data);
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($blocks as $block) {
                if ($i < count($block)) {
                    $interleaved[] = $block[$i];
                }
            }
        }

        // Interleave ECC Codewords
        for ($i = 0; $i < $eccPerBlock; $i++) {
            foreach ($eccBlocks as $eccBlock) {
                $interleaved[] = $eccBlock[$i];
            }
        }

        return $interleaved;
    }

    protected static function calculateReedSolomon(array $data, int $eccCount): array
    {
        // GF(256) log and exp tables (primitive polynomial 0x11D = 285)
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $exp[$i + 255] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }

        // Generator polynomial g(x) = (x - a^0)(x - a^1)...(x - a^(eccCount-1))
        $gen = [1];
        for ($i = 0; $i < $eccCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            $factor = $exp[$i];
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];
                $prod = ($gen[$j] === 0 || $factor === 0) ? 0 : $exp[$log[$gen[$j]] + $log[$factor]];
                $next[$j + 1] ^= $prod;
            }
            $gen = $next;
        }

        // Polynomial division
        $res = array_fill(0, count($data) + $eccCount, 0);
        for ($i = 0; $i < count($data); $i++) {
            $res[$i] = $data[$i];
        }

        for ($i = 0; $i < count($data); $i++) {
            $lead = $res[$i];
            if ($lead !== 0) {
                $leadLog = $log[$lead];
                for ($j = 0; $j < count($gen); $j++) {
                    if ($gen[$j] !== 0) {
                        $res[$i + $j] ^= $exp[$leadLog + $log[$gen[$j]]];
                    }
                }
            }
        }

        return array_slice($res, count($data), $eccCount);
    }

    protected static function placeDataAndMask(array &$matrix, array &$reserved, array $codewords, int $size, int $mask): void
    {
        $bits = '';
        foreach ($codewords as $cw) {
            $bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        $bitIdx = 0;
        $totalBits = strlen($bits);
        $col = $size - 1;
        $upward = true;

        while ($col > 0) {
            if ($col === 6) {
                $col--; // Skip vertical timing column
            }

            for ($r = 0; $r < $size; $r++) {
                $currRow = $upward ? ($size - 1 - $r) : $r;
                for ($c = 0; $c < 2; $c++) {
                    $currCol = $col - $c;
                    if (!$reserved[$currRow][$currCol]) {
                        $bit = ($bitIdx < $totalBits) ? (int) $bits[$bitIdx++] : 0;
                        $maskBit = self::getMaskBit($mask, $currRow, $currCol);
                        $matrix[$currRow][$currCol] = $maskBit ? ($bit ^ 1) : $bit;
                    }
                }
            }

            $col -= 2;
            $upward = !$upward;
        }
    }

    protected static function getMaskBit(int $mask, int $row, int $col): bool
    {
        return match ($mask) {
            0 => ($row + $col) % 2 === 0,
            1 => $row % 2 === 0,
            2 => $col % 3 === 0,
            3 => ($row + $col) % 3 === 0,
            4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
            5 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
            6 => ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0,
            7 => ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0,
            default => false,
        };
    }

    protected static function calculatePenalty(array $matrix, int $size): int
    {
        $penalty = 0;

        // Condition 1: 5 or more same color consecutive in row/col
        for ($r = 0; $r < $size; $r++) {
            $rowColor = $matrix[$r][0];
            $rowLen = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($matrix[$r][$c] === $rowColor) {
                    $rowLen++;
                } else {
                    if ($rowLen >= 5) {
                        $penalty += 3 + ($rowLen - 5);
                    }
                    $rowColor = $matrix[$r][0] ?? null;
                    $rowColor = $matrix[$r][$c];
                    $rowLen = 1;
                }
            }
            if ($rowLen >= 5) {
                $penalty += 3 + ($rowLen - 5);
            }
        }

        for ($c = 0; $c < $size; $c++) {
            $colColor = $matrix[0][$c];
            $colLen = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($matrix[$r][$c] === $colColor) {
                    $colLen++;
                } else {
                    if ($colLen >= 5) {
                        $penalty += 3 + ($colLen - 5);
                    }
                    $colColor = $matrix[$r][$c];
                    $colLen = 1;
                }
            }
            if ($colLen >= 5) {
                $penalty += 3 + ($colLen - 5);
            }
        }

        // Condition 2: 2x2 blocks of same color
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $val = $matrix[$r][$c];
                if ($val === $matrix[$r + 1][$c] && $val === $matrix[$r][$c + 1] && $val === $matrix[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Condition 3: Finder-like 1:1:3:1:1 patterns with quiet boundary
        for ($r = 0; $r < $size; $r++) {
            $rowStr = implode('', $matrix[$r]);
            $penalty += substr_count($rowStr, '10111010000') * 40;
            $penalty += substr_count($rowStr, '00001011101') * 40;
        }
        for ($c = 0; $c < $size; $c++) {
            $colStr = '';
            for ($r = 0; $r < $size; $r++) {
                $colStr .= $matrix[$r][$c];
            }
            $penalty += substr_count($colStr, '10111010000') * 40;
            $penalty += substr_count($colStr, '00001011101') * 40;
        }

        // Condition 4: Dark module ratio
        $darkCount = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $darkCount++;
                }
            }
        }
        $percent = ($darkCount * 100) / ($size * $size);
        $prev5 = (int) (floor($percent / 5) * 5);
        $next5 = (int) (ceil($percent / 5) * 5);
        $dev1 = abs($prev5 - 50) / 5;
        $dev2 = abs($next5 - 50) / 5;
        $penalty += min($dev1, $dev2) * 10;

        return (int) $penalty;
    }
}

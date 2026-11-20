<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Encoder;

use Wnikk\QrCodeCute\Exception\DataTooLongException;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;

/**
 * Encodes a payload into a matrix of modules.
 *
 * The encoder writes the payload in byte mode. It uses the smallest version
 * (1 to 40) that holds the payload at the given error correction level.
 * The drawing of the matrix is in the Render namespace.
 */
final class Encoder
{
    /**
     * Error correction levels, from the lowest to the highest.
     *
     * @var string[]
     */
    public const LEVELS = ['L', 'M', 'Q', 'H'];

    /**
     * Level indicators for the format information.
     *
     * @var array<string, int>
     */
    private const LEVEL_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    /**
     * Mode indicator for byte mode.
     *
     * @var int
     */
    private const MODE_BYTE = 4;

    /**
     * The first pad codeword.
     *
     * @var int
     */
    private const PAD0 = 0xEC;

    /**
     * The second pad codeword.
     *
     * @var int
     */
    private const PAD1 = 0x11;

    /**
     * The generator polynomial for the format information.
     *
     * @var int
     */
    private const G15 = 0b10100110111;

    /**
     * The generator polynomial for the version information.
     *
     * @var int
     */
    private const G18 = 0b1111100100101;

    /**
     * The mask for the format information.
     *
     * @var int
     */
    private const G15_MASK = 0b101010000010010;

    /**
     * Reed-Solomon block layout for each level, versions 1 to 40.
     * Each group of three values is: block count, total codewords, data codewords.
     *
     * @var array<string, array<int, int[]>>
     */
    private const RS_BLOCKS = [
        'L' => [
            [1, 26, 19], [1, 44, 34], [1, 70, 55], [1, 100, 80], [1, 134, 108],
            [2, 86, 68], [2, 98, 78], [2, 121, 97], [2, 146, 116], [2, 86, 68, 2, 87, 69],
            [4, 101, 81], [2, 116, 92, 2, 117, 93], [4, 133, 107], [3, 145, 115, 1, 146, 116], [5, 109, 87, 1, 110, 88],
            [5, 122, 98, 1, 123, 99], [1, 135, 107, 5, 136, 108], [5, 150, 120, 1, 151, 121], [3, 141, 113, 4, 142, 114], [3, 135, 107, 5, 136, 108],
            [4, 144, 116, 4, 145, 117], [2, 139, 111, 7, 140, 112], [4, 151, 121, 5, 152, 122], [6, 147, 117, 4, 148, 118], [8, 132, 106, 4, 133, 107],
            [10, 142, 114, 2, 143, 115], [8, 152, 122, 4, 153, 123], [3, 147, 117, 10, 148, 118], [7, 146, 116, 7, 147, 117], [5, 145, 115, 10, 146, 116],
            [13, 145, 115, 3, 146, 116], [17, 145, 115], [17, 145, 115, 1, 146, 116], [13, 145, 115, 6, 146, 116], [12, 151, 121, 7, 152, 122],
            [6, 151, 121, 14, 152, 122], [17, 152, 122, 4, 153, 123], [4, 152, 122, 18, 153, 123], [20, 147, 117, 4, 148, 118], [19, 148, 118, 6, 149, 119],
        ],
        'M' => [
            [1, 26, 16], [1, 44, 28], [1, 70, 44], [2, 50, 32], [2, 67, 43],
            [4, 43, 27], [4, 49, 31], [2, 60, 38, 2, 61, 39], [3, 58, 36, 2, 59, 37], [4, 69, 43, 1, 70, 44],
            [1, 80, 50, 4, 81, 51], [6, 58, 36, 2, 59, 37], [8, 59, 37, 1, 60, 38], [4, 64, 40, 5, 65, 41], [5, 65, 41, 5, 66, 42],
            [7, 73, 45, 3, 74, 46], [10, 74, 46, 1, 75, 47], [9, 69, 43, 4, 70, 44], [3, 70, 44, 11, 71, 45], [3, 67, 41, 13, 68, 42],
            [17, 68, 42], [17, 74, 46], [4, 75, 47, 14, 76, 48], [6, 73, 45, 14, 74, 46], [8, 75, 47, 13, 76, 48],
            [19, 74, 46, 4, 75, 47], [22, 73, 45, 3, 74, 46], [3, 73, 45, 23, 74, 46], [21, 73, 45, 7, 74, 46], [19, 75, 47, 10, 76, 48],
            [2, 74, 46, 29, 75, 47], [10, 74, 46, 23, 75, 47], [14, 74, 46, 21, 75, 47], [14, 74, 46, 23, 75, 47], [12, 75, 47, 26, 76, 48],
            [6, 75, 47, 34, 76, 48], [29, 74, 46, 14, 75, 47], [13, 74, 46, 32, 75, 47], [40, 75, 47, 7, 76, 48], [18, 75, 47, 31, 76, 48],
        ],
        'Q' => [
            [1, 26, 13], [1, 44, 22], [2, 35, 17], [2, 50, 24], [2, 33, 15, 2, 34, 16],
            [4, 43, 19], [2, 32, 14, 4, 33, 15], [4, 40, 18, 2, 41, 19], [4, 36, 16, 4, 37, 17], [6, 43, 19, 2, 44, 20],
            [4, 50, 22, 4, 51, 23], [4, 46, 20, 6, 47, 21], [8, 44, 20, 4, 45, 21], [11, 36, 16, 5, 37, 17], [5, 54, 24, 7, 55, 25],
            [15, 43, 19, 2, 44, 20], [1, 50, 22, 15, 51, 23], [17, 50, 22, 1, 51, 23], [17, 47, 21, 4, 48, 22], [15, 54, 24, 5, 55, 25],
            [17, 50, 22, 6, 51, 23], [7, 54, 24, 16, 55, 25], [11, 54, 24, 14, 55, 25], [11, 54, 24, 16, 55, 25], [7, 54, 24, 22, 55, 25],
            [28, 50, 22, 6, 51, 23], [8, 53, 23, 26, 54, 24], [4, 54, 24, 31, 55, 25], [1, 53, 23, 37, 54, 24], [15, 54, 24, 25, 55, 25],
            [42, 54, 24, 1, 55, 25], [10, 54, 24, 35, 55, 25], [29, 54, 24, 19, 55, 25], [44, 54, 24, 7, 55, 25], [39, 54, 24, 14, 55, 25],
            [46, 54, 24, 10, 55, 25], [49, 54, 24, 10, 55, 25], [48, 54, 24, 14, 55, 25], [43, 54, 24, 22, 55, 25], [34, 54, 24, 34, 55, 25],
        ],
        'H' => [
            [1, 26, 9], [1, 44, 16], [2, 35, 13], [4, 25, 9], [2, 33, 11, 2, 34, 12],
            [4, 43, 15], [4, 39, 13, 1, 40, 14], [4, 40, 14, 2, 41, 15], [4, 36, 12, 4, 37, 13], [6, 43, 15, 2, 44, 16],
            [3, 36, 12, 8, 37, 13], [7, 42, 14, 4, 43, 15], [12, 33, 11, 4, 34, 12], [11, 36, 12, 5, 37, 13], [11, 36, 12],
            [3, 45, 15, 13, 46, 16], [2, 42, 14, 17, 43, 15], [2, 42, 14, 19, 43, 15], [9, 39, 13, 16, 40, 14], [15, 43, 15, 10, 44, 16],
            [19, 46, 16, 6, 47, 17], [34, 37, 13], [16, 45, 15, 14, 46, 16], [30, 46, 16, 2, 47, 17], [22, 45, 15, 13, 46, 16],
            [33, 46, 16, 4, 47, 17], [12, 45, 15, 28, 46, 16], [11, 45, 15, 31, 46, 16], [19, 45, 15, 26, 46, 16], [23, 45, 15, 25, 46, 16],
            [23, 45, 15, 28, 46, 16], [19, 45, 15, 35, 46, 16], [11, 45, 15, 46, 46, 16], [59, 46, 16, 1, 47, 17], [22, 45, 15, 41, 46, 16],
            [2, 45, 15, 64, 46, 16], [24, 45, 15, 46, 46, 16], [42, 45, 15, 32, 46, 16], [10, 45, 15, 67, 46, 16], [20, 45, 15, 61, 46, 16],
        ],
    ];

    /**
     * Centres of the alignment patterns for each version.
     *
     * @var array<int, int[]>
     */
    private const ALIGNMENT = [
        [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50],
        [6, 30, 54], [6, 32, 58], [6, 34, 62], [6, 26, 46, 66], [6, 26, 48, 70], [6, 26, 50, 74], [6, 30, 54, 78], [6, 30, 56, 82], [6, 30, 58, 86], [6, 34, 62, 90],
        [6, 28, 50, 72, 94], [6, 26, 50, 74, 98], [6, 30, 54, 78, 102], [6, 28, 54, 80, 106], [6, 32, 58, 84, 110], [6, 30, 58, 86, 114], [6, 34, 62, 90, 118], [6, 26, 50, 74, 98, 122], [6, 30, 54, 78, 102, 126], [6, 26, 52, 78, 104, 130],
        [6, 30, 56, 82, 108, 134], [6, 34, 60, 86, 112, 138], [6, 30, 58, 86, 114, 142], [6, 34, 62, 90, 118, 146], [6, 30, 54, 78, 102, 126, 150], [6, 24, 50, 76, 102, 128, 154], [6, 28, 54, 80, 106, 132, 158], [6, 32, 58, 84, 110, 136, 162], [6, 26, 54, 82, 110, 138, 166], [6, 30, 58, 86, 114, 142, 170],
    ];

    /**
     * The exponent table of GF(256).
     *
     * @var int[]
     */
    private static array $exp = [];

    /**
     * The logarithm table of GF(256).
     *
     * @var int[]
     */
    private static array $log = [];

    /**
     * The error correction level.
     *
     * @var string
     */
    private string $level;

    /**
     * The version, 1 to 40.
     *
     * @var int
     */
    private int $version;

    /**
     * The number of modules on one side.
     *
     * @var int
     */
    private int $size;

    /**
     * The matrix while it is made. The value null is a free module.
     *
     * @var array<int, array<int, bool|null>>
     */
    private array $modules = [];

    /**
     * The data and error correction codewords, in placement order.
     *
     * @var int[]
     */
    private array $codewords;

    /**
     * Prepare the codewords for a payload.
     *
     * @param  string  $data
     * @param  string  $level
     * @return void
     */
    private function __construct(string $data, string $level)
    {
        self::initGalois();
        $this->level = $level;
        $this->version = self::pickVersion(strlen($data), $level);
        $this->size = $this->version * 4 + 17;
        $this->codewords = $this->createCodewords($data);
    }

    /**
     * Encodes a payload. The payload is written as raw bytes. Give UTF-8 text.
     *
     * @param  string  $data
     * @param  int|null  $mask  A mask pattern 0-7, or null to select the best mask.
     * @param  string  $level  The error correction level: L, M, Q or H.
     * @return bool[][] A square matrix. The value true is a dark module.
     */
    public static function encode(string $data, ?int $mask = null, string $level = 'H'): array
    {
        self::checkLevel($level);
        $encoder = new self($data, $level);
        if ($mask === null) {
            $mask = $encoder->bestMask();
        }
        $encoder->build(false, $mask);

        $matrix = [];
        foreach ($encoder->modules as $r => $row) {
            foreach ($row as $c => $v) {
                $matrix[$r][$c] = (bool) $v;
            }
        }

        return $matrix;
    }

    /**
     * Gives the maximum payload in bytes for a level (version 40).
     *
     * @param  string  $level
     * @return int
     */
    public static function capacity(string $level = 'H'): int
    {
        self::checkLevel($level);

        return self::dataCapacity(40, $level);
    }

    /**
     * Make sure that an error correction level is known.
     *
     * @param  string  $level
     * @return void
     */
    private static function checkLevel(string $level): void
    {
        if (! isset(self::LEVEL_BITS[$level])) {
            throw new InvalidOptionException(sprintf(
                'errorCorrection: expected one of %s - got "%s"',
                implode(', ', self::LEVELS),
                $level
            ));
        }
    }

    /**
     * Get the payload capacity in bytes of a version at a level.
     *
     * @param  int  $version
     * @param  string  $level
     * @return int
     */
    private static function dataCapacity(int $version, string $level): int
    {
        $bits = self::dataCodewordCount($version, $level) * 8 - 4 - self::countBits($version);

        return intdiv($bits, 8);
    }

    /**
     * Get the smallest version that holds a payload.
     *
     * @param  int  $length
     * @param  string  $level
     * @return int
     */
    private static function pickVersion(int $length, string $level): int
    {
        for ($v = 1; $v <= 40; $v++) {
            if ($length <= self::dataCapacity($v, $level)) {
                return $v;
            }
        }
        $message = sprintf(
            'text: %d bytes is too long for error correction level %s (maximum %d bytes)',
            $length,
            $level,
            self::dataCapacity(40, $level)
        );
        if ($level !== 'L' && $length <= self::dataCapacity(40, 'L')) {
            $message .= '. Use a lower errorCorrection level';
        }
        throw new DataTooLongException($message);
    }

    /**
     * Get the number of bits of the character count for a version.
     *
     * @param  int  $version
     * @return int
     */
    private static function countBits(int $version): int
    {
        return $version < 10 ? 8 : 16;
    }

    /**
     * Get the Reed-Solomon blocks of a version at a level.
     *
     * @param  int  $version
     * @param  string  $level
     * @return array<int, array{0:int, 1:int}> One [total, data] pair for each block.
     */
    private static function blocks(int $version, string $level): array
    {
        $spec = self::RS_BLOCKS[$level][$version - 1];
        $blocks = [];
        for ($i = 0, $n = count($spec); $i < $n; $i += 3) {
            for ($k = 0; $k < $spec[$i]; $k++) {
                $blocks[] = [$spec[$i + 1], $spec[$i + 2]];
            }
        }

        return $blocks;
    }

    /**
     * Get the number of data codewords of a version at a level.
     *
     * @param  int  $version
     * @param  string  $level
     * @return int
     */
    private static function dataCodewordCount(int $version, string $level): int
    {
        $total = 0;
        foreach (self::blocks($version, $level) as [, $data]) {
            $total += $data;
        }

        return $total;
    }

    /* ── Data and error correction ─────────────────────────────── */

    /**
     * Make the data and error correction codewords for the payload.
     *
     * @param  string  $data
     * @return int[]
     */
    private function createCodewords(string $data): array
    {
        $bits = [];
        $put = static function (int $value, int $length) use (&$bits): void {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };

        $put(self::MODE_BYTE, 4);
        $put(strlen($data), self::countBits($this->version));
        foreach (unpack('C*', $data) ?: [] as $byte) {
            $put($byte, 8);
        }

        $capacityBits = self::dataCodewordCount($this->version, $this->level) * 8;
        if (count($bits) + 4 <= $capacityBits) {
            $put(0, 4);
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $pad = [self::PAD0, self::PAD1];
        for ($i = 0; count($bits) < $capacityBits; $i++) {
            $put($pad[$i % 2], 8);
        }

        $bytes = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $bytes[] = (int) bindec(implode('', $chunk));
        }

        $offset = 0;
        $dataBlocks = [];
        $ecBlocks = [];
        foreach (self::blocks($this->version, $this->level) as [$total, $dataCount]) {
            $block = array_slice($bytes, $offset, $dataCount);
            $offset += $dataCount;
            $dataBlocks[] = $block;
            $ecBlocks[] = self::reedSolomon($block, $total - $dataCount);
        }

        // Interleave the blocks: first all data codewords, then all error correction codewords.
        $out = [];
        foreach ([$dataBlocks, $ecBlocks] as $group) {
            $longest = 0;
            foreach ($group as $block) {
                $longest = max($longest, count($block));
            }
            for ($i = 0; $i < $longest; $i++) {
                foreach ($group as $block) {
                    if (isset($block[$i])) {
                        $out[] = $block[$i];
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Fill the exponent and logarithm tables of the Galois field GF(256).
     *
     * @return void
     */
    private static function initGalois(): void
    {
        if (self::$exp) {
            return;
        }
        for ($i = 0; $i < 8; $i++) {
            self::$exp[$i] = 1 << $i;
        }
        for ($i = 8; $i < 256; $i++) {
            self::$exp[$i] = self::$exp[$i - 4] ^ self::$exp[$i - 5] ^ self::$exp[$i - 6] ^ self::$exp[$i - 8];
        }
        for ($i = 0; $i < 255; $i++) {
            self::$log[self::$exp[$i]] = $i;
        }
    }

    /**
     * Get the power of the field generator for an exponent.
     *
     * @param  int  $n
     * @return int
     */
    private static function gexp(int $n): int
    {
        $n %= 255;

        return self::$exp[$n < 0 ? $n + 255 : $n];
    }

    /**
     * Calculate the Reed-Solomon error correction codewords for a block.
     *
     * @param  int[]  $data
     * @param  int  $ecCount
     * @return int[] The error correction codewords.
     */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        // The generator polynomial is the product of (x - a^i) for i < ecCount.
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                if ($coef !== 0) {
                    $next[$j + 1] ^= self::gexp(self::$log[$coef] + $i);
                }
            }
            $gen = $next;
        }

        $rem = array_merge($data, array_fill(0, $ecCount, 0));
        $dataCount = count($data);
        for ($i = 0; $i < $dataCount; $i++) {
            $lead = $rem[$i];
            if ($lead === 0) {
                continue;
            }
            $ratio = self::$log[$lead];
            foreach ($gen as $j => $coef) {
                if ($coef !== 0) {
                    $rem[$i + $j] ^= self::gexp(self::$log[$coef] + $ratio);
                }
            }
        }

        return array_slice($rem, $dataCount);
    }

    /* ── Matrix layout ─────────────────────────────────────────── */

    /**
     * Make the matrix with a mask.
     *
     * @param  bool  $test  Set to true to leave the format and the version information empty.
     * @param  int  $mask
     * @return void
     */
    private function build(bool $test, int $mask): void
    {
        $n = $this->size;
        $this->modules = array_fill(0, $n, array_fill(0, $n, null));

        $this->placeFinder(0, 0);
        $this->placeFinder($n - 7, 0);
        $this->placeFinder(0, $n - 7);
        $this->placeAlignment();
        $this->placeTiming();
        $this->placeFormat($test, $mask);
        if ($this->version >= 7) {
            $this->placeVersion($test);
        }
        $this->placeData($mask);
    }

    /**
     * Put a finder pattern and its separator at a position.
     *
     * @param  int  $row
     * @param  int  $col
     * @return void
     */
    private function placeFinder(int $row, int $col): void
    {
        for ($r = -1; $r <= 7; $r++) {
            if ($row + $r <= -1 || $this->size <= $row + $r) {
                continue;
            }
            for ($c = -1; $c <= 7; $c++) {
                if ($col + $c <= -1 || $this->size <= $col + $c) {
                    continue;
                }
                $this->modules[$row + $r][$col + $c] =
                    ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                    || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6))
                    || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
            }
        }
    }

    /**
     * Put the alignment patterns.
     *
     * @return void
     */
    private function placeAlignment(): void
    {
        $pos = self::ALIGNMENT[$this->version - 1];
        foreach ($pos as $row) {
            foreach ($pos as $col) {
                if ($this->modules[$row][$col] !== null) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $this->modules[$row + $r][$col + $c] =
                            $r === -2 || $r === 2 || $c === -2 || $c === 2 || ($r === 0 && $c === 0);
                    }
                }
            }
        }
    }

    /**
     * Put the timing patterns.
     *
     * @return void
     */
    private function placeTiming(): void
    {
        for ($i = 8; $i < $this->size - 8; $i++) {
            if ($this->modules[$i][6] === null) {
                $this->modules[$i][6] = $i % 2 === 0;
            }
            if ($this->modules[6][$i] === null) {
                $this->modules[6][$i] = $i % 2 === 0;
            }
        }
    }

    /**
     * Put the format information.
     *
     * @param  bool  $test
     * @param  int  $mask
     * @return void
     */
    private function placeFormat(bool $test, int $mask): void
    {
        $n = $this->size;
        $bits = self::bchFormat((self::LEVEL_BITS[$this->level] << 3) | $mask);
        for ($i = 0; $i < 15; $i++) {
            $mod = ! $test && (($bits >> $i) & 1) === 1;
            if ($i < 6) {
                $this->modules[$i][8] = $mod;
            } elseif ($i < 8) {
                $this->modules[$i + 1][8] = $mod;
            } else {
                $this->modules[$n - 15 + $i][8] = $mod;
            }

            if ($i < 8) {
                $this->modules[8][$n - $i - 1] = $mod;
            } elseif ($i < 9) {
                $this->modules[8][15 - $i] = $mod;
            } else {
                $this->modules[8][14 - $i] = $mod;
            }
        }
        $this->modules[$n - 8][8] = ! $test;
    }

    /**
     * Put the version information (version 7 and higher).
     *
     * @param  bool  $test
     * @return void
     */
    private function placeVersion(bool $test): void
    {
        $bits = self::bchVersion($this->version);
        for ($i = 0; $i < 18; $i++) {
            $mod = ! $test && (($bits >> $i) & 1) === 1;
            $this->modules[intdiv($i, 3)][$i % 3 + $this->size - 11] = $mod;
            $this->modules[$i % 3 + $this->size - 11][intdiv($i, 3)] = $mod;
        }
    }

    /**
     * Put the codewords into the free modules and apply the mask.
     *
     * @param  int  $mask
     * @return void
     */
    private function placeData(int $mask): void
    {
        $data = $this->codewords;
        $dataLength = count($data);
        $inc = -1;
        $row = $this->size - 1;
        $bitIndex = 7;
        $byteIndex = 0;

        for ($col = $this->size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            while (true) {
                for ($c = 0; $c < 2; $c++) {
                    if ($this->modules[$row][$col - $c] !== null) {
                        continue;
                    }
                    $dark = false;
                    if ($byteIndex < $dataLength) {
                        $dark = (($data[$byteIndex] >> $bitIndex) & 1) === 1;
                    }
                    if (self::maskBit($mask, $row, $col - $c)) {
                        $dark = ! $dark;
                    }
                    $this->modules[$row][$col - $c] = $dark;
                    $bitIndex--;
                    if ($bitIndex === -1) {
                        $byteIndex++;
                        $bitIndex = 7;
                    }
                }
                $row += $inc;
                if ($row < 0 || $this->size <= $row) {
                    $row -= $inc;
                    $inc = -$inc;
                    break;
                }
            }
        }
    }

    /**
     * Determine if a mask inverts the module at a row and a column.
     *
     * @param  int  $mask
     * @param  int  $i
     * @param  int  $j
     * @return bool
     */
    private static function maskBit(int $mask, int $i, int $j): bool
    {
        switch ($mask) {
            case 0: return ($i + $j) % 2 === 0;
            case 1: return $i % 2 === 0;
            case 2: return $j % 3 === 0;
            case 3: return ($i + $j) % 3 === 0;
            case 4: return (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0;
            case 5: return ($i * $j) % 2 + ($i * $j) % 3 === 0;
            case 6: return (($i * $j) % 2 + ($i * $j) % 3) % 2 === 0;
            case 7: return (($i * $j) % 3 + ($i + $j) % 2) % 2 === 0;
        }
        throw new InvalidOptionException('mask: expected an integer 0-7 - got '.$mask);
    }

    /**
     * Get the number of significant bits of a value.
     *
     * @param  int  $data
     * @return int
     */
    private static function bchDigit(int $data): int
    {
        $digit = 0;
        while ($data !== 0) {
            $digit++;
            $data >>= 1;
        }

        return $digit;
    }

    /**
     * Calculate the BCH code of the format information.
     *
     * @param  int  $data
     * @return int
     */
    private static function bchFormat(int $data): int
    {
        $d = $data << 10;
        while (self::bchDigit($d) - self::bchDigit(self::G15) >= 0) {
            $d ^= self::G15 << (self::bchDigit($d) - self::bchDigit(self::G15));
        }

        return (($data << 10) | $d) ^ self::G15_MASK;
    }

    /**
     * Calculate the BCH code of the version information.
     *
     * @param  int  $data
     * @return int
     */
    private static function bchVersion(int $data): int
    {
        $d = $data << 12;
        while (self::bchDigit($d) - self::bchDigit(self::G18) >= 0) {
            $d ^= self::G18 << (self::bchDigit($d) - self::bchDigit(self::G18));
        }

        return ($data << 12) | $d;
    }

    /* ── Mask selection ────────────────────────────────────────── */

    /**
     * Get the mask with the lowest penalty score.
     *
     * @return int
     */
    private function bestMask(): int
    {
        $best = 0;
        $bestScore = 0.0;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->build(true, $mask);
            $score = $this->penalty();
            if ($mask === 0 || $bestScore > $score) {
                $bestScore = $score;
                $best = $mask;
            }
        }

        return $best;
    }

    /**
     * Gives the penalty score of the current matrix. A lower score is better.
     *
     * @return float
     */
    private function penalty(): float
    {
        $n = $this->size;
        $m = [];
        foreach ($this->modules as $r => $row) {
            foreach ($row as $c => $v) {
                $m[$r][$c] = (bool) $v;
            }
        }
        $lost = 0.0;

        // Rule 1: a module with more than five neighbours of the same colour.
        for ($row = 0; $row < $n; $row++) {
            for ($col = 0; $col < $n; $col++) {
                $same = 0;
                $dark = $m[$row][$col];
                for ($r = -1; $r <= 1; $r++) {
                    if ($row + $r < 0 || $n <= $row + $r) {
                        continue;
                    }
                    for ($c = -1; $c <= 1; $c++) {
                        if ($col + $c < 0 || $n <= $col + $c || ($r === 0 && $c === 0)) {
                            continue;
                        }
                        if ($dark === $m[$row + $r][$col + $c]) {
                            $same++;
                        }
                    }
                }
                if ($same > 5) {
                    $lost += 3 + $same - 5;
                }
            }
        }

        // Rule 2: a 2 x 2 block of one colour.
        for ($row = 0; $row < $n - 1; $row++) {
            for ($col = 0; $col < $n - 1; $col++) {
                $count = (int) $m[$row][$col] + (int) $m[$row + 1][$col]
                    + (int) $m[$row][$col + 1] + (int) $m[$row + 1][$col + 1];
                if ($count === 0 || $count === 4) {
                    $lost += 3;
                }
            }
        }

        // Rule 3: a pattern that looks like a finder (1:1:3:1:1).
        for ($row = 0; $row < $n; $row++) {
            for ($col = 0; $col < $n - 6; $col++) {
                if ($m[$row][$col] && ! $m[$row][$col + 1] && $m[$row][$col + 2] && $m[$row][$col + 3]
                    && $m[$row][$col + 4] && ! $m[$row][$col + 5] && $m[$row][$col + 6]) {
                    $lost += 40;
                }
            }
        }
        for ($col = 0; $col < $n; $col++) {
            for ($row = 0; $row < $n - 6; $row++) {
                if ($m[$row][$col] && ! $m[$row + 1][$col] && $m[$row + 2][$col] && $m[$row + 3][$col]
                    && $m[$row + 4][$col] && ! $m[$row + 5][$col] && $m[$row + 6][$col]) {
                    $lost += 40;
                }
            }
        }

        // Rule 4: the balance of dark and light modules.
        $darkCount = 0;
        foreach ($m as $row) {
            $darkCount += count(array_filter($row));
        }
        $lost += abs(100 * $darkCount / $n / $n - 50) / 5 * 10;

        return $lost;
    }
}

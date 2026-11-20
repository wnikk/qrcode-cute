<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Raster;

/**
 * Encode an RGBA image as lossless WebP (VP8L, RFC 9649).
 *
 * The encoder uses no transforms and no colour cache. It finds backward
 * references to the left pixel and to the pixel above, and codes all
 * symbols with length-limited prefix codes. QR images have large areas of
 * one colour, so these simple steps give files of approximately PNG size.
 *
 * @internal
 */
final class WebpEncoder
{
    /**
     * The order in which the code length code lengths are written.
     *
     * @var int[]
     */
    private const CODE_LENGTH_ORDER = [17, 18, 0, 1, 2, 3, 4, 5, 16, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

    /**
     * The maximum length of a backward reference.
     *
     * @var int
     */
    private const MAX_LENGTH = 4096;

    /**
     * The minimum length that makes a backward reference useful.
     *
     * @var int
     */
    private const MIN_LENGTH = 3;

    /**
     * The bits that are not written yet.
     *
     * @var int
     */
    private int $bits = 0;

    /**
     * The number of bits in $bits.
     *
     * @var int
     */
    private int $bitCount = 0;

    /**
     * The bytes that are written.
     *
     * @var string
     */
    private string $out = '';

    /**
     * Encode the image.
     *
     * @param  string  $rgba  The pixels, 4 bytes each, row by row.
     * @param  int  $width
     * @param  int  $height
     * @return string
     */
    public static function encode(string $rgba, int $width, int $height): string
    {
        $encoder = new self;
        $vp8l = $encoder->vp8l($rgba, $width, $height);
        if (strlen($vp8l) % 2 === 1) {
            $vp8l .= "\0";
            $chunkSize = strlen($vp8l) - 1;
        } else {
            $chunkSize = strlen($vp8l);
        }

        return 'RIFF'.pack('V', 4 + 8 + strlen($vp8l)).'WEBP'.'VP8L'.pack('V', $chunkSize).$vp8l;
    }

    /**
     * Make the VP8L bitstream.
     *
     * @param  string  $rgba
     * @param  int  $width
     * @param  int  $height
     * @return string
     */
    private function vp8l(string $rgba, int $width, int $height): string
    {
        $pixelCount = $width * $height;
        $alpha = (string) preg_replace('/.../s', '', $rgba);
        $hasAlpha = strspn($alpha, "\xFF") !== strlen($alpha);

        $this->out = "\x2F";
        $this->write($width - 1, 14);
        $this->write($height - 1, 14);
        $this->write($hasAlpha ? 1 : 0, 1);
        $this->write(0, 3);     // version
        $this->write(0, 1);     // no transform
        $this->write(0, 1);     // no colour cache
        $this->write(0, 1);     // one prefix code group

        // Find the tokens: a literal pixel (as ARGB) or a backward reference.
        // A reference is stored as a negative pair: -(length), then the distance code.
        $tokens = [];
        $green = array_fill(0, 280, 0);
        $red = array_fill(0, 256, 0);
        $blue = array_fill(0, 256, 0);
        $alphas = array_fill(0, 256, 0);
        $distance = array_fill(0, 40, 0);

        $i = 0;
        while ($i < $pixelCount) {
            $bestLength = 0;
            $bestCode = 0;
            $limit = min(self::MAX_LENGTH, $pixelCount - $i);
            if ($i >= 1) {
                $length = self::matchLength($rgba, $i, 1, $limit);
                if ($length > $bestLength) {
                    $bestLength = $length;
                    $bestCode = 2;  // plane code 2 is the left pixel
                }
            }
            if ($i >= $width) {
                $length = self::matchLength($rgba, $i, $width, $limit);
                if ($length > $bestLength) {
                    $bestLength = $length;
                    $bestCode = 1;  // plane code 1 is the pixel above
                }
            }

            if ($bestLength >= self::MIN_LENGTH) {
                $tokens[] = -$bestLength;
                $tokens[] = $bestCode;
                $green[256 + self::prefix($bestLength)[0]]++;
                $distance[self::prefix($bestCode)[0]]++;
                $i += $bestLength;

                continue;
            }

            $o = $i * 4;
            $r = ord($rgba[$o]);
            $g = ord($rgba[$o + 1]);
            $b = ord($rgba[$o + 2]);
            $a = ord($rgba[$o + 3]);
            $tokens[] = ($a << 24) | ($r << 16) | ($g << 8) | $b;
            $green[$g]++;
            $red[$r]++;
            $blue[$b]++;
            $alphas[$a]++;
            $i++;
        }

        $codes = [];
        foreach ([$green, $red, $blue, $alphas, $distance] as $histogram) {
            $codes[] = $this->writePrefixCode($histogram);
        }
        [$greenCode, $redCode, $blueCode, $alphaCode, $distanceCode] = $codes;

        $count = count($tokens);
        for ($t = 0; $t < $count; $t++) {
            $token = $tokens[$t];
            if ($token >= 0) {
                $this->writeSymbol($greenCode, ($token >> 8) & 0xFF);
                $this->writeSymbol($redCode, ($token >> 16) & 0xFF);
                $this->writeSymbol($blueCode, $token & 0xFF);
                $this->writeSymbol($alphaCode, ($token >> 24) & 0xFF);

                continue;
            }
            [$prefix, $extraBits, $extra] = self::prefix(-$token);
            $this->writeSymbol($greenCode, 256 + $prefix);
            $this->write($extra, $extraBits);
            [$prefix, $extraBits, $extra] = self::prefix($tokens[++$t]);
            $this->writeSymbol($distanceCode, $prefix);
            $this->write($extra, $extraBits);
        }

        $this->flush();

        return $this->out;
    }

    /**
     * Get the number of pixels at $i that are equal to the pixels $distance before.
     *
     * @param  string  $rgba
     * @param  int  $i
     * @param  int  $distance
     * @param  int  $limit
     * @return int
     */
    private static function matchLength(string $rgba, int $i, int $distance, int $limit): int
    {
        // Compare a short window first. Most positions fail there.
        $length = 0;
        $window = 16;
        while ($length < $limit) {
            $n = min($window, $limit - $length);
            $a = substr($rgba, ($i + $length) * 4, $n * 4);
            $b = substr($rgba, ($i + $length - $distance) * 4, $n * 4);
            $same = intdiv(strspn($a ^ $b, "\0"), 4);
            $length += $same;
            if ($same < $n) {
                break;
            }
            $window = 1024;
        }

        return $length;
    }

    /**
     * Get the prefix code, the number of extra bits and the extra bits for a value of 1 or more.
     *
     * @param  int  $value
     * @return array{0: int, 1: int, 2: int}
     */
    private static function prefix(int $value): array
    {
        $d = $value - 1;
        if ($d < 4) {
            return [$d, 0, 0];
        }
        $high = (int) floor(log($d, 2));
        // log() can be wrong by one at exact powers of two.
        while ((1 << $high) > $d) {
            $high--;
        }
        while ((2 << $high) <= $d) {
            $high++;
        }
        $second = ($d >> ($high - 1)) & 1;
        $extraBits = $high - 1;

        return [2 * $high + $second, $extraBits, $d & ((1 << $extraBits) - 1)];
    }

    /**
     * Write a prefix code for a histogram and get the code for each symbol.
     *
     * @param  int[]  $histogram
     * @return array<int, array{0: int, 1: int}> The bit-reversed code and its length, by symbol.
     */
    private function writePrefixCode(array $histogram): array
    {
        $used = array_keys(array_filter($histogram));

        // A code with one or two small symbols uses the simple form.
        if (count($used) <= 2 && max($used ?: [0]) < 256) {
            $symbols = $used ?: [0];
            $this->write(1, 1);
            $this->write(count($symbols) - 1, 1);
            if ($symbols[0] < 2) {
                $this->write(0, 1);
                $this->write($symbols[0], 1);
            } else {
                $this->write(1, 1);
                $this->write($symbols[0], 8);
            }
            if (count($symbols) === 2) {
                $this->write($symbols[1], 8);

                return [$symbols[0] => [0, 1], $symbols[1] => [1, 1]];
            }

            // One symbol has a code of zero length.
            return [$symbols[0] => [0, 0]];
        }

        $lengths = self::codeLengths($histogram, 15);
        $this->write(0, 1);
        $this->writeCodeLengths($lengths);

        return self::canonicalCodes($lengths);
    }

    /**
     * Write the code lengths of a normal prefix code.
     *
     * @param  int[]  $lengths
     * @return void
     */
    private function writeCodeLengths(array $lengths): void
    {
        // Change the lengths into tokens: 0-15 literal, 16 repeat, 17 and 18 zero runs.
        $tokens = [];
        $count = count($lengths);
        $previous = 8;
        for ($i = 0; $i < $count;) {
            $value = $lengths[$i];
            $run = 1;
            while ($i + $run < $count && $lengths[$i + $run] === $value) {
                $run++;
            }
            if ($value === 0) {
                $left = $run;
                while ($left >= 11) {
                    $n = min($left, 138);
                    $tokens[] = [18, $n - 11, 7];
                    $left -= $n;
                }
                if ($left >= 3) {
                    $tokens[] = [17, $left - 3, 3];
                    $left = 0;
                }
                for (; $left > 0; $left--) {
                    $tokens[] = [0, 0, 0];
                }
            } else {
                $left = $run;
                if ($value !== $previous) {
                    $tokens[] = [$value, 0, 0];
                    $left--;
                }
                while ($left >= 3) {
                    $n = min($left, 6);
                    $tokens[] = [16, $n - 3, 2];
                    $left -= $n;
                }
                for (; $left > 0; $left--) {
                    $tokens[] = [$value, 0, 0];
                }
                $previous = $value;
            }
            $i += $run;
        }

        $histogram = array_fill(0, 19, 0);
        foreach ($tokens as [$symbol]) {
            $histogram[$symbol]++;
        }
        $metaLengths = self::codeLengths($histogram, 7);
        if (count(array_filter($metaLengths)) === 1) {
            // A code needs two symbols; give one more symbol a length.
            $metaLengths[array_key_first(array_filter($metaLengths)) === 0 ? 1 : 0] = 1;
        }
        $metaCodes = self::canonicalCodes($metaLengths);

        $numCodes = 19;
        while ($numCodes > 4 && $metaLengths[self::CODE_LENGTH_ORDER[$numCodes - 1]] === 0) {
            $numCodes--;
        }
        $this->write($numCodes - 4, 4);
        for ($k = 0; $k < $numCodes; $k++) {
            $this->write($metaLengths[self::CODE_LENGTH_ORDER[$k]], 3);
        }
        $this->write(0, 1);     // the code lengths go to the end of the alphabet

        foreach ($tokens as [$symbol, $extra, $extraBits]) {
            $this->writeSymbol($metaCodes, $symbol);
            $this->write($extra, $extraBits);
        }
    }

    /**
     * Get Huffman code lengths with a maximum length.
     *
     * @param  int[]  $histogram
     * @param  int  $maxLength
     * @return int[]
     */
    private static function codeLengths(array $histogram, int $maxLength): array
    {
        $lengths = array_fill(0, count($histogram), 0);
        $counts = array_filter($histogram);
        if (count($counts) === 1) {
            $lengths[array_key_first($counts)] = 1;

            return $lengths;
        }

        while (true) {
            $heap = new \SplPriorityQueue;
            $heap->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
            $parent = [];
            $node = 0;
            foreach ($counts as $symbol => $c) {
                // SplPriorityQueue gives the highest priority first, so use negative counts.
                $heap->insert(['leaf' => $symbol, 'id' => $node, 'w' => $c], [-$c, -$node]);
                $node++;
            }
            $leafNode = [];
            while ($heap->count() > 1) {
                $a = $heap->extract();
                $b = $heap->extract();
                $id = $node++;
                foreach ([$a, $b] as $child) {
                    $parent[$child['id']] = $id;
                    if ($child['leaf'] !== null) {
                        $leafNode[$child['leaf']] = $child['id'];
                    }
                }
                $w = $a['w'] + $b['w'];
                $heap->insert(['leaf' => null, 'id' => $id, 'w' => $w], [-$w, -$id]);
            }

            $tooLong = false;
            foreach ($leafNode as $symbol => $id) {
                $depth = 0;
                while (isset($parent[$id])) {
                    $id = $parent[$id];
                    $depth++;
                }
                $lengths[$symbol] = $depth;
                if ($depth > $maxLength) {
                    $tooLong = true;
                }
            }
            if (! $tooLong) {
                return $lengths;
            }

            // Make the counts more equal and try again.
            foreach ($counts as $symbol => $c) {
                $counts[$symbol] = intdiv($c, 2) + 1;
            }
        }
    }

    /**
     * Get canonical codes from code lengths. Each code is bit-reversed for the LSB-first writer.
     *
     * @param  int[]  $lengths
     * @return array<int, array{0: int, 1: int}>
     */
    private static function canonicalCodes(array $lengths): array
    {
        if ($lengths === []) {
            return [];
        }
        $maxLength = max($lengths);
        $countByLength = array_fill(0, $maxLength + 1, 0);
        foreach ($lengths as $length) {
            if ($length > 0) {
                $countByLength[$length]++;
            }
        }
        $next = array_fill(0, $maxLength + 1, 0);
        $code = 0;
        for ($bits = 1; $bits <= $maxLength; $bits++) {
            $code = ($code + $countByLength[$bits - 1]) << 1;
            $next[$bits] = $code;
        }

        $codes = [];
        foreach ($lengths as $symbol => $length) {
            if ($length === 0) {
                continue;
            }
            $value = $next[$length]++;
            $reversed = 0;
            for ($k = 0; $k < $length; $k++) {
                $reversed = ($reversed << 1) | (($value >> $k) & 1);
            }
            $codes[$symbol] = [$reversed, $length];
        }

        return $codes;
    }

    /**
     * Write the code of one symbol.
     *
     * @param  array  $codes
     * @param  int  $symbol
     * @param  array<int, array{0: int, 1: int}>  $codes
     * @return void
     */
    private function writeSymbol(array $codes, int $symbol): void
    {
        [$code, $length] = $codes[$symbol];
        if ($length > 0) {
            $this->write($code, $length);
        }
    }

    /**
     * Write bits, the least significant bit first.
     *
     * @param  int  $value
     * @param  int  $count
     * @return void
     */
    private function write(int $value, int $count): void
    {
        if ($count === 0) {
            return;
        }
        $this->bits |= $value << $this->bitCount;
        $this->bitCount += $count;
        if ($this->bitCount >= 32) {
            $this->out .= pack('V', $this->bits & 0xFFFFFFFF);
            $this->bits >>= 32;
            $this->bitCount -= 32;
        }
    }

    /**
     * Write the bits that remain, with zero padding to a full byte.
     *
     * @return void
     */
    private function flush(): void
    {
        while ($this->bitCount > 0) {
            $this->out .= chr($this->bits & 0xFF);
            $this->bits >>= 8;
            $this->bitCount -= 8;
        }
        $this->bits = 0;
        $this->bitCount = 0;
    }
}

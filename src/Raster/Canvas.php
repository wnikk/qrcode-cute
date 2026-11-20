<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Raster;

/**
 * An RGBA pixel buffer with an anti-aliased polygon fill and a PNG writer.
 *
 * The fill goes one scanline at a time. Each pixel row has some sub-scanlines.
 * On each sub-scanline, the horizontal coverage of a span is exact. This gives
 * smooth edges and is faster than full supersampling. Each row is a binary
 * string with 4 bytes for each pixel. A 1024x1024 canvas uses approximately 4 MB.
 *
 * @internal
 */
final class Canvas
{
    /**
     * The number of sub-scanlines in one pixel row.
     *
     * @var int
     */
    private const SUBSAMPLES = 5;

    /**
     * The width in pixels.
     *
     * @var int
     */
    private int $width;

    /**
     * The height in pixels.
     *
     * @var int
     */
    private int $height;

    /**
     * One RGBA string for each pixel row.
     *
     * @var string[] one RGBA string per row
     */
    private array $rows;

    /**
     * Create a transparent canvas.
     *
     * @param  int  $width
     * @param  int  $height
     * @return void
     */
    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
        $this->rows = array_fill(0, $height, str_repeat("\0\0\0\0", $width));
    }

    /**
     * Get the width in pixels.
     *
     * @return int
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * Get the height in pixels.
     *
     * @return int
     */
    public function height(): int
    {
        return $this->height;
    }

    /**
     * Fills polygons in device space.
     *
     * @param  array<int, float[]>  $polygons  Each polygon is a flat list x0, y0, x1, y1, ...; closed implicitly.
     * @param  bool  $evenOdd  true for the evenodd fill rule, false for nonzero.
     * @param  array{0:int, 1:int, 2:int, 3:float}  $color  r, g, b in 0-255 and alpha in 0-1.
     * @return void
     */
    public function fill(array $polygons, bool $evenOdd, array $color): void
    {
        if ($color[3] <= 0) {
            return;
        }

        // Each edge is [yTop, yBottom, xAtTop, dx/dy, direction].
        $edges = [];
        $minX = INF;
        $minY = INF;
        $maxX = -INF;
        $maxY = -INF;
        foreach ($polygons as $pts) {
            $count = count($pts);
            if ($count < 6) {
                continue;
            }
            for ($i = 0; $i < $count; $i += 2) {
                $x0 = $pts[$i];
                $y0 = $pts[$i + 1];
                $j = ($i + 2) % $count;
                $x1 = $pts[$j];
                $y1 = $pts[$j + 1];
                if ($x0 < $minX) {
                    $minX = $x0;
                }
                if ($x0 > $maxX) {
                    $maxX = $x0;
                }
                if ($y0 < $minY) {
                    $minY = $y0;
                }
                if ($y0 > $maxY) {
                    $maxY = $y0;
                }
                if ($y0 == $y1) {
                    continue;
                }
                if ($y0 < $y1) {
                    $edges[] = [$y0, $y1, $x0, ($x1 - $x0) / ($y1 - $y0), 1];
                } else {
                    $edges[] = [$y1, $y0, $x1, ($x0 - $x1) / ($y0 - $y1), -1];
                }
            }
        }
        if (! $edges) {
            return;
        }

        $bx0 = max(0, (int) floor($minX));
        $bx1 = min($this->width, (int) ceil($maxX));
        $by0 = max(0, (int) floor($minY));
        $by1 = min($this->height, (int) ceil($maxY));
        if ($bx0 >= $bx1 || $by0 >= $by1) {
            return;
        }
        $spanW = $bx1 - $bx0;

        usort($edges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $edgeCount = count($edges);
        $next = 0;
        $active = [];

        $sub = self::SUBSAMPLES;
        $w = 1.0 / $sub;
        $zeros = array_fill(0, $spanW + 1, 0.0);

        [$cr, $cg, $cb, $ca] = $color;
        $opaque = $ca >= 1.0;
        $solid = chr($cr).chr($cg).chr($cb).chr(255);

        for ($py = $by0; $py < $by1; $py++) {
            $cov = $zeros;
            $delta = $zeros;
            $touched = false;

            for ($s = 0; $s < $sub; $s++) {
                $sy = $py + ($s + 0.5) * $w;

                while ($next < $edgeCount && $edges[$next][0] <= $sy) {
                    $active[] = $edges[$next++];
                }
                if (! $active) {
                    continue;
                }

                $xs = [];
                $dirs = [];
                $keep = [];
                foreach ($active as $e) {
                    if ($e[1] <= $sy) {
                        continue;
                    }
                    $keep[] = $e;
                    if ($e[0] <= $sy) {
                        $xs[] = $e[2] + ($sy - $e[0]) * $e[3];
                        $dirs[] = $e[4];
                    }
                }
                $active = $keep;
                if (count($xs) < 2) {
                    continue;
                }
                array_multisort($xs, SORT_NUMERIC, $dirs);

                $winding = 0;
                $start = 0.0;
                foreach ($xs as $k => $x) {
                    $dir = $dirs[$k];
                    $was = $winding;
                    $winding = $evenOdd ? ($winding ^ 1) : $winding + $dir;
                    if ($was === 0 && $winding !== 0) {
                        $start = $x;

                        continue;
                    }
                    if ($was === 0 || $winding !== 0) {
                        continue;
                    }

                    // Add the coverage of the span [start, x) to the row.
                    $xa = $start - $bx0;
                    $xb = $x - $bx0;
                    if ($xa < 0) {
                        $xa = 0.0;
                    }
                    if ($xb > $spanW) {
                        $xb = (float) $spanW;
                    }
                    if ($xb <= $xa) {
                        continue;
                    }
                    $touched = true;
                    $ia = (int) $xa;
                    $ib = (int) $xb;
                    if ($ia === $ib) {
                        $cov[$ia] += ($xb - $xa) * $w;
                    } else {
                        $cov[$ia] += ($ia + 1 - $xa) * $w;
                        $delta[$ia + 1] += $w;
                        $delta[$ib] -= $w;
                        $cov[$ib] += ($xb - $ib) * $w;
                    }
                }
            }

            if (! $touched) {
                continue;
            }

            $row = &$this->rows[$py];
            $run = 0.0;
            $solidFrom = -1;
            for ($i = 0; $i <= $spanW; $i++) {
                $c = 0.0;
                if ($i < $spanW) {
                    $run += $delta[$i];
                    $c = $cov[$i] + $run;
                    if ($c >= 0.998 && $opaque) {
                        if ($solidFrom < 0) {
                            $solidFrom = $i;
                        }

                        continue;
                    }
                }
                if ($solidFrom >= 0) {
                    $len = $i - $solidFrom;
                    $row = substr_replace($row, str_repeat($solid, $len), ($bx0 + $solidFrom) * 4, $len * 4);
                    $solidFrom = -1;
                }
                if ($i === $spanW || $c <= 0.002) {
                    continue;
                }
                $o = ($bx0 + $i) * 4;
                $sa = ($c > 1.0 ? 1.0 : $c) * $ca;
                $da = ord($row[$o + 3]) / 255;
                $keepDst = $da * (1 - $sa);
                $outA = $sa + $keepDst;
                if ($outA <= 0) {
                    continue;
                }
                $row[$o] = chr((int) (($cr * $sa + ord($row[$o]) * $keepDst) / $outA + 0.5));
                $row[$o + 1] = chr((int) (($cg * $sa + ord($row[$o + 1]) * $keepDst) / $outA + 0.5));
                $row[$o + 2] = chr((int) (($cb * $sa + ord($row[$o + 2]) * $keepDst) / $outA + 0.5));
                $row[$o + 3] = chr((int) ($outA * 255 + 0.5));
            }
            unset($row);
        }
    }

    /**
     * Get all pixels as one RGBA string, row by row.
     *
     * @return string
     */
    public function toRgba(): string
    {
        return implode('', $this->rows);
    }

    /**
     * Encodes the canvas as PNG. An opaque canvas gives RGB. Other canvases give RGBA.
     *
     * @return string
     */
    public function toPng(): string
    {
        // A bitwise AND of two strings runs in C: keep only the alpha bytes and compare.
        $mask = str_repeat("\0\0\0\xFF", $this->width);
        $opaque = true;
        foreach ($this->rows as $row) {
            if (($row & $mask) !== $mask) {
                $opaque = false;
                break;
            }
        }

        $raw = '';
        foreach ($this->rows as $row) {
            $raw .= "\0".($opaque ? (string) preg_replace('/(...)./s', '$1', $row) : $row);
        }

        $ihdr = pack('NNCCCCC', $this->width, $this->height, 8, $opaque ? 2 : 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', $ihdr)
            .self::chunk('IDAT', self::zlib($raw))
            .self::chunk('IEND', '');
    }

    /**
     * Make a PNG chunk.
     *
     * @param  string  $type
     * @param  string  $data
     * @return string
     */
    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    /**
     * Gives a zlib stream. Uses ext-zlib if it is available. If not, writes blocks without compression.
     *
     * @param  string  $data
     * @return string
     */
    private static function zlib(string $data): string
    {
        if (function_exists('gzcompress')) {
            return (string) gzcompress($data, 6);
        }
        $out = "\x78\x01";
        $length = strlen($data);
        $offset = 0;
        do {
            $block = substr($data, $offset, 65535);
            $offset += strlen($block);
            $size = strlen($block);
            $out .= chr($offset >= $length ? 1 : 0).pack('v', $size).pack('v', $size ^ 0xFFFF).$block;
        } while ($offset < $length);

        return $out.hash('adler32', $data, true);
    }
}

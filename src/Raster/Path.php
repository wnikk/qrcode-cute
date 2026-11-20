<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Raster;

use Wnikk\QrCodeCute\Exception\RenderException;

/**
 * Changes SVG geometry into polylines.
 *
 * All path commands are possible (M L H V C S Q T A Z, absolute and relative).
 * There are also functions for rect, circle and polygon. Curves and arcs
 * become straight segments. The maximum distance from the curve is $tolerance.
 *
 * @internal
 */
final class Path
{
    /**
     * Read SVG path data.
     *
     * @param  string  $d
     * @param  float  $tolerance
     * @return array<int, array{points: float[], closed: bool}> subpaths, points as a flat x, y list
     */
    public static function fromData(string $d, float $tolerance): array
    {
        preg_match_all('/[MmLlHhVvCcSsQqTtAaZz]|[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/', $d, $m);
        $tokens = $m[0];
        $count = count($tokens);

        $subpaths = [];
        $points = [];
        $closed = false;
        $x = $y = 0.0;      // current point
        $sx = $sy = 0.0;    // subpath start
        $cx = $cy = 0.0;    // last control point, for S and T
        $prev = '';
        $cmd = '';
        $i = 0;

        $flush = static function () use (&$subpaths, &$points, &$closed): void {
            if (count($points) >= 4) {
                $subpaths[] = ['points' => $points, 'closed' => $closed];
            }
            $points = [];
            $closed = false;
        };
        $num = static function () use (&$i, &$tokens, $count, $d): float {
            if ($i >= $count || ! is_numeric($tokens[$i])) {
                throw new RenderException('The path data is not correct: '.substr($d, 0, 80));
            }

            return (float) $tokens[$i++];
        };
        // Arc flags can have no separators, as in "a1 1 0 01 2 2".
        $flag = static function () use (&$i, &$tokens, &$count, $d): bool {
            if ($i >= $count) {
                throw new RenderException('The path data is not correct: '.substr($d, 0, 80));
            }
            $t = $tokens[$i];
            if ($t === '0' || $t === '1') {
                $i++;

                return $t === '1';
            }
            if (($t[0] === '0' || $t[0] === '1') && strlen($t) > 1 && is_numeric($t)) {
                $tokens[$i] = substr($t, 1);

                return $t[0] === '1';
            }
            throw new RenderException('An arc flag in the path data is not correct: '.substr($d, 0, 80));
        };

        while ($i < $count) {
            if (! is_numeric($tokens[$i])) {
                $cmd = $tokens[$i++];
            } elseif ($cmd === '') {
                throw new RenderException('The path data must start with a command: '.substr($d, 0, 80));
            }
            $rel = ctype_lower($cmd);
            $upper = strtoupper($cmd);

            switch ($upper) {
                case 'M':
                    $flush();
                    $x = $num() + ($rel ? $x : 0);
                    $y = $num() + ($rel ? $y : 0);
                    $sx = $x;
                    $sy = $y;
                    $points = [$x, $y];
                    // More pairs after a moveto are linetos.
                    $cmd = $rel ? 'l' : 'L';
                    break;
                case 'L':
                    $x = $num() + ($rel ? $x : 0);
                    $y = $num() + ($rel ? $y : 0);
                    self::lineTo($points, $x, $y);
                    break;
                case 'H':
                    $x = $num() + ($rel ? $x : 0);
                    self::lineTo($points, $x, $y);
                    break;
                case 'V':
                    $y = $num() + ($rel ? $y : 0);
                    self::lineTo($points, $x, $y);
                    break;
                case 'C':
                case 'S':
                    if ($upper === 'C') {
                        $x1 = $num() + ($rel ? $x : 0);
                        $y1 = $num() + ($rel ? $y : 0);
                    } elseif ($prev === 'C' || $prev === 'S') {
                        $x1 = 2 * $x - $cx;
                        $y1 = 2 * $y - $cy;
                    } else {
                        $x1 = $x;
                        $y1 = $y;
                    }
                    $x2 = $num() + ($rel ? $x : 0);
                    $y2 = $num() + ($rel ? $y : 0);
                    $ex = $num() + ($rel ? $x : 0);
                    $ey = $num() + ($rel ? $y : 0);
                    self::lineTo($points, $x, $y);
                    self::cubic($points, $x, $y, $x1, $y1, $x2, $y2, $ex, $ey, $tolerance);
                    $cx = $x2;
                    $cy = $y2;
                    $x = $ex;
                    $y = $ey;
                    break;
                case 'Q':
                case 'T':
                    if ($upper === 'Q') {
                        $qx = $num() + ($rel ? $x : 0);
                        $qy = $num() + ($rel ? $y : 0);
                    } elseif ($prev === 'Q' || $prev === 'T') {
                        $qx = 2 * $x - $cx;
                        $qy = 2 * $y - $cy;
                    } else {
                        $qx = $x;
                        $qy = $y;
                    }
                    $ex = $num() + ($rel ? $x : 0);
                    $ey = $num() + ($rel ? $y : 0);
                    self::lineTo($points, $x, $y);
                    self::cubic(
                        $points, $x, $y,
                        $x + 2 / 3 * ($qx - $x), $y + 2 / 3 * ($qy - $y),
                        $ex + 2 / 3 * ($qx - $ex), $ey + 2 / 3 * ($qy - $ey),
                        $ex, $ey, $tolerance
                    );
                    $cx = $qx;
                    $cy = $qy;
                    $x = $ex;
                    $y = $ey;
                    break;
                case 'A':
                    $rx = abs($num());
                    $ry = abs($num());
                    $rot = $num();
                    $large = $flag();
                    $sweep = $flag();
                    $ex = $num() + ($rel ? $x : 0);
                    $ey = $num() + ($rel ? $y : 0);
                    self::lineTo($points, $x, $y);
                    self::arc($points, $x, $y, $rx, $ry, $rot, $large, $sweep, $ex, $ey, $tolerance);
                    $x = $ex;
                    $y = $ey;
                    break;
                case 'Z':
                    $closed = true;
                    $x = $sx;
                    $y = $sy;
                    $flush();
                    // A command after Z without a moveto starts at the start point of the subpath.
                    $points = [$x, $y];
                    break;
                default:
                    throw new RenderException('The path command "'.$cmd.'" is not supported');
            }
            $prev = $upper;
        }
        $flush();

        return $subpaths;
    }

    /**
     * Get the outline of a rectangle with optional rounded corners.
     *
     * @param  float  $x
     * @param  float  $y
     * @param  float  $w
     * @param  float  $h
     * @param  float  $rx
     * @param  float  $ry
     * @param  float  $tolerance
     * @return array<int, array{points: float[], closed: bool}>
     */
    public static function rect(float $x, float $y, float $w, float $h, float $rx, float $ry, float $tolerance): array
    {
        if ($w <= 0 || $h <= 0) {
            return [];
        }
        $rx = min($rx, $w / 2);
        $ry = min($ry, $h / 2);
        if ($rx <= 0 || $ry <= 0) {
            return [['points' => [$x, $y, $x + $w, $y, $x + $w, $y + $h, $x, $y + $h], 'closed' => true]];
        }
        $d = sprintf(
            'M%F,%F H%F A%F,%F 0 0 1 %F,%F V%F A%F,%F 0 0 1 %F,%F H%F A%F,%F 0 0 1 %F,%F V%F A%F,%F 0 0 1 %F,%F Z',
            $x + $rx, $y, $x + $w - $rx,
            $rx, $ry, $x + $w, $y + $ry, $y + $h - $ry,
            $rx, $ry, $x + $w - $rx, $y + $h, $x + $rx,
            $rx, $ry, $x, $y + $h - $ry, $y + $ry,
            $rx, $ry, $x + $rx, $y
        );

        return self::fromData($d, $tolerance);
    }

    /**
     * Get the outline of an ellipse.
     *
     * @param  float  $cx
     * @param  float  $cy
     * @param  float  $rx
     * @param  float  $ry
     * @param  float  $tolerance
     * @return array<int, array{points: float[], closed: bool}>
     */
    public static function ellipse(float $cx, float $cy, float $rx, float $ry, float $tolerance): array
    {
        if ($rx <= 0 || $ry <= 0) {
            return [];
        }
        $points = [];
        $steps = self::arcSteps(2 * M_PI, max($rx, $ry), $tolerance);
        for ($k = 0; $k < $steps; $k++) {
            $t = 2 * M_PI * $k / $steps;
            $points[] = $cx + $rx * cos($t);
            $points[] = $cy + $ry * sin($t);
        }

        return [['points' => $points, 'closed' => true]];
    }

    /**
     * Read the points of a polygon or a polyline.
     *
     * @param  string  $list
     * @param  bool  $closed
     * @return array<int, array{points: float[], closed: bool}>
     */
    public static function polygon(string $list, bool $closed): array
    {
        preg_match_all('/[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/', $list, $m);
        $points = array_map('floatval', $m[0]);
        if (count($points) % 2 === 1) {
            array_pop($points);
        }

        return count($points) >= 4 ? [['points' => $points, 'closed' => $closed]] : [];
    }

    /**
     * Gives the number of segments that keep an arc of this radius in the tolerance.
     *
     * @param  float  $sweep
     * @param  float  $radius
     * @param  float  $tolerance
     * @return int
     */
    public static function arcSteps(float $sweep, float $radius, float $tolerance): int
    {
        if ($radius <= $tolerance) {
            return max(3, (int) ceil(abs($sweep) / M_PI * 2));
        }
        $step = 2 * acos(1 - $tolerance / $radius);

        return max(3, min(1000, (int) ceil(abs($sweep) / $step)));
    }

    /**
     * Add a point, unless it is equal to the last point.
     *
     * @param  float[]  $points
     * @param  float  $x
     * @param  float  $y
     * @return void
     */
    private static function lineTo(array &$points, float $x, float $y): void
    {
        $n = count($points);
        if ($n >= 2 && $points[$n - 2] === $x && $points[$n - 1] === $y) {
            return;
        }
        $points[] = $x;
        $points[] = $y;
    }

    /**
     * Add the points of a cubic Bézier curve.
     *
     * @param  float[]  $points
     * @param  float  $x0
     * @param  float  $y0
     * @param  float  $x1
     * @param  float  $y1
     * @param  float  $x2
     * @param  float  $y2
     * @param  float  $x3
     * @param  float  $y3
     * @param  float  $tolerance
     * @return void
     */
    private static function cubic(array &$points, float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, float $x3, float $y3, float $tolerance): void
    {
        $len = hypot($x1 - $x0, $y1 - $y0) + hypot($x2 - $x1, $y2 - $y1) + hypot($x3 - $x2, $y3 - $y2);
        $steps = max(1, min(200, (int) ceil(sqrt($len * 0.4 / $tolerance))));
        for ($k = 1; $k <= $steps; $k++) {
            $t = $k / $steps;
            $u = 1 - $t;
            $a = $u * $u * $u;
            $b = 3 * $u * $u * $t;
            $c = 3 * $u * $t * $t;
            $e = $t * $t * $t;
            self::lineTo($points, $a * $x0 + $b * $x1 + $c * $x2 + $e * $x3, $a * $y0 + $b * $y1 + $c * $y2 + $e * $y3);
        }
    }

    /**
     * Adds an elliptical arc. Changes the endpoint parameters to centre
     * parameters (SVG 1.1, appendix F.6.5).
     *
     * @param  float[]  $points
     * @param  float  $x1
     * @param  float  $y1
     * @param  float  $rx
     * @param  float  $ry
     * @param  float  $rotation
     * @param  bool  $large
     * @param  bool  $sweep
     * @param  float  $x2
     * @param  float  $y2
     * @param  float  $tolerance
     * @return void
     */
    private static function arc(array &$points, float $x1, float $y1, float $rx, float $ry, float $rotation, bool $large, bool $sweep, float $x2, float $y2, float $tolerance): void
    {
        if ($x1 == $x2 && $y1 == $y2) {
            return;
        }
        if ($rx == 0 || $ry == 0) {
            self::lineTo($points, $x2, $y2);

            return;
        }
        $phi = deg2rad($rotation);
        $cos = cos($phi);
        $sin = sin($phi);
        $dx = ($x1 - $x2) / 2;
        $dy = ($y1 - $y2) / 2;
        $xp = $cos * $dx + $sin * $dy;
        $yp = -$sin * $dx + $cos * $dy;

        $lambda = ($xp * $xp) / ($rx * $rx) + ($yp * $yp) / ($ry * $ry);
        if ($lambda > 1) {
            $rx *= sqrt($lambda);
            $ry *= sqrt($lambda);
        }
        $num = $rx * $rx * $ry * $ry - $rx * $rx * $yp * $yp - $ry * $ry * $xp * $xp;
        $den = $rx * $rx * $yp * $yp + $ry * $ry * $xp * $xp;
        $coef = ($den == 0) ? 0.0 : sqrt(max(0.0, $num / $den));
        if ($large === $sweep) {
            $coef = -$coef;
        }
        $cxp = $coef * $rx * $yp / $ry;
        $cyp = -$coef * $ry * $xp / $rx;
        $cx = $cos * $cxp - $sin * $cyp + ($x1 + $x2) / 2;
        $cy = $sin * $cxp + $cos * $cyp + ($y1 + $y2) / 2;

        $theta1 = atan2(($yp - $cyp) / $ry, ($xp - $cxp) / $rx);
        $theta2 = atan2((-$yp - $cyp) / $ry, (-$xp - $cxp) / $rx);
        $delta = $theta2 - $theta1;
        if ($sweep && $delta < 0) {
            $delta += 2 * M_PI;
        } elseif (! $sweep && $delta > 0) {
            $delta -= 2 * M_PI;
        }

        $steps = self::arcSteps($delta, max($rx, $ry), $tolerance);
        for ($k = 1; $k <= $steps; $k++) {
            if ($k === $steps) {
                self::lineTo($points, $x2, $y2);
                break;
            }
            $t = $theta1 + $delta * $k / $steps;
            $ex = $rx * cos($t);
            $ey = $ry * sin($t);
            self::lineTo($points, $cos * $ex - $sin * $ey + $cx, $sin * $ex + $cos * $ey + $cy);
        }
    }
}

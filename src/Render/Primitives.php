<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

use Wnikk\QrCodeCute\Svg\Num;
use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * The parts that the shape renderers use: module shapes, finder eyes, and the
 * functions that merge adjacent modules into rounded shapes or capsules.
 *
 * @internal
 */
final class Primitives
{
    /**
     * Each finder eye is one path: the ring and the centre, with holes by the
     * evenodd rule. Thus one fill sets its colour. The path is in a 52x52 box
     * that becomes the 7-module area of the eye.
     *
     * @var array<string, string>
     */
    private const EYE_PATH_D = [
        'circle' => 'M26 0C40.3594 0 52 11.6406 52 26C52 40.3594 40.3594 52 26 52C11.6406 52 0 40.3594 0 26C0 11.6406 11.6406 0 26 0ZM26 7C15.5066 7 7 15.5066 7 26C7 36.4934 15.5066 45 26 45C36.4934 45 45 36.4934 45 26C45 15.5066 36.4934 7 26 7Z'
            .'M26 15C32.0751 15 37 19.9249 37 26C37 32.0751 32.0751 37 26 37C19.9249 37 15 32.0751 15 26C15 19.9249 19.9249 15 26 15Z',
        'rounded' => 'M33 0C43.4934 0 52 8.50659 52 19V33C52 43.4934 43.4934 52 33 52H19C8.50659 52 0 43.4934 0 33V19C0 8.50659 8.50659 0 19 0H33ZM19 7C12.3726 7 7 12.3726 7 19V33C7 39.6274 12.3726 45 19 45H33C39.6274 45 45 39.6274 45 33V19C45 12.3726 39.6274 7 33 7H19Z'
            .'M33 15C35.2091 15 37 16.7909 37 19V33C37 35.2091 35.2091 37 33 37H19C16.7909 37 15 35.2091 15 33V19C15 16.7909 16.7909 15 19 15H33Z',
        'square' => 'M45 0C48.866 0 52 3.13401 52 7V45C52 48.866 48.866 52 45 52H7C3.13401 52 1.93283e-07 48.866 0 45V7C0 3.13401 3.13401 1.93277e-07 7 0H45ZM7 45H45V7H7V45Z'
            .'M37 37H15V15H37V37Z',
    ];

    /**
     * Gives true in the 8x8 area around each of the three finder patterns.
     *
     * @param  int  $row
     * @param  int  $col
     * @param  int  $n
     * @return bool
     */
    private static function isFinderZone(int $row, int $col, int $n): bool
    {
        return ($row < 8 && $col < 8)
            || ($row < 8 && $col >= $n - 8)
            || ($row >= $n - 8 && $col < 8);
    }

    /**
     * Make a root <svg> element with a view box.
     *
     * @param  float  $width
     * @param  float  $height
     * @return SvgNode
     */
    public static function svg(float $width, float $height): SvgNode
    {
        $svg = new SvgNode('svg');
        $svg->setAttribute('viewBox', '0 0 '.Num::fmt($width).' '.Num::fmt($height));
        $svg->setAttribute('shape-rendering', 'geometricPrecision');

        return $svg;
    }

    /**
     * Get the radius of a decorative cell. The radius changes a little from cell to cell.
     *
     * @param  Context  $ctx
     * @param  int  $gi
     * @param  int  $gj
     * @return float
     */
    public static function fillRadius(Context $ctx, int $gi, int $gj): float
    {
        if ($ctx->pattern === 'star') {
            return $ctx->rand($gi, $gj) < 0.5 ? 4.0 : 2.8;
        }

        return max(4.0 + ($ctx->rand($gj * 7, $gi * 11) - 0.5) * 0.5, 3.2);
    }

    /* ── Module shapes ─────────────────────────────────────────── */

    /**
     * Draw one module in the shape of a pattern.
     *
     * @param  SvgNode  $parent
     * @param  float  $cx
     * @param  float  $cy
     * @param  float  $r
     * @param  string  $shape
     * @return void
     */
    private static function drawModule(SvgNode $parent, float $cx, float $cy, float $r, string $shape): void
    {
        $f1 = static fn (float $v): string => Num::fixed($v, 1);
        $f2 = static fn (float $v): string => Num::fixed($v, 2);

        switch ($shape) {
            case 'small':
                // Small squares with gaps between the cells.
                $side = $r * 1.85;
                $el = new SvgNode('rect');
                $el->setAttribute('x', $f2($cx - $side / 2));
                $el->setAttribute('y', $f2($cy - $side / 2));
                $el->setAttribute('width', $f2($side));
                $el->setAttribute('height', $f2($side));
                break;

            case 'square':
                $half = Context::MODULE / 2;
                $el = new SvgNode('rect');
                $el->setAttribute('x', $f2($cx - $half));
                $el->setAttribute('y', $f2($cy - $half));
                $el->setAttribute('width', Context::MODULE);
                $el->setAttribute('height', Context::MODULE);
                break;

            case 'stars':
                // A star with five points. The ratio of outer radius to inner radius is 1 : 0.4.
                $outer = $r * 1.45;
                $inner = $outer * 0.4;
                $pts = [];
                for ($i = 0; $i < 10; $i++) {
                    $rad = ($i % 2 === 0) ? $outer : $inner;
                    $ang = -M_PI / 2 + $i * M_PI / 5;
                    $pts[] = $f2($cx + cos($ang) * $rad).','.$f2($cy + sin($ang) * $rad);
                }
                $el = new SvgNode('polygon');
                $el->setAttribute('points', implode(' ', $pts));
                break;

            case 'hearts':
                $hr = $r * 1.3;
                $d = 'M '.$f2($cx).','.$f2($cy + $hr * 0.55)
                    .' C '.$f2($cx - $hr * 1.4).','.$f2($cy - $hr * 0.25)
                    .' '.$f2($cx - $hr * 0.45).','.$f2($cy - $hr * 1.05)
                    .' '.$f2($cx).','.$f2($cy - $hr * 0.3)
                    .' C '.$f2($cx + $hr * 0.45).','.$f2($cy - $hr * 1.05)
                    .' '.$f2($cx + $hr * 1.4).','.$f2($cy - $hr * 0.25)
                    .' '.$f2($cx).','.$f2($cy + $hr * 0.55).' Z';
                $el = new SvgNode('path');
                $el->setAttribute('d', $d);
                break;

            case 'triangles':
                // An equilateral triangle that points up.
                $tx = $r * 1.25;
                $ty = $r * 0.72;
                $el = new SvgNode('polygon');
                $el->setAttribute('points',
                    $f2($cx).','.$f2($cy - $r * 1.44).' '
                    .$f2($cx + $tx).','.$f2($cy + $ty).' '
                    .$f2($cx - $tx).','.$f2($cy + $ty));
                break;

            case 'diamonds':
                $dr = $r * 1.35;
                $el = new SvgNode('polygon');
                $el->setAttribute('points',
                    $f2($cx).','.$f2($cy - $dr).' '
                    .$f2($cx + $dr).','.$f2($cy).' '
                    .$f2($cx).','.$f2($cy + $dr).' '
                    .$f2($cx - $dr).','.$f2($cy));
                break;

            default:
                // 'dots', 'star' and all other round shapes.
                $el = new SvgNode('circle');
                $el->setAttribute('cx', $f1($cx));
                $el->setAttribute('cy', $f1($cy));
                $el->setAttribute('r', $f2($r));
        }
        $parent->appendChild($el);
    }

    /**
     * Draw the data modules of the matrix with the current pattern.
     * Does not draw the finder areas. The matrix origin is at (x0, y0).
     *
     * @param  Context  $ctx
     * @param  SvgNode  $parent
     * @param  bool[][]  $matrix
     * @param  float  $x0
     * @param  float  $y0
     * @return void
     */
    public static function drawData(Context $ctx, SvgNode $parent, array $matrix, float $x0, float $y0): void
    {
        $n = count($matrix);
        $m = Context::MODULE;
        $cells = [];
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $dark) {
                if (! $dark || self::isFinderZone($r, $c, $n)) {
                    continue;
                }
                $cells[] = [
                    'gi' => $r,
                    'gj' => $c,
                    'pcx' => $x0 + $c * $m + $m / 2,
                    'pcy' => $y0 + $r * $m + $m / 2,
                    'dotR' => ($ctx->pattern === 'star' && $ctx->rand($r, $c) >= 0.5) ? 2.8 : 4.0,
                ];
            }
        }

        $g = new SvgNode('g');
        $g->setAttribute('fill', $ctx->color);
        self::drawCells($ctx, $g, $cells);
        $parent->appendChild($g);
    }

    /**
     * Draw a list of cells with the current pattern. The parent sets the fill colour.
     *
     * The 'rounded', pill and bar patterns merge adjacent cells into one shape.
     *
     * @param  Context  $ctx
     * @param  SvgNode  $parent
     * @param  array<int, array{gi: int, gj: int, pcx: float, pcy: float, dotR: float}>  $cells
     * @return void
     */
    public static function drawCells(Context $ctx, SvgNode $parent, array $cells): void
    {
        if ($ctx->isPillLike()) {
            self::drawPills($parent, $cells, $ctx->pillMode(), $ctx->pillSharp());

            return;
        }

        if ($ctx->pattern === 'rounded') {
            $dark = [];
            foreach ($cells as $c) {
                $dark[$c['gi']][$c['gj']] = true;
            }
            $isDark = static fn (int $i, int $j): bool => isset($dark[$i][$j]);
            $half = Context::MODULE / 2;
            foreach ($cells as $c) {
                $path = new SvgNode('path');
                $path->setAttribute('d', self::groupedCellPath($isDark, $c['gi'], $c['gj'], $c['pcx'] - $half, $c['pcy'] - $half));
                $parent->appendChild($path);
            }

            return;
        }

        foreach ($cells as $c) {
            self::drawModule($parent, $c['pcx'], $c['pcy'], $c['dotR'], $ctx->pattern);
        }
    }

    /* ── Finder eyes ───────────────────────────────────────────── */

    /**
     * Applies a scale and a translation to a path with absolute coordinates.
     * Only the commands M, L, C, H, V and Z are possible. The eye paths use only these.
     *
     * @param  string  $d
     * @param  float  $s
     * @param  float  $tx
     * @param  float  $ty
     * @return string
     */
    private static function transformPathData(string $d, float $s, float $tx, float $ty): string
    {
        return (string) preg_replace_callback(
            '/([MLCHVZ])([^MLCHVZ]*)/i',
            static function (array $match) use ($s, $tx, $ty): string {
                $cmd = $match[1];
                $upper = strtoupper($cmd);
                if ($upper === 'Z') {
                    return $cmd;
                }
                preg_match_all('/-?\d*\.?\d+(?:[eE][-+]?\d+)?/', $match[2], $nums);
                $out = [];
                foreach ($nums[0] as $i => $num) {
                    $v = (float) $num * $s;
                    if ($upper === 'H') {
                        $v += $tx;
                    } elseif ($upper === 'V') {
                        $v += $ty;
                    } else {
                        $v += ($i % 2 === 0) ? $tx : $ty;
                    }
                    $out[] = Num::fmt(floor($v * 1000 + 0.5) / 1000);
                }

                return $cmd.implode(' ', $out);
            },
            $d
        );
    }

    /**
     * Gives one finder eye with its centre at (ecx, ecy) and the outer radius eR.
     *
     * @param  Context  $ctx
     * @param  float  $ecx
     * @param  float  $ecy
     * @param  float  $eR
     * @param  string  $color
     * @return SvgNode
     */
    private static function finderEye(Context $ctx, float $ecx, float $ecy, float $eR, string $color): SvgNode
    {
        $shape = isset(self::EYE_PATH_D[$ctx->eyeShape]) ? $ctx->eyeShape : 'circle';
        $s = ($eR * 2) / 52;
        $p = new SvgNode('path');
        $p->setAttribute('d', self::transformPathData(self::EYE_PATH_D[$shape], $s, $ecx - $eR, $ecy - $eR));
        $p->setAttribute('fill-rule', 'evenodd');
        $p->setAttribute('fill', $color);

        return $p;
    }

    /**
     * Draws the three finder eyes of an n-module matrix with its origin at (x0, y0).
     *
     * @param  Context  $ctx
     * @param  SvgNode  $parent
     * @param  int  $n
     * @param  float  $x0
     * @param  float  $y0
     * @return void
     */
    public static function drawFinderEyes(Context $ctx, SvgNode $parent, int $n, float $x0, float $y0): void
    {
        $m = Context::MODULE;
        $eR = 3.5 * $m;
        $eyes = [
            [$x0 + 3.5 * $m, $y0 + 3.5 * $m],
            [$x0 + ($n - 3.5) * $m, $y0 + 3.5 * $m],
            [$x0 + 3.5 * $m, $y0 + ($n - 3.5) * $m],
        ];
        $g = new SvgNode('g');
        foreach ($eyes as [$ecx, $ecy]) {
            $g->appendChild(self::finderEye($ctx, $ecx, $ecy, $eR, $ctx->eyeFill()));
        }
        $parent->appendChild($g);
    }

    /* ── Grouped 'rounded' pattern ─────────────────────────────── */

    /**
     * Gives a rectangle path with a different radius on each corner.
     *
     * @param  float  $x
     * @param  float  $y
     * @param  float  $w
     * @param  float  $h
     * @param  float  $rTL
     * @param  float  $rTR
     * @param  float  $rBR
     * @param  float  $rBL
     * @return string
     */
    private static function roundedRectPath(float $x, float $y, float $w, float $h, float $rTL, float $rTR, float $rBR, float $rBL): string
    {
        $f = static fn (float $v): string => Num::fixed($v, 1);
        $p = 'M'.$f($x + $rTL).','.$f($y);
        $p .= ' L'.$f($x + $w - $rTR).','.$f($y);
        if ($rTR > 0) {
            $p .= ' Q'.$f($x + $w).','.$f($y).' '.$f($x + $w).','.$f($y + $rTR);
        }
        $p .= ' L'.$f($x + $w).','.$f($y + $h - $rBR);
        if ($rBR > 0) {
            $p .= ' Q'.$f($x + $w).','.$f($y + $h).' '.$f($x + $w - $rBR).','.$f($y + $h);
        }
        $p .= ' L'.$f($x + $rBL).','.$f($y + $h);
        if ($rBL > 0) {
            $p .= ' Q'.$f($x).','.$f($y + $h).' '.$f($x).','.$f($y + $h - $rBL);
        }
        $p .= ' L'.$f($x).','.$f($y + $rTL);
        if ($rTL > 0) {
            $p .= ' Q'.$f($x).','.$f($y).' '.$f($x + $rTL).','.$f($y);
        }

        return $p.' Z ';
    }

    /**
     * Gives a fillet for a concave corner of a group. The fillet is a small
     * wedge with a curved side in the empty diagonal cell. Thus the outline
     * looks like one smooth shape.
     *
     * @param  float  $cx
     * @param  float  $cy
     * @param  float  $r
     * @param  string  $quadrant
     * @return string
     */
    private static function concaveFillet(float $cx, float $cy, float $r, string $quadrant): string
    {
        switch ($quadrant) {
            case 'BR':
                [$p1x, $p1y, $p2x, $p2y] = [$cx + $r, $cy, $cx, $cy + $r];
                break;
            case 'BL':
                [$p1x, $p1y, $p2x, $p2y] = [$cx, $cy + $r, $cx - $r, $cy];
                break;
            case 'TR':
                [$p1x, $p1y, $p2x, $p2y] = [$cx, $cy - $r, $cx + $r, $cy];
                break;
            default: // TL
                [$p1x, $p1y, $p2x, $p2y] = [$cx - $r, $cy, $cx, $cy - $r];
        }
        $f = static fn (float $v): string => Num::fixed($v, 1);

        return 'M'.$f($cx).','.$f($cy)
            .' L'.$f($p1x).','.$f($p1y)
            .' A'.Num::fmt($r).','.Num::fmt($r).' 0 0,0 '.$f($p2x).','.$f($p2y)
            .' Z ';
    }

    /**
     * Get the path for one cell of a grouped shape. A corner without a dark
     * neighbour is rounded. A concave corner gets a fillet.
     *
     * @param  callable(int, int): bool  $isDark
     * @param  int  $i
     * @param  int  $j
     * @param  float  $x
     * @param  float  $y
     * @return string
     */
    private static function groupedCellPath(callable $isDark, int $i, int $j, float $x, float $y): string
    {
        $m = Context::MODULE;
        $r = $m * 0.4;
        $top = $isDark($i - 1, $j);
        $bottom = $isDark($i + 1, $j);
        $left = $isDark($i, $j - 1);
        $right = $isDark($i, $j + 1);

        $d = self::roundedRectPath(
            $x, $y, $m, $m,
            (! $top && ! $left) ? $r : 0,
            (! $top && ! $right) ? $r : 0,
            (! $bottom && ! $right) ? $r : 0,
            (! $bottom && ! $left) ? $r : 0
        );

        // Only one cell draws each concave corner.
        if ($right && $bottom && ! $isDark($i + 1, $j + 1)) {
            $d .= self::concaveFillet($x + $m, $y + $m, $r, 'BR');
        }
        if ($left && $bottom && ! $isDark($i + 1, $j - 1)) {
            $d .= self::concaveFillet($x, $y + $m, $r, 'BL');
        }
        if ($right && $top && ! $isDark($i - 1, $j + 1)) {
            $d .= self::concaveFillet($x + $m, $y, $r, 'TR');
        }
        if ($left && $top && ! $isDark($i - 1, $j - 1)) {
            $d .= self::concaveFillet($x, $y, $r, 'TL');
        }

        return $d;
    }

    /* ── Pill and bar patterns ─────────────────────────────────── */

    /**
     * Draw pill or bar shapes for a list of cells. Adjacent cells in the merge
     * direction become one capsule. A single cell becomes a dot or a small square.
     *
     * @param  SvgNode  $parent
     * @param  array<int, array{gi: int, gj: int, pcx: float, pcy: float, dotR: float}>  $cells
     * @param  string  $mode  'h' or 'v'.
     * @param  bool  $sharp  Set to true for square corners.
     * @return void
     */
    private static function drawPills(SvgNode $parent, array $cells, string $mode, bool $sharp): void
    {
        $m = Context::MODULE;
        $r = $m * 0.4;
        $corner = $sharp ? 0 : $r;
        $along = $mode === 'h' ? 'gj' : 'gi';
        $across = $mode === 'h' ? 'gi' : 'gj';

        $groups = [];
        foreach ($cells as $c) {
            $groups[(string) $c[$across]][] = $c;
        }

        foreach ($groups as $arr) {
            usort($arr, static fn (array $a, array $b): int => $a[$along] <=> $b[$along]);
            $count = count($arr);
            $i = 0;
            while ($i < $count) {
                $start = $i;
                while ($i + 1 < $count && $arr[$i + 1][$along] - $arr[$i][$along] == 1) {
                    $i++;
                }
                $first = $arr[$start];
                $last = $arr[$i];

                if ($i === $start) {
                    if ($sharp) {
                        $el = new SvgNode('rect');
                        $el->setAttribute('x', $first['pcx'] - $r);
                        $el->setAttribute('y', $first['pcy'] - $r);
                        $el->setAttribute('width', $r * 2);
                        $el->setAttribute('height', $r * 2);
                    } else {
                        $el = new SvgNode('circle');
                        $el->setAttribute('cx', $first['pcx']);
                        $el->setAttribute('cy', $first['pcy']);
                        $el->setAttribute('r', $r);
                    }
                } else {
                    $el = new SvgNode('rect');
                    if ($mode === 'h') {
                        $x0 = $first['pcx'] - $m / 2;
                        $x1 = $last['pcx'] + $m / 2;
                        $el->setAttribute('x', $x0);
                        $el->setAttribute('y', $first['pcy'] - $r);
                        $el->setAttribute('width', $x1 - $x0);
                        $el->setAttribute('height', $r * 2);
                    } else {
                        $y0 = $first['pcy'] - $m / 2;
                        $y1 = $last['pcy'] + $m / 2;
                        $el->setAttribute('x', $first['pcx'] - $r);
                        $el->setAttribute('y', $y0);
                        $el->setAttribute('width', $r * 2);
                        $el->setAttribute('height', $y1 - $y0);
                    }
                    $el->setAttribute('rx', $corner);
                    $el->setAttribute('ry', $corner);
                }
                $parent->appendChild($el);
                $i++;
            }
        }
    }
}

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
     * Create an element.
     *
     * @param  string  $name
     * @return SvgNode
     */
    public static function el(string $name): SvgNode
    {
        return new SvgNode($name);
    }

    /**
     * Gives true in the 8x8 area around each of the three finder patterns.
     *
     * @param  int  $row
     * @param  int  $col
     * @param  int  $n
     * @return bool
     */
    public static function isFinderZone(int $row, int $col, int $n): bool
    {
        return ($row < 8 && $col < 8)
            || ($row < 8 && $col >= $n - 8)
            || ($row >= $n - 8 && $col < 8);
    }

    /**
     * Gives a copy of the matrix without the finder areas.
     *
     * @param  bool[][]  $matrix
     * @return bool[][]
     */
    public static function maskFinders(array $matrix): array
    {
        $n = count($matrix);
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $v) {
                if ($v && self::isFinderZone($r, $c, $n)) {
                    $matrix[$r][$c] = false;
                }
            }
        }

        return $matrix;
    }

    /**
     * Gives a root <svg> element with an optional rounded background.
     *
     * @param  float  $size
     * @param  string  $bg
     * @param  float  $bgRx
     * @return SvgNode
     */
    public static function makeSvg(float $size, string $bg, float $bgRx): SvgNode
    {
        $svg = self::el('svg');
        $svg->setAttribute('id', 'qr-code');
        $svg->setAttribute('viewBox', '0 0 '.Num::fmt($size).' '.Num::fmt($size));
        $svg->setAttribute('shape-rendering', 'crispEdges');
        if ($bg !== '' && $bg !== 'transparent') {
            $rect = self::el('rect');
            $rect->setAttribute('width', $size);
            $rect->setAttribute('height', $size);
            if ($bgRx) {
                $rect->setAttribute('rx', $bgRx);
                $rect->setAttribute('ry', $bgRx);
            }
            $rect->setAttribute('id', 'Background');
            $rect->setAttribute('fill', $bg);
            $svg->appendChild($rect);
        }

        return $svg;
    }

    /**
     * Gives the module radius for the cell at (row, col).
     *
     * @param  Context  $ctx
     * @param  float  $row
     * @param  float  $col
     * @return float
     */
    public static function dotRadius(Context $ctx, float $row, float $col): float
    {
        if ($ctx->pattern === 'star') {
            return $ctx->rand($row, $col) < 0.5 ? 4.0 : 2.8;
        }

        return 4.0;
    }

    /* ── Module shapes ─────────────────────────────────────────── */

    /**
     * Draw one module in the shape of a pattern.
     *
     * @param  Context  $ctx
     * @param  SvgNode  $parent
     * @param  float  $cx
     * @param  float  $cy
     * @param  float  $r
     * @param  string  $shape
     * @return void
     */
    public static function drawModule(Context $ctx, SvgNode $parent, float $cx, float $cy, float $r, string $shape): void
    {
        $f1 = static fn (float $v): string => Num::fixed($v, 1);
        $f2 = static fn (float $v): string => Num::fixed($v, 2);

        switch ($shape) {
            case 'small':
                // Small squares with gaps between the cells.
                $side = $r * 1.85;
                $el = self::el('rect');
                $el->setAttribute('x', $f2($cx - $side / 2));
                $el->setAttribute('y', $f2($cy - $side / 2));
                $el->setAttribute('width', $f2($side));
                $el->setAttribute('height', $f2($side));
                $el->setAttribute('rx', '0');
                break;

            case 'square':
                $half = $ctx->moduleSize / 2;
                $el = self::el('rect');
                $el->setAttribute('x', $f2($cx - $half));
                $el->setAttribute('y', $f2($cy - $half));
                $el->setAttribute('width', $ctx->moduleSize);
                $el->setAttribute('height', $ctx->moduleSize);
                $el->setAttribute('rx', '0');
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
                $el = self::el('polygon');
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
                $el = self::el('path');
                $el->setAttribute('d', $d);
                break;

            case 'triangles':
                // An equilateral triangle that points up.
                $tx = $r * 1.25;
                $ty = $r * 0.72;
                $el = self::el('polygon');
                $el->setAttribute('points',
                    $f2($cx).','.$f2($cy - $r * 1.44).' '
                    .$f2($cx + $tx).','.$f2($cy + $ty).' '
                    .$f2($cx - $tx).','.$f2($cy + $ty));
                break;

            case 'diamonds':
                $dr = $r * 1.35;
                $el = self::el('polygon');
                $el->setAttribute('points',
                    $f2($cx).','.$f2($cy - $dr).' '
                    .$f2($cx + $dr).','.$f2($cy).' '
                    .$f2($cx).','.$f2($cy + $dr).' '
                    .$f2($cx - $dr).','.$f2($cy));
                break;

            default:
                // 'dots', 'star' and all other round shapes.
                $el = self::el('circle');
                $el->setAttribute('cx', $f1($cx));
                $el->setAttribute('cy', $f1($cy));
                $el->setAttribute('r', $f2($r));
        }
        $parent->appendChild($el);
    }

    /**
     * Draws the data modules of the matrix with the current pattern.
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
        $m = $ctx->moduleSize;

        if ($ctx->isPillLike()) {
            $parent->appendChild(self::renderPills(
                $ctx, self::maskFinders($matrix), $ctx->color, $x0, $y0, $ctx->pillMode(), $ctx->pillSharp()
            ));

            return;
        }
        if ($ctx->pattern === 'rounded') {
            $parent->appendChild(self::renderGroupedQr($matrix, $n, $ctx->color, $x0, $y0, $m));

            return;
        }

        $g = self::el('g');
        $g->setAttribute('fill', $ctx->color);
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if (! $matrix[$r][$c] || self::isFinderZone($r, $c, $n)) {
                    continue;
                }
                $mcx = $x0 + $c * $m + $m / 2;
                $mcy = $y0 + $r * $m + $m / 2;
                self::drawModule($ctx, $g, $mcx, $mcy, self::dotRadius($ctx, $r, $c), $ctx->pattern);
            }
        }
        $parent->appendChild($g);
    }

    /**
     * Draws the decorative cells around a shaped code.
     *
     * @param  Context  $ctx
     * @param  SvgNode  $parent
     * @param  array  $cells
     * @param  array<int, array{gi: int, gj: int, pcx: float, pcy: float, dotR: float}>  $cells
     * @return void
     */
    public static function drawContinuation(Context $ctx, SvgNode $parent, array $cells): void
    {
        $m = $ctx->moduleSize;
        if ($ctx->isPillLike()) {
            self::appendPillContinuation($parent, $cells, $ctx->pillMode(), $m, $ctx->pillSharp());
        } elseif ($ctx->pattern === 'rounded') {
            self::appendGroupedContinuation($parent, $cells, $m);
        } else {
            foreach ($cells as $c) {
                self::drawModule($ctx, $parent, $c['pcx'], $c['pcy'], $c['dotR'], $ctx->pattern);
            }
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
    public static function transformPathData(string $d, float $s, float $tx, float $ty): string
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
    public static function finderEye(Context $ctx, float $ecx, float $ecy, float $eR, string $color): SvgNode
    {
        $shape = isset(self::EYE_PATH_D[$ctx->eyeShape]) ? $ctx->eyeShape : 'circle';
        $s = ($eR * 2) / 52;
        $p = self::el('path');
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
        $m = $ctx->moduleSize;
        $eR = 3.5 * $m;
        $eyes = [
            [$x0 + 3.5 * $m, $y0 + 3.5 * $m],
            [$x0 + ($n - 3.5) * $m, $y0 + 3.5 * $m],
            [$x0 + 3.5 * $m, $y0 + ($n - 3.5) * $m],
        ];
        $g = self::el('g');
        $g->setAttribute('class', 'qr-eyes');
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
    public static function roundedRectPath(float $x, float $y, float $w, float $h, float $rTL, float $rTR, float $rBR, float $rBL): string
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
    public static function concaveFillet(float $cx, float $cy, float $r, string $quadrant): string
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
     * Gives the path for one cell of a grouped shape. A corner without a dark
     * neighbour is rounded. A concave corner gets a fillet.
     *
     * @param  callable  $isDark
     * @param  int|float  $i
     * @param  int|float  $j
     * @param  float  $x
     * @param  float  $y
     * @param  float  $m
     * @param  callable(int|float, int|float): bool  $isDark
     * @return string
     */
    private static function groupedCellPath(callable $isDark, int|float $i, int|float $j, float $x, float $y, float $m): string
    {
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

    /**
     * Gives the matrix as merged rounded shapes, with one path for each cell.
     * Does not draw the finder areas. Another function draws the eyes.
     *
     * @param  bool[][]  $matrix
     * @param  int  $n
     * @param  string  $fg
     * @param  float  $offsetX
     * @param  float  $offsetY
     * @param  float  $m
     * @return SvgNode
     */
    public static function renderGroupedQr(array $matrix, int $n, string $fg, float $offsetX, float $offsetY, float $m): SvgNode
    {
        $isDark = static function ($r, $c) use ($matrix, $n): bool {
            if ($r < 0 || $r >= $n || $c < 0 || $c >= $n) {
                return false;
            }

            return $matrix[$r][$c] && ! self::isFinderZone($r, $c, $n);
        };

        $group = self::el('g');
        $group->setAttribute('fill', $fg);
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if (! $isDark($r, $c)) {
                    continue;
                }
                $path = self::el('path');
                $path->setAttribute('d', self::groupedCellPath($isDark, $r, $c, $offsetX + $c * $m, $offsetY + $r * $m, $m));
                $group->appendChild($path);
            }
        }

        return $group;
    }

    /**
     * Draws grouped 'rounded' shapes for a list of decorative cells.
     *
     * @param  SvgNode  $parent
     * @param  array  $cells
     * @param  float  $m
     * @param  array<int, array{gi: int, gj: int, pcx: float, pcy: float}>  $cells
     * @return void
     */
    public static function appendGroupedContinuation(SvgNode $parent, array $cells, float $m): void
    {
        if (! $cells) {
            return;
        }
        $map = [];
        foreach ($cells as $c) {
            $map[$c['gi'].','.$c['gj']] = true;
        }
        $isDark = static fn ($gi, $gj): bool => isset($map[$gi.','.$gj]);

        $fill = $parent->getAttribute('fill') ?? '#000';
        foreach ($cells as $c) {
            $path = self::el('path');
            $path->setAttribute('d', self::groupedCellPath($isDark, $c['gi'], $c['gj'], $c['pcx'] - $m / 2, $c['pcy'] - $m / 2, $m));
            $path->setAttribute('fill', $fill);
            $parent->appendChild($path);
        }
    }

    /* ── Pill and bar patterns ─────────────────────────────────── */

    /**
     * Draws pill or bar shapes for a list of decorative cells. Adjacent cells
     * in the merge direction become one capsule. A single cell becomes a dot.
     *
     * @param  SvgNode  $parent
     * @param  array  $cells
     * @param  string  $mode
     * @param  float  $m
     * @param  bool  $sharp
     * @param  array<int, array{gi: int, gj: int, pcx: float, pcy: float}>  $cells
     * @return void
     */
    public static function appendPillContinuation(SvgNode $parent, array $cells, string $mode, float $m, bool $sharp): void
    {
        if (! $cells) {
            return;
        }
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
                        $el = self::el('rect');
                        $el->setAttribute('x', $first['pcx'] - $r);
                        $el->setAttribute('y', $first['pcy'] - $r);
                        $el->setAttribute('width', $r * 2);
                        $el->setAttribute('height', $r * 2);
                    } else {
                        $el = self::el('circle');
                        $el->setAttribute('cx', $first['pcx']);
                        $el->setAttribute('cy', $first['pcy']);
                        $el->setAttribute('r', $r);
                    }
                } else {
                    $el = self::el('rect');
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

    /**
     * Gives the matrix as horizontal ('h') or vertical ('v') runs. Each run
     * becomes a capsule or a bar. A single module becomes a dot or a small square.
     *
     * @param  Context  $ctx
     * @param  bool[][]  $matrix
     * @param  string  $fg
     * @param  float  $offsetX
     * @param  float  $offsetY
     * @param  string  $mode
     * @param  bool  $sharp
     * @return SvgNode
     */
    public static function renderPills(Context $ctx, array $matrix, string $fg, float $offsetX, float $offsetY, string $mode, bool $sharp): SvgNode
    {
        $n = count($matrix);
        $m = $ctx->moduleSize;
        $r = $m * 0.4;
        $inset = ($m - $r * 2) / 2;
        $corner = $sharp ? 0 : $r;
        $f = [Num::class, 'fmt'];

        $rectPath = static function (float $x0, float $y0, float $x1, float $y1) use ($corner, $f): string {
            if ($corner == 0) {
                return 'M'.$f($x0).','.$f($y0).' L'.$f($x1).','.$f($y0)
                    .' L'.$f($x1).','.$f($y1).' L'.$f($x0).','.$f($y1).' Z';
            }
            $k = $corner;

            return 'M'.$f($x0 + $k).','.$f($y0)
                .' L'.$f($x1 - $k).','.$f($y0)
                .' Q'.$f($x1).','.$f($y0).' '.$f($x1).','.$f($y0 + $k)
                .' L'.$f($x1).','.$f($y1 - $k)
                .' Q'.$f($x1).','.$f($y1).' '.$f($x1 - $k).','.$f($y1)
                .' L'.$f($x0 + $k).','.$f($y1)
                .' Q'.$f($x0).','.$f($y1).' '.$f($x0).','.$f($y1 - $k)
                .' L'.$f($x0).','.$f($y0 + $k)
                .' Q'.$f($x0).','.$f($y0).' '.$f($x0 + $k).','.$f($y0).' Z';
        };

        $dotPath = static function (int $row, int $col) use ($offsetX, $offsetY, $m, $r, $sharp, $rectPath, $f): string {
            $cx = $offsetX + $col * $m + $m / 2;
            $cy = $offsetY + $row * $m + $m / 2;
            if ($sharp) {
                return $rectPath($cx - $r, $cy - $r, $cx + $r, $cy + $r);
            }

            return 'M'.$f($cx - $r).','.$f($cy)
                .' a'.$f($r).','.$f($r).' 0 1,0 '.$f($r * 2).',0'
                .' a'.$f($r).','.$f($r).' 0 1,0 '.$f(-$r * 2).',0 Z';
        };

        $group = self::el('g');
        $group->setAttribute('fill', $fg);
        $add = static function (string $d) use ($group): void {
            $p = new SvgNode('path');
            $p->setAttribute('d', $d);
            $group->appendChild($p);
        };

        if ($mode === 'h') {
            for ($row = 0; $row < $n; $row++) {
                $c = 0;
                while ($c < $n) {
                    if (! $matrix[$row][$c]) {
                        $c++;

                        continue;
                    }
                    $c0 = $c;
                    while ($c < $n && $matrix[$row][$c]) {
                        $c++;
                    }
                    if ($c - $c0 === 1) {
                        $add($dotPath($row, $c0));
                    } else {
                        $y0 = $offsetY + $row * $m + $inset;
                        $add($rectPath($offsetX + $c0 * $m, $y0, $offsetX + $c * $m, $y0 + $r * 2));
                    }
                }
            }
        } else {
            for ($col = 0; $col < $n; $col++) {
                $row = 0;
                while ($row < $n) {
                    if (! $matrix[$row][$col]) {
                        $row++;

                        continue;
                    }
                    $r0 = $row;
                    while ($row < $n && $matrix[$row][$col]) {
                        $row++;
                    }
                    if ($row - $r0 === 1) {
                        $add($dotPath($r0, $col));
                    } else {
                        $x0 = $offsetX + $col * $m + $inset;
                        $add($rectPath($x0, $offsetY + $r0 * $m, $x0 + $r * 2, $offsetY + $row * $m));
                    }
                }
            }
        }

        return $group;
    }
}

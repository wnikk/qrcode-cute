<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

use Wnikk\QrCodeCute\Svg\Num;
use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Draws a triangular code. The matrix is in the low part of a rounded
 * triangle, where the triangle is widest. Decorative modules fill the remaining area.
 *
 * @internal
 */
final class TriangleRenderer
{
    /**
     * Draw the code.
     *
     * @param  Context  $ctx  The drawing settings.
     * @param  bool[][]  $matrix
     * @return SvgNode
     */
    public static function render(Context $ctx, array $matrix): SvgNode
    {
        $n = count($matrix);
        $m = $ctx->moduleSize = 10;

        $qrW = $n * $m;
        $triBase = ceil($qrW * 2.6);
        $triHeight = ceil($triBase * sqrt(3) / 2);

        $shellPad = $m * 2;
        $marginX = $m * 0.5 + $shellPad;
        $marginYTop = $m * 0.5 + $shellPad;
        // A larger bottom margin balances the wide base of the triangle.
        $marginYBot = $marginYTop + $triHeight * 0.18;

        $totalW = $triBase + $marginX * 2;
        $totalH = $triHeight + $marginYTop + $marginYBot;

        $apex = ['x' => $totalW / 2, 'y' => $marginYTop];
        $bottomLeft = ['x' => $marginX, 'y' => $marginYTop + $triHeight];
        $bottomRight = ['x' => $totalW - $marginX, 'y' => $marginYTop + $triHeight];

        // The matrix is in the horizontal centre, two modules above the base.
        $qrX0 = $apex['x'] - $qrW / 2;
        $qrY0 = $bottomRight['y'] - $qrW - $m * 2;
        $safeX0 = $qrX0 - $m * 0.5;
        $safeY0 = $qrY0 - $m * 0.5;
        $safeX1 = $qrX0 + $qrW + $m * 0.5;
        $safeY1 = $qrY0 + $qrW + $m * 0.5;

        $centroidX = ($apex['x'] + $bottomLeft['x'] + $bottomRight['x']) / 3;
        $centroidY = ($apex['y'] + $bottomLeft['y'] + $bottomRight['y']) / 3;

        $svg = Primitives::el('svg');
        $svg->setAttribute('id', 'qr-code');
        $svg->setAttribute('viewBox', '0 0 '.Num::fmt($totalW).' '.Num::fmt($totalH));
        $svg->setAttribute('shape-rendering', 'geometricPrecision');

        // The boundary for the fit check of each module: the outer triangle with
        // rounded corners. The vertices go clockwise: apex, bottom-right, bottom-left.
        $boundCornerR = $triBase * 0.07;
        $verts = [$apex, $bottomRight, $bottomLeft];
        $edges = [];
        foreach ($verts as $i => $v1) {
            $v2 = $verts[($i + 1) % 3];
            $ex = $v2['x'] - $v1['x'];
            $ey = $v2['y'] - $v1['y'];
            $len = sqrt($ex * $ex + $ey * $ey);
            $edges[] = ['v' => $v1, 'nx' => -$ey / $len, 'ny' => $ex / $len];
        }
        $arcInset = $boundCornerR * sqrt(3);
        $arcs = [];
        foreach ($verts as $i => $v) {
            $tp = self::unit($v, $verts[($i + 2) % 3]);
            $tn = self::unit($v, $verts[($i + 1) % 3]);
            $bx = ($tp['x'] + $tn['x']) / 2;
            $by = ($tp['y'] + $tn['y']) / 2;
            $bl = sqrt($bx * $bx + $by * $by);
            $arcs[] = [
                'v' => $v, 'tp' => $tp, 'tn' => $tn,
                'cx' => $v['x'] + $bx / $bl * 2 * $boundCornerR,
                'cy' => $v['y'] + $by / $bl * 2 * $boundCornerR,
            ];
        }
        $fits = static function (float $x, float $y, float $r) use ($edges, $arcs, $arcInset, $boundCornerR): bool {
            foreach ($edges as $e) {
                if (($x - $e['v']['x']) * $e['nx'] + ($y - $e['v']['y']) * $e['ny'] < $r) {
                    return false;
                }
            }
            foreach ($arcs as $a) {
                $dvx = $x - $a['v']['x'];
                $dvy = $y - $a['v']['y'];
                $projP = $dvx * $a['tp']['x'] + $dvy * $a['tp']['y'];
                $projN = $dvx * $a['tn']['x'] + $dvy * $a['tn']['y'];
                if ($projP > 0 && $projP < $arcInset && $projN > 0 && $projN < $arcInset
                    && sqrt(($x - $a['cx']) ** 2 + ($y - $a['cy']) ** 2) > $boundCornerR - $r) {
                    return false;
                }
            }

            return true;
        };

        // The background shell: the triangle, two modules larger.
        $bgPad = $m * 2;
        $grow = static function (array $v) use ($centroidX, $centroidY, $bgPad): array {
            $dx = $centroidX - $v['x'];
            $dy = $centroidY - $v['y'];
            $len = sqrt($dx * $dx + $dy * $dy);

            return ['x' => $v['x'] - $dx / $len * $bgPad, 'y' => $v['y'] - $dy / $len * $bgPad];
        };
        $bgTri = Primitives::el('path');
        $bgTri->setAttribute('d', self::roundedTrianglePath(
            $grow($apex), $grow($bottomLeft), $grow($bottomRight), $m * 4.5 + $bgPad
        ));
        $bgTri->setAttribute('fill', $ctx->background);
        $svg->appendChild($bgTri);
        $svg->meta['bgShell'] = $bgTri;

        $contG = Primitives::el('g');
        $contG->setAttribute('fill', $ctx->color);

        $gridStartX = floor(($marginX - $m * 3) / $m) * $m + fmod($qrX0, $m);
        $gridEndX = $totalW - $marginX + $m * 3;
        $gridStartY = floor(($marginYTop - $m * 3) / $m) * $m + fmod($qrY0, $m);
        $gridEndY = $totalH - $marginYBot + $m * 3;

        $cells = self::scatter($ctx, $gridStartX, $gridEndX, $gridStartY, $gridEndY,
            [$safeX0, $safeY0, $safeX1, $safeY1], $fits);

        Primitives::drawContinuation($ctx, $contG, $cells);
        $svg->appendChild($contG);

        Primitives::drawData($ctx, $svg, $matrix, $qrX0, $qrY0);
        Primitives::drawFinderEyes($ctx, $svg, $n, $qrX0, $qrY0);

        // The boundary triangle that a frame scales into its window. The random
        // fill does not change it. Thus the gap to the ring is always equal.
        $svg->meta['triGeom'] = [
            'apexY' => $apex['y'],
            'baseY' => $bottomRight['y'],
            'centroidX' => $centroidX,
            'centroidY' => $centroidY,
        ];

        return $svg;
    }

    /**
     * Finds the decorative cells on the module grid that fit in a shape.
     * The hexagon renderer also uses this function.
     *
     * @param  Context  $ctx
     * @param  float  $x0
     * @param  float  $x1
     * @param  float  $y0
     * @param  float  $y1
     * @param  float[]  $safe  matrix area to skip: x0, y0, x1, y1
     * @param  callable  $fits
     * @param  callable(float, float, float): bool  $fits
     * @return array<int, array{gi: int, gj: int, pcx: float, pcy: float, dotR: float}>
     */
    public static function scatter(Context $ctx, float $x0, float $x1, float $y0, float $y1, array $safe, callable $fits): array
    {
        $m = $ctx->moduleSize;
        // Move the boundary in by approximately one module. Thus the outer
        // row is not on the edge, and the real code stays visually strong.
        $safetyPad = $m * 1.6;
        $cells = [];

        for ($py = $y0; $py < $y1; $py += $m) {
            for ($px = $x0; $px < $x1; $px += $m) {
                $pcx = $px + $m / 2;
                $pcy = $py + $m / 2;
                if ($pcx >= $safe[0] && $pcx <= $safe[2] && $pcy >= $safe[1] && $pcy <= $safe[3]) {
                    continue;
                }

                // Round a half up.
                $gi = floor($py / $m + 0.5);
                $gj = floor($px / $m + 0.5);
                $noise = ($ctx->rand($gj, $gi) + $ctx->rand($gj + 31, $gi + 17) * 0.25) / 1.25;
                $isEven = ((fmod($gi, 2) + fmod($gj, 2) + 4) % 2) === 0;
                if ($noise > ($isEven ? 0.58 : 0.42)) {
                    continue;
                }

                if ($ctx->pattern === 'star') {
                    $dotR = $ctx->rand($gi, $gj) < 0.5 ? 4.0 : 2.8;
                } else {
                    $dotR = max(4.0 + ($ctx->rand($gj * 7, $gi * 11) - 0.5) * 0.5, 3.2);
                }

                // The bounding radius of the shape. A full cell needs its half-diagonal.
                if ($ctx->pattern === 'square' || $ctx->pattern === 'rounded') {
                    $boundR = $m * M_SQRT1_2;
                } elseif ($ctx->pattern === 'small') {
                    $boundR = $dotR * 1.85 * M_SQRT1_2;
                } elseif ($ctx->isPillLike()) {
                    $boundR = $m * 0.5;
                } else {
                    $boundR = $dotR;
                }

                if (! $fits($pcx, $pcy, $boundR + $safetyPad)) {
                    continue;
                }
                $cells[] = ['gi' => (int) $gi, 'gj' => (int) $gj, 'pcx' => $pcx, 'pcy' => $pcy, 'dotR' => $dotR];
            }
        }

        return $cells;
    }

    /**
     * Get the unit vector from one point to another.
     *
     * @param  array  $from
     * @param  array  $to
     * @param  array{x:float, y:float}  $from
     * @param  array{x:float, y:float}  $to
     * @return array{x:float, y:float}
     */
    private static function unit(array $from, array $to): array
    {
        $dx = $to['x'] - $from['x'];
        $dy = $to['y'] - $from['y'];
        $len = sqrt($dx * $dx + $dy * $dy);

        return ['x' => $dx / $len, 'y' => $dy / $len];
    }

    /**
     * Gives an equilateral triangle with rounded corners of radius r.
     *
     * @param  array  $a
     * @param  array  $b
     * @param  array  $c
     * @param  float  $r
     * @param  array{x:float, y:float}  $a  apex
     * @param  array{x:float, y:float}  $b  bottom-left
     * @param  array{x:float, y:float}  $c  bottom-right
     * @return string
     */
    private static function roundedTrianglePath(array $a, array $b, array $c, float $r): string
    {
        $verts = [$a, $c, $b];
        $d = $r * sqrt(3);
        $arcs = [];
        foreach ($verts as $i => $v) {
            $toPrev = self::unit($v, $verts[($i + 2) % 3]);
            $toNext = self::unit($v, $verts[($i + 1) % 3]);
            $arcs[] = [
                [$v['x'] + $toPrev['x'] * $d, $v['y'] + $toPrev['y'] * $d],
                [$v['x'] + $toNext['x'] * $d, $v['y'] + $toNext['y'] * $d],
            ];
        }
        $f = static fn (float $v): string => Num::fixed($v, 2);
        $p = 'M '.$f($arcs[0][0][0]).','.$f($arcs[0][0][1]);
        for ($j = 0; $j < 3; $j++) {
            $p .= ' A '.Num::fmt($r).','.Num::fmt($r).' 0 0 1 '.$f($arcs[$j][1][0]).','.$f($arcs[$j][1][1]);
            $next = $arcs[($j + 1) % 3][0];
            $p .= ' L '.$f($next[0]).','.$f($next[1]);
        }

        return $p.' Z';
    }
}

<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

/**
 * The decorative fill of a polygon with rounded corners. The triangle and
 * the hexagon renderers use it.
 *
 * @internal
 */
final class Polygon
{
    /**
     * Find the decorative cells on the module grid that fit in the polygon.
     *
     * The cells are not in the matrix area. The vertices go clockwise.
     *
     * @param  Context  $ctx
     * @param  array<int, array{x:float, y:float}>  $verts
     * @param  float  $cornerR  The radius of the rounded corners.
     * @param  float  $qrX0  The left edge of the matrix.
     * @param  float  $qrY0  The top edge of the matrix.
     * @param  float  $qrW  The width of the matrix.
     * @return array<int, array{gi: int, gj: int, pcx: float, pcy: float, dotR: float}>
     */
    public static function scatter(Context $ctx, array $verts, float $cornerR, float $qrX0, float $qrY0, float $qrW): array
    {
        $m = Context::MODULE;
        $fits = self::fitCheck($verts, $cornerR);
        [$minX, $maxX, $minY, $maxY] = [INF, -INF, INF, -INF];
        foreach ($verts as $v) {
            [$minX, $maxX] = [min($minX, $v['x']), max($maxX, $v['x'])];
            [$minY, $maxY] = [min($minY, $v['y']), max($maxY, $v['y'])];
        }

        // The grid starts three modules outside the polygon and aligns with the matrix.
        $x0 = floor(($minX - $m * 3) / $m) * $m + fmod($qrX0, $m);
        $x1 = $maxX + $m * 3;
        $y0 = floor(($minY - $m * 3) / $m) * $m + fmod($qrY0, $m);
        $y1 = $maxY + $m * 3;
        $safe = [$qrX0 - $m * 0.5, $qrY0 - $m * 0.5, $qrX0 + $qrW + $m * 0.5, $qrY0 + $qrW + $m * 0.5];

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
                $gi = (int) floor($py / $m + 0.5);
                $gj = (int) floor($px / $m + 0.5);
                $noise = ($ctx->rand($gj, $gi) + $ctx->rand($gj + 31, $gi + 17) * 0.25) / 1.25;
                $isEven = (($gi % 2) + ($gj % 2) + 4) % 2 === 0;
                if ($noise > ($isEven ? 0.58 : 0.42)) {
                    continue;
                }

                $dotR = Primitives::fillRadius($ctx, $gi, $gj);

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

                if ($fits($pcx, $pcy, $boundR + $safetyPad)) {
                    $cells[] = ['gi' => $gi, 'gj' => $gj, 'pcx' => $pcx, 'pcy' => $pcy, 'dotR' => $dotR];
                }
            }
        }

        return $cells;
    }

    /**
     * Make the check that tells if a circle is fully in the polygon.
     *
     * @param  array<int, array{x:float, y:float}>  $verts
     * @param  float  $cornerR
     * @return \Closure(float, float, float): bool
     */
    private static function fitCheck(array $verts, float $cornerR): \Closure
    {
        $count = count($verts);
        $edges = [];
        $arcs = [];
        foreach ($verts as $i => $v) {
            $toPrev = self::unit($v, $verts[($i + $count - 1) % $count]);
            $toNext = self::unit($v, $verts[($i + 1) % $count]);
            // The inward normal of the edge to the next vertex.
            $edges[] = [$v['x'], $v['y'], -$toNext['y'], $toNext['x']];

            // The corner arc touches the two edges at the inset from the vertex.
            $halfA = acos(max(-1.0, min(1.0, $toPrev['x'] * $toNext['x'] + $toPrev['y'] * $toNext['y']))) / 2;
            $bisX = $toPrev['x'] + $toNext['x'];
            $bisY = $toPrev['y'] + $toNext['y'];
            $centre = $cornerR / sin($halfA) / sqrt($bisX * $bisX + $bisY * $bisY);
            $arcs[] = [
                $v['x'], $v['y'], $toPrev, $toNext, $cornerR / tan($halfA),
                $v['x'] + $bisX * $centre, $v['y'] + $bisY * $centre,
            ];
        }

        return static function (float $x, float $y, float $r) use ($edges, $arcs, $cornerR): bool {
            foreach ($edges as [$vx, $vy, $nx, $ny]) {
                if (($x - $vx) * $nx + ($y - $vy) * $ny < $r) {
                    return false;
                }
            }
            foreach ($arcs as [$vx, $vy, $tp, $tn, $inset, $acx, $acy]) {
                $projP = ($x - $vx) * $tp['x'] + ($y - $vy) * $tp['y'];
                $projN = ($x - $vx) * $tn['x'] + ($y - $vy) * $tn['y'];
                if ($projP > 0 && $projP < $inset && $projN > 0 && $projN < $inset
                    && sqrt(($x - $acx) ** 2 + ($y - $acy) ** 2) > $cornerR - $r) {
                    return false;
                }
            }

            return true;
        };
    }

    /**
     * Get the unit vector from one point to another.
     *
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
}

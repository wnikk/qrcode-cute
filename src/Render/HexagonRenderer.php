<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

use Wnikk\QrCodeCute\Svg\Num;
use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Draws a hexagonal code. The matrix is in the centre of a hexagon with a
 * flat top and rounded corners. Decorative modules fill the remaining area.
 *
 * @internal
 */
final class HexagonRenderer
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

        // The bands scale with the matrix. Thus the hexagon keeps the same
        // aspect ratio (approximately 1.12, as the frame window) for all payloads.
        $qrW = $n * $m;
        $hexScale = $n / 25;
        $sideBand = 11 * $hexScale;
        $topBand = 8.45 * $hexScale;
        $hexW = $qrW + 2 * $sideBand * $m;
        $hexH = $qrW + 2 * $topBand * $m;

        // Increase the two bands until the corners of the matrix are a minimum
        // of 2.5 modules from the slanted edges.
        $need = $m * 2.5;
        for ($guard = 0; $guard < 60; $guard++) {
            $p1x = $hexW / 4;
            $p1y = -$hexH / 2;
            $ex = $hexW / 2 - $p1x;
            $ey = 0 - $p1y;
            $len = sqrt($ex * $ex + $ey * $ey);
            $nx = -$ey / $len;
            $ny = $ex / $len;
            if ($nx * (0 - $p1x) + $ny * (0 - $p1y) < 0) {
                $nx = -$nx;
                $ny = -$ny;
            }
            if ($nx * ($qrW / 2 - $p1x) + $ny * (-$qrW / 2 - $p1y) >= $need) {
                break;
            }
            $sideBand += 1;
            $topBand += 1;
            $hexW = $qrW + 2 * $sideBand * $m;
            $hexH = $qrW + 2 * $topBand * $m;
        }

        $margin = $m * 0.5 + $m * 2;
        $totalW = $hexW + $margin * 2;
        $totalH = $hexH + $margin * 2;
        $cx = $totalW / 2;
        $cy = $totalH / 2;

        // The vertices, clockwise from the top-right corner.
        $verts = [
            ['x' => $cx + $hexW / 4, 'y' => $cy - $hexH / 2],
            ['x' => $cx + $hexW / 2, 'y' => $cy],
            ['x' => $cx + $hexW / 4, 'y' => $cy + $hexH / 2],
            ['x' => $cx - $hexW / 4, 'y' => $cy + $hexH / 2],
            ['x' => $cx - $hexW / 2, 'y' => $cy],
            ['x' => $cx - $hexW / 4, 'y' => $cy - $hexH / 2],
        ];

        $qrX0 = $cx - $qrW / 2;
        $qrY0 = $cy - $qrW / 2;
        $safe = [$qrX0 - $m * 0.5, $qrY0 - $m * 0.5, $qrX0 + $qrW + $m * 0.5, $qrY0 + $qrW + $m * 0.5];

        $cornerR = $m * 4.5;

        $svg = Primitives::el('svg');
        $svg->setAttribute('id', 'qr-code');
        $svg->setAttribute('viewBox', '0 0 '.Num::fmt($totalW).' '.Num::fmt($totalH));
        $svg->setAttribute('shape-rendering', 'geometricPrecision');

        // The background shell: the hexagon, two modules larger.
        $bgPad = $m * 2;
        $bgVerts = [];
        foreach ($verts as $v) {
            $dx = $v['x'] - $cx;
            $dy = $v['y'] - $cy;
            $len = sqrt($dx * $dx + $dy * $dy);
            $s = ($len + $bgPad) / $len;
            $bgVerts[] = ['x' => $cx + $dx * $s, 'y' => $cy + $dy * $s];
        }
        $bgHex = Primitives::el('path');
        $bgHex->setAttribute('d', self::roundedHexPath($bgVerts, $cornerR + $bgPad));
        $bgHex->setAttribute('fill', $ctx->background);
        $svg->appendChild($bgHex);
        $svg->meta['bgShell'] = $bgHex;

        // The boundary for the fit check of each module.
        $meta = self::vertexMeta($verts);
        $edges = [];
        foreach ($verts as $i => $v1) {
            $v2 = $verts[($i + 1) % 6];
            $ex = $v2['x'] - $v1['x'];
            $ey = $v2['y'] - $v1['y'];
            $len = sqrt($ex * $ex + $ey * $ey);
            $edges[] = ['v' => $v1, 'nx' => -$ey / $len, 'ny' => $ex / $len];
        }
        $arcs = [];
        foreach ($meta as $mv) {
            $centerDist = $cornerR / sin($mv['halfA']);
            $arcs[] = [
                'v' => $mv['v'], 'tp' => $mv['toPrev'], 'tn' => $mv['toNext'],
                'inset' => $cornerR / tan($mv['halfA']),
                'cx' => $mv['v']['x'] + $mv['bisX'] * $centerDist,
                'cy' => $mv['v']['y'] + $mv['bisY'] * $centerDist,
            ];
        }
        $fits = static function (float $x, float $y, float $r) use ($edges, $arcs, $cornerR): bool {
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
                if ($projP > 0 && $projP < $a['inset'] && $projN > 0 && $projN < $a['inset']
                    && sqrt(($x - $a['cx']) ** 2 + ($y - $a['cy']) ** 2) > $cornerR - $r) {
                    return false;
                }
            }

            return true;
        };

        $cells = TriangleRenderer::scatter(
            $ctx,
            floor(($margin - $m * 3) / $m) * $m + fmod($qrX0, $m),
            $totalW - $margin + $m * 3,
            floor(($margin - $m * 3) / $m) * $m + fmod($qrY0, $m),
            $totalH - $margin + $m * 3,
            $safe,
            $fits
        );

        // The area that the decorative fill covers. A frame scales this area,
        // not the hexagon, to its window. Thus the fill is near the ring.
        $minX = $qrX0;
        $maxX = $qrX0 + $qrW;
        $minY = $qrY0;
        $maxY = $qrY0 + $qrW;
        foreach ($cells as $c) {
            $r = $c['dotR'];
            $minX = min($minX, $c['pcx'] - $r);
            $maxX = max($maxX, $c['pcx'] + $r);
            $minY = min($minY, $c['pcy'] - $r);
            $maxY = max($maxY, $c['pcy'] + $r);
        }

        $contG = Primitives::el('g');
        $contG->setAttribute('fill', $ctx->color);
        Primitives::drawContinuation($ctx, $contG, $cells);
        $svg->appendChild($contG);

        Primitives::drawData($ctx, $svg, $matrix, $qrX0, $qrY0);
        Primitives::drawFinderEyes($ctx, $svg, $n, $qrX0, $qrY0);

        $svg->meta['hexFill'] = [
            'halfW' => max($cx - $minX, $maxX - $cx),
            'halfH' => max($cy - $minY, $maxY - $cy),
        ];

        return $svg;
    }

    /**
     * Gives the interior angle and the inward bisector for each vertex.
     *
     * @param  array  $verts
     * @param  array<int, array{x:float, y:float}>  $verts
     * @return array<int, array<string, mixed>>
     */
    private static function vertexMeta(array $verts): array
    {
        $count = count($verts);
        $out = [];
        foreach ($verts as $i => $v) {
            $toPrev = self::unit($v, $verts[($i + $count - 1) % $count]);
            $toNext = self::unit($v, $verts[($i + 1) % $count]);
            $cosA = max(-1.0, min(1.0, $toPrev['x'] * $toNext['x'] + $toPrev['y'] * $toNext['y']));
            $bisX = ($toPrev['x'] + $toNext['x']) / 2;
            $bisY = ($toPrev['y'] + $toNext['y']) / 2;
            $blen = sqrt($bisX * $bisX + $bisY * $bisY) ?: 1.0;
            $out[] = [
                'v' => $v,
                'toPrev' => $toPrev,
                'toNext' => $toNext,
                'halfA' => acos($cosA) / 2,
                'bisX' => $bisX / $blen,
                'bisY' => $bisY / $blen,
            ];
        }

        return $out;
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
     * Gives a hexagon with rounded corners. Each vertex has its own inset,
     * because the angles of a stretched hexagon are not all equal.
     *
     * @param  array  $verts
     * @param  float  $r
     * @param  array<int, array{x:float, y:float}>  $verts
     * @return string
     */
    private static function roundedHexPath(array $verts, float $r): string
    {
        $arcs = [];
        foreach (self::vertexMeta($verts) as $mv) {
            $inset = $r / tan($mv['halfA']);
            $arcs[] = [
                [$mv['v']['x'] + $mv['toPrev']['x'] * $inset, $mv['v']['y'] + $mv['toPrev']['y'] * $inset],
                [$mv['v']['x'] + $mv['toNext']['x'] * $inset, $mv['v']['y'] + $mv['toNext']['y'] * $inset],
            ];
        }
        $f = static fn (float $v): string => Num::fixed($v, 2);
        $count = count($verts);
        $p = 'M '.$f($arcs[0][0][0]).','.$f($arcs[0][0][1]);
        for ($j = 0; $j < $count; $j++) {
            $p .= ' A '.Num::fmt($r).','.Num::fmt($r).' 0 0 1 '.$f($arcs[$j][1][0]).','.$f($arcs[$j][1][1]);
            $next = $arcs[($j + 1) % $count][0];
            $p .= ' L '.$f($next[0]).','.$f($next[1]);
        }

        return $p.' Z';
    }
}

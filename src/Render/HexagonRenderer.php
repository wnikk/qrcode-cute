<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Draws a hexagonal code. The matrix is in the centre of a hexagon with a
 * flat top and rounded corners. Decorative modules fill the remaining area.
 *
 * The frame renderer scales this artwork into its window.
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
        $m = Context::MODULE;

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
        $cells = Polygon::scatter($ctx, $verts, $m * 4.5, $qrX0, $qrY0, $qrW);

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

        $svg = Primitives::svg($totalW, $totalH);
        $fill = new SvgNode('g');
        $fill->setAttribute('fill', $ctx->color);
        Primitives::drawCells($ctx, $fill, $cells);
        $svg->appendChild($fill);

        Primitives::drawData($ctx, $svg, $matrix, $qrX0, $qrY0);
        Primitives::drawFinderEyes($ctx, $svg, $n, $qrX0, $qrY0);

        $svg->meta['hexFill'] = [
            'halfW' => max($cx - $minX, $maxX - $cx),
            'halfH' => max($cy - $minY, $maxY - $cy),
        ];

        return $svg;
    }
}

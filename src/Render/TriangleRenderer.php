<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Draws a triangular code. The matrix is in the low part of a rounded
 * triangle, where the triangle is widest. Decorative modules fill the remaining area.
 *
 * The frame renderer scales this artwork into its window.
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
        $m = Context::MODULE;

        $qrW = $n * $m;
        $triBase = ceil($qrW * 2.6);
        $triHeight = ceil($triBase * sqrt(3) / 2);

        $marginX = $m * 2.5;
        $marginYTop = $m * 2.5;
        // A larger bottom margin balances the wide base of the triangle.
        $marginYBot = $marginYTop + $triHeight * 0.18;

        $totalW = $triBase + $marginX * 2;
        $totalH = $triHeight + $marginYTop + $marginYBot;
        $baseY = $marginYTop + $triHeight;

        // The vertices go clockwise: apex, bottom-right, bottom-left.
        $verts = [
            ['x' => $totalW / 2, 'y' => $marginYTop],
            ['x' => $totalW - $marginX, 'y' => $baseY],
            ['x' => $marginX, 'y' => $baseY],
        ];

        // The matrix is in the horizontal centre, two modules above the base.
        $qrX0 = $totalW / 2 - $qrW / 2;
        $qrY0 = $baseY - $qrW - $m * 2;

        $svg = Primitives::svg($totalW, $totalH);

        $fill = new SvgNode('g');
        $fill->setAttribute('fill', $ctx->color);
        Primitives::drawCells($ctx, $fill, Polygon::scatter($ctx, $verts, $triBase * 0.07, $qrX0, $qrY0, $qrW));
        $svg->appendChild($fill);

        Primitives::drawData($ctx, $svg, $matrix, $qrX0, $qrY0);
        Primitives::drawFinderEyes($ctx, $svg, $n, $qrX0, $qrY0);

        // The boundary triangle that a frame scales into its window. The random
        // fill does not change it. Thus the gap to the ring is always equal.
        $svg->meta['triGeom'] = [
            'apexY' => $marginYTop,
            'baseY' => $baseY,
            'centroidX' => $totalW / 2,
            'centroidY' => ($marginYTop + 2 * $baseY) / 3,
        ];

        return $svg;
    }
}

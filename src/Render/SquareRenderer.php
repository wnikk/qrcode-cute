<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Draws a square code without a frame. The modules are on a grid in a
 * rounded background card.
 *
 * @internal
 */
final class SquareRenderer
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
        $quiet = 4;

        $qrSize = ($n + $quiet * 2) * $m;
        // Add background around the quiet zone, so that the rounded card is visible.
        $shellPad = $m * 2;
        $total = $qrSize + 2 * $shellPad;

        $svg = Primitives::makeSvg($total, $ctx->background, $m * 2.5);
        $svg->setAttribute('shape-rendering', 'geometricPrecision');

        $g = Primitives::el('g');
        $g->setAttribute('transform', 'translate('.$shellPad.','.$shellPad.')');
        $svg->appendChild($g);

        Primitives::drawData($ctx, $g, $matrix, $quiet * $m, $quiet * $m);
        Primitives::drawFinderEyes($ctx, $g, $n, $quiet * $m, $quiet * $m);

        return $svg;
    }
}

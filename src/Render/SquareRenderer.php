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
        $m = Context::MODULE;
        $quiet = 4;

        $qrSize = ($n + $quiet * 2) * $m;
        // Add background around the quiet zone, so that the rounded card is visible.
        $shellPad = $m * 2;
        $total = $qrSize + 2 * $shellPad;

        $svg = Primitives::svg($total, $total);
        if ($ctx->background !== 'transparent') {
            $card = new SvgNode('rect');
            $card->setAttribute('width', $total);
            $card->setAttribute('height', $total);
            $card->setAttribute('rx', $m * 2.5);
            $card->setAttribute('fill', $ctx->background);
            $svg->appendChild($card);
        }

        $g = new SvgNode('g');
        $g->setAttribute('transform', 'translate('.$shellPad.','.$shellPad.')');
        $svg->appendChild($g);

        Primitives::drawData($ctx, $g, $matrix, $quiet * $m, $quiet * $m);
        Primitives::drawFinderEyes($ctx, $g, $n, $quiet * $m, $quiet * $m);

        return $svg;
    }
}

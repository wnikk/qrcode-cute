<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

use Wnikk\QrCodeCute\Svg\Num;
use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Draws a code in one of the frames.
 *
 * Each shape renderer makes a code without a frame. This class scales that
 * code into the frame window and draws the frame around it. Each frame has a
 * 420x420 box in the centre of a 440x440 background card.
 *
 * @internal
 */
final class FrameRenderer
{
    /**
     * Draw the code in a frame.
     *
     * @param  Context  $ctx  The drawing settings.
     * @param  bool[][]  $matrix
     * @param  string  $frameKey  The artwork key of the frame.
     * @return SvgNode
     */
    public static function render(Context $ctx, array $matrix, string $frameKey): SvgNode
    {
        $spec = Frames::spec($frameKey);
        if ($spec === null) {
            throw new \LogicException('The frame artwork is not known: '.$frameKey);
        }
        $n = count($matrix);
        $m = $ctx->moduleSize = 10;
        $box = Frames::DESIGN_BOX;

        $svg = Primitives::el('svg');
        $svg->setAttribute('id', 'qr-code');
        $svg->setAttribute('viewBox', '0 0 '.Frames::CARD_BOX.' '.Frames::CARD_BOX);
        $svg->setAttribute('shape-rendering', 'geometricPrecision');
        Frames::appendBgCard($svg, $spec['shape'], $ctx->background);

        // Put all artwork in a 420 group in the centre of the 440 card.
        $root = Primitives::el('g');
        $root->setAttribute('id', 'QR-code');
        $root->setAttribute('transform', 'translate('.Frames::CARD_PAD.','.Frames::CARD_PAD.')');
        $svg->appendChild($root);

        $content = $spec['content'] ?? 'square';

        if ($content === 'circle') {
            $shape = CircleRenderer::render($ctx, $matrix);
            [$w, $h] = self::size($shape);
            // Scale the decorative fill, not the full artwork, so that the fill is near the ring.
            $scale = ($spec['innerR'] - 5) / $shape->meta['dotFillR'];
            $tx = $box / 2 - ($w / 2) * $scale;
            $ty = $box / 2 - ($h / 2) * $scale;
            self::place($root, $shape, $tx, $ty, $scale);
        } elseif ($content === 'hex') {
            $shape = HexagonRenderer::render($ctx, $matrix);
            [$w, $h] = self::size($shape);
            $fill = $shape->meta['hexFill'];
            // Use one scale for the two axes, so the modules stay square. Keep a gap of approximately 9 units on each side.
            $gap = 18;
            $scale = min(
                (($spec['innerW'] ?? Frames::DESIGN_QR) - $gap) / ($fill['halfW'] * 2),
                (($spec['innerH'] ?? Frames::DESIGN_QR) - $gap) / ($fill['halfH'] * 2)
            );
            $tx = $box / 2 - ($w / 2) * $scale;
            $ty = ($spec['cy'] ?? $box / 2) - ($h / 2) * $scale;
            self::place($root, $shape, $tx, $ty, $scale);
        } elseif ($content === 'tri') {
            $shape = TriangleRenderer::render($ctx, $matrix);
            $geom = $shape->meta['triGeom'];
            $target = $spec['triTarget'];
            // Put the boundary triangle on the inner triangle of the frame.
            // Align the centroids, so the gap is equal on the three edges.
            $scale = $target['h'] / max(1, $geom['baseY'] - $geom['apexY']);
            $tx = $target['cx'] - $scale * $geom['centroidX'];
            $ty = $target['cy'] - $scale * $geom['centroidY'];
            self::place($root, $shape, $tx, $ty, $scale);
        } else {
            // Square: the matrix and a quiet zone of two modules fill the 300 window.
            $quiet = 2;
            $scale = Frames::DESIGN_QR / ($n * $m);
            $t = Frames::DESIGN_OFF - $scale * $quiet * $m;
            $g = Primitives::el('g');
            $g->setAttribute('id', 'Code');
            $g->setAttribute('transform', self::transform($t, $t, $scale));
            $root->appendChild($g);
            Primitives::drawData($ctx, $g, $matrix, $quiet * $m, $quiet * $m);
            Primitives::drawFinderEyes($ctx, $g, $n, $quiet * $m, $quiet * $m);
        }

        $frameFill = $ctx->frameFill();
        $layer = Primitives::el('g');
        $layer->setAttribute('id', 'Frame');
        $root->appendChild($layer);

        foreach ($spec['paths'] ?? [] as $p) {
            $path = Primitives::el('path');
            $path->setAttribute('d', $p['d']);
            if (isset($p['fillRule'])) {
                $path->setAttribute('fill-rule', $p['fillRule']);
            }
            $path->setAttribute('fill', $frameFill);
            $layer->appendChild($path);
        }
        foreach ($spec['strokes'] ?? [] as $st) {
            $el = Primitives::el($st['el']);
            if ($st['el'] === 'circle') {
                $el->setAttribute('cx', $st['cx']);
                $el->setAttribute('cy', $st['cy']);
                $el->setAttribute('r', $st['r']);
            } else {
                $el->setAttribute('d', $st['d']);
                $el->setAttribute('stroke-linejoin', 'round');
            }
            $el->setAttribute('fill', 'none');
            $el->setAttribute('stroke', $frameFill);
            $el->setAttribute('stroke-width', $st['strokeWidth']);
            $layer->appendChild($el);
        }

        return $svg;
    }

    /**
     * Get the width and the height from the viewBox.
     *
     * @param  SvgNode  $svg
     * @return float[] width and height from the viewBox
     */
    private static function size(SvgNode $svg): array
    {
        $vb = array_map('floatval', preg_split('/[\s,]+/', trim((string) $svg->getAttribute('viewBox'))) ?: []);

        return [$vb[2] ?? 1.0, $vb[3] ?? 1.0];
    }

    /**
     * Moves the artwork of a shape into a scaled group. Removes the background shell of the shape.
     *
     * @param  SvgNode  $root
     * @param  SvgNode  $shape
     * @param  float  $tx
     * @param  float  $ty
     * @param  float  $scale
     * @return void
     */
    private static function place(SvgNode $root, SvgNode $shape, float $tx, float $ty, float $scale): void
    {
        if (isset($shape->meta['bgShell'])) {
            $shape->meta['bgShell']->remove();
        }
        $g = Primitives::el('g');
        $g->setAttribute('id', 'Code');
        $g->setAttribute('transform', self::transform($tx, $ty, $scale));
        $g->adoptChildrenOf($shape);
        $root->appendChild($g);
    }

    /**
     * Make a transform attribute for a translation and a scale.
     *
     * @param  float  $tx
     * @param  float  $ty
     * @param  float  $scale
     * @return string
     */
    private static function transform(float $tx, float $ty, float $scale): string
    {
        return 'translate('.Num::fixed($tx, 3).','.Num::fixed($ty, 3).') scale('.Num::fixed($scale, 5).')';
    }
}

<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

use Wnikk\QrCodeCute\Svg\Num;
use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Draws a circular code. The matrix is in the centre of a disc.
 * Decorative modules fill the area between the matrix and the edge of the disc.
 *
 * @internal
 */
final class CircleRenderer
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

        $quiet = 4;    // real quiet zone, in modules
        $contPad = 4;  // decorative band beyond the quiet zone, in modules
        $innerR = ($n / 2 + $quiet + $contPad) * $m;
        // The corners of the matrix are (n/2)*sqrt(2) modules from the centre.
        // For long payloads, make the disc two modules larger than the corners.
        $halfDiag = ($n / 2) * $m * M_SQRT2;
        if ($innerR < $halfDiag + $m * 2) {
            $innerR = $halfDiag + $m * 2;
        }

        $shellPad = $m * 2;
        $margin = 6 + $shellPad;
        $total = ceil($innerR * 2) + $margin * 2;
        $cx = $total / 2;
        $cy = $total / 2;

        $qrX0 = $cx - ($n / 2) * $m;
        $qrY0 = $cy - ($n / 2) * $m;
        $safeX0 = $qrX0 - $m * 0.5;
        $safeY0 = $qrY0 - $m * 0.5;
        $safeX1 = $qrX0 + $n * $m + $m * 0.5;
        $safeY1 = $qrY0 + $n * $m + $m * 0.5;

        $svg = Primitives::el('svg');
        $svg->setAttribute('id', 'qr-code');
        $svg->setAttribute('viewBox', '0 0 '.Num::fmt($total).' '.Num::fmt($total));
        $svg->setAttribute('shape-rendering', 'geometricPrecision');

        $bgCircle = Primitives::el('circle');
        $bgCircle->setAttribute('cx', $cx);
        $bgCircle->setAttribute('cy', $cy);
        $bgCircle->setAttribute('r', $innerR + $shellPad);
        $bgCircle->setAttribute('fill', $ctx->background);
        $svg->appendChild($bgCircle);
        // A frame has its own card. It removes this shell.
        $svg->meta['bgShell'] = $bgCircle;

        $contG = Primitives::el('g');
        $contG->setAttribute('fill', $ctx->color);

        $scanPad = max($quiet + $contPad, (int) ceil(($innerR - ($n / 2) * $m) / $m)) + 1;
        $zoneWidth = $innerR - ($n / 2) * $m;
        $cells = [];

        for ($gi = -$scanPad; $gi < $n + $scanPad; $gi++) {
            for ($gj = -$scanPad; $gj < $n + $scanPad; $gj++) {
                $pcx = $qrX0 + ($gj + 0.5) * $m;
                $pcy = $qrY0 + ($gi + 0.5) * $m;

                if ($pcx >= $safeX0 && $pcx <= $safeX1 && $pcy >= $safeY0 && $pcy <= $safeY1) {
                    continue;
                }

                $distC = sqrt(($pcx - $cx) ** 2 + ($pcy - $cy) ** 2);
                if ($distC > $innerR - $m * 0.9) {
                    continue;
                }

                // Fill approximately 55% of the cells, as in a real code. Decrease the fill only near the edge.
                $edgeFade = $distC > $innerR - $m * 3.5
                    ? max(0, ($innerR - $m * 0.9 - $distC) / ($m * 2.6))
                    : 1.0;
                $noise = ($ctx->rand($gj, $gi) + $ctx->rand($gj + 31, $gi + 17) * 0.25) / 1.25;
                // Use a checkerboard base, as in the timing pattern.
                $isEven = ((($gi % 2) + ($gj % 2) + 4) % 2) === 0;
                $threshold = ($isEven ? 0.58 : 0.42) * $edgeFade;
                if ($noise > $threshold) {
                    continue;
                }

                $cell = ['gi' => $gi, 'gj' => $gj, 'pcx' => $pcx, 'pcy' => $pcy, 'dotR' => 4.0];
                if ($ctx->pattern === 'star') {
                    $cell['dotR'] = $ctx->rand($gi, $gj) < 0.5 ? 4.0 : 2.8;
                } else {
                    $cell['dotR'] = max(4.0 + ($ctx->rand($gj * 7, $gi * 11) - 0.5) * 0.5, 3.2);
                }
                $cells[] = $cell;
            }
        }

        Primitives::drawContinuation($ctx, $contG, $cells);
        $svg->appendChild($contG);

        Primitives::drawData($ctx, $svg, $matrix, $qrX0, $qrY0);
        Primitives::drawFinderEyes($ctx, $svg, $n, $qrX0, $qrY0);

        // The radius of the decorative fill. A frame scales this radius to
        // put the fill near its ring.
        $svg->meta['dotFillR'] = $innerR - $m * 0.9;

        return $svg;
    }
}

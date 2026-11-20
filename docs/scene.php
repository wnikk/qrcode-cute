<?php

/*
 * The shared helper of the examples in docs/.
 *
 * It loads the library and puts QR codes on cards in a grid. The library's
 * own raster renderer draws the grid. Thus each image is the same as the
 * output of QrCode::toPng() or QrCode::toWebp().
 */

declare(strict_types=1);

require_once dirname(__DIR__).'/src/autoload.php';

use Wnikk\QrCodeCute\QrCode;
use Wnikk\QrCodeCute\Raster\Rasterizer;
use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Put the codes on cards in a grid and save the image.
 *
 * The file extension sets the format: .png or .webp.
 *
 * @param  string  $file  The image file name, in docs/.
 * @param  QrCode[]  $codes  The codes, from left to right and from top to bottom.
 * @param  string  $band  The colour behind the cards.
 * @param  string|string[]  $cards  The colour of all cards, or one colour for each card.
 * @param  int|null  $columns  The number of cards in a row. The value null puts all cards in one row.
 * @param  int  $width  The image width in pixels.
 * @return void
 */
function saveScene(string $file, array $codes, string $band, string|array $cards = '#ffffff', ?int $columns = null, int $width = 2000): void
{
    $card = 440;
    $gap = 44;
    $pad = 52;
    $codeSize = 340;
    $columns ??= count($codes);
    $rows = (int) ceil(count($codes) / $columns);

    $sceneWidth = $pad * 2 + $columns * $card + ($columns - 1) * $gap;
    $sceneHeight = $pad * 2 + $rows * $card + ($rows - 1) * $gap;

    $root = new SvgNode('svg');
    $root->setAttribute('viewBox', "0 0 $sceneWidth $sceneHeight");
    $background = new SvgNode('rect');
    $background->setAttribute('width', $sceneWidth)->setAttribute('height', $sceneHeight)->setAttribute('fill', $band);
    $root->appendChild($background);

    foreach (array_values($codes) as $i => $qr) {
        $x = $pad + ($i % $columns) * ($card + $gap);
        $y = $pad + intdiv($i, $columns) * ($card + $gap);

        $rect = new SvgNode('rect');
        $rect->setAttribute('x', $x)->setAttribute('y', $y)
            ->setAttribute('width', $card)->setAttribute('height', $card)
            ->setAttribute('rx', 40)->setAttribute('fill', is_array($cards) ? ($cards[$i] ?? '#ffffff') : $cards);
        $root->appendChild($rect);

        // Scale the artwork of the code into a box in the centre of the card.
        $svg = $qr->toSvgNode();
        [, , $w, $h] = array_map('floatval', explode(' ', (string) $svg->getAttribute('viewBox')));
        $scale = $codeSize / max($w, $h);
        $group = new SvgNode('g');
        $group->setAttribute('transform', sprintf(
            'translate(%F,%F) scale(%F)',
            $x + ($card - $w * $scale) / 2,
            $y + ($card - $h * $scale) / 2,
            $scale
        ));
        $group->adoptChildrenOf($svg);
        $root->appendChild($group);
    }

    $data = str_ends_with($file, '.webp') ? Rasterizer::webp($root, $width) : Rasterizer::png($root, $width);
    file_put_contents(__DIR__.'/'.$file, $data);
    echo 'docs/'.$file, "\n";
}

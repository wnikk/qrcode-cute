<?php

/*
 * Example 1: the four shapes, each with a frame.
 *
 *     php docs/example1-shapes.php
 *
 * The script writes docs/example1-shapes.png.
 */

declare(strict_types=1);

require __DIR__.'/scene.php';

use Wnikk\QrCodeCute\QrCode;

$url = 'https://github.com/wnikk/qrcode-cute';

saveScene('example1-shapes.png', [
    new QrCode($url, ['shape' => 'circle', 'frame' => 'double', 'pattern' => 'square', 'color' => '#6d28d9']),
    new QrCode($url, ['shape' => 'square', 'frame' => 'organic', 'pattern' => 'dots', 'color' => '#c81e1e']),
    new QrCode($url, ['shape' => 'hexagon', 'frame' => 'organic', 'pattern' => 'rounded', 'color' => '#18181b']),
    new QrCode($url, ['shape' => 'triangle', 'frame' => 'thin', 'pattern' => 'dots', 'color' => '#15803d']),
], '#86efac');

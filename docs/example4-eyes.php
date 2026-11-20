<?php

/*
 * Example 4: the three eye shapes, with an eye colour that is different from the module colour.
 *
 *     php docs/example4-eyes.php
 *
 * The script writes docs/example4-eyes.png.
 */

declare(strict_types=1);

require __DIR__.'/scene.php';

use Wnikk\QrCodeCute\QrCode;

$url = 'https://github.com/wnikk/qrcode-cute';

saveScene('example4-eyes.png', [
    new QrCode($url, ['eyeShape' => 'circle', 'pattern' => 'rounded', 'color' => '#be123c', 'eyeColor' => '#0369a1', 'background' => '#fff1f2']),
    new QrCode($url, ['eyeShape' => 'rounded', 'pattern' => 'rounded', 'color' => '#047857', 'eyeColor' => '#c2410c', 'background' => '#ecfdf5']),
    new QrCode($url, ['eyeShape' => 'square', 'pattern' => 'rounded', 'color' => '#1e3a8a', 'eyeColor' => '#ca8a04', 'background' => '#eff6ff']),
], '#fde68a', ['#fff1f2', '#ecfdf5', '#eff6ff'], null, 1500);

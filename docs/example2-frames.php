<?php

/*
 * Example 2: the four frames on one shape, each in its own colours.
 *
 *     php docs/example2-frames.php
 *
 * The script writes docs/example2-frames.png.
 */

declare(strict_types=1);

require __DIR__.'/scene.php';

use Wnikk\QrCodeCute\QrCode;

$url = 'https://github.com/wnikk/qrcode-cute';

// Each option set changes the module colour, the eye colour, the frame colour and the background.
$designs = [
    ['frame' => 'none', 'color' => '#0f766e', 'eyeColor' => '#f59e0b', 'background' => '#f0fdfa'],
    ['frame' => 'thin', 'color' => '#1d4ed8', 'eyeColor' => '#db2777', 'frameColor' => '#db2777', 'background' => '#eff6ff'],
    ['frame' => 'double', 'color' => '#7c2d12', 'eyeColor' => '#ea580c', 'frameColor' => '#ea580c', 'background' => '#fff7ed'],
    ['frame' => 'organic', 'color' => '#4c1d95', 'eyeColor' => '#7c3aed', 'frameColor' => '#16a34a', 'background' => '#f5f3ff'],
];

$codes = [];
foreach ($designs as $options) {
    $codes[] = new QrCode($url, ['shape' => 'circle'] + $options);
}

saveScene('example2-frames.png', $codes, '#1e293b', array_column($designs, 'background'));

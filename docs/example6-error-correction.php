<?php

/*
 * Example 6: one payload at the four error correction levels.
 *
 *     php docs/example6-error-correction.php
 *
 * The script writes docs/example6-error-correction.png. From left to right:
 * L, M, Q and H. A higher level adds more error correction data, thus the
 * matrix has more modules.
 */

declare(strict_types=1);

require __DIR__.'/scene.php';

use Wnikk\QrCodeCute\QrCode;

$text = 'https://github.com/wnikk/qrcode-cute?utm_source=docs&utm_medium=example&utm_campaign=error-correction';

$codes = [];
foreach (QrCode::ERROR_CORRECTION_LEVELS as $level) {
    $codes[] = new QrCode($text, ['errorCorrection' => $level, 'pattern' => 'square', 'eyeShape' => 'square', 'color' => '#1e3a8a']);
}

saveScene('example6-error-correction.png', $codes, '#c7d2fe');

<?php

/*
 * Example 3: the thirteen module patterns.
 *
 *     php docs/example3-patterns.php
 *
 * The script writes docs/example3-patterns.png. The order of the codes is
 * the order of QrCode::PATTERNS, from left to right and from top to bottom.
 */

declare(strict_types=1);

require __DIR__.'/scene.php';

use Wnikk\QrCodeCute\QrCode;

$url = 'https://github.com/wnikk/qrcode-cute';

$codes = [];
foreach (QrCode::PATTERNS as $pattern) {
    $codes[] = new QrCode($url, ['pattern' => $pattern, 'color' => '#18181b']);
}

saveScene('example3-patterns.png', $codes, '#e4e4e7', '#ffffff', 5);

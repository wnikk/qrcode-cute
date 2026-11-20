<?php

/*
 * Autoloader for use without Composer:
 *
 *     require '/path/to/qrcode-cute/src/autoload.php';
 *
 * With Composer, use vendor/autoload.php. You do not need this file.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Wnikk\\QrCodeCute\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($file)) {
        require $file;
    }
});

<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use PHPUnit\Framework\Assert;

/**
 * Reads the PNG files that the library writes. The tests use it to examine the pixels.
 */
final class PngDecoder
{
    /**
     * @return array{width:int, height:int, type:int, pixels:string}
     */
    public static function decode(string $png): array
    {
        Assert::assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8), 'The PNG signature is missing.');
        $offset = 8;
        $idat = '';
        $info = [];
        while ($offset < strlen($png)) {
            $len = self::uint32(substr($png, $offset, 4));
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $len);
            Assert::assertSame(crc32($type.$data), self::uint32(substr($png, $offset + 8 + $len, 4)), "The CRC of $type is incorrect.");
            if ($type === 'IHDR') {
                $info = [
                    'width' => self::uint32(substr($data, 0, 4)),
                    'height' => self::uint32(substr($data, 4, 4)),
                    'type' => ord($data[9]),
                ];
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            }
            $offset += 12 + $len;
        }

        if ($info === []) {
            Assert::fail('The IHDR chunk is missing.');
        }

        $raw = gzuncompress($idat);
        Assert::assertNotFalse($raw, 'The IDAT data does not decompress.');
        $bpp = $info['type'] === 6 ? 4 : 3;
        $stride = $info['width'] * $bpp;
        Assert::assertSame(($stride + 1) * $info['height'], strlen($raw), 'The image data has an incorrect length.');

        $pixels = '';
        for ($y = 0; $y < $info['height']; $y++) {
            Assert::assertSame("\0", $raw[$y * ($stride + 1)], 'The row filter is not 0.');
            $pixels .= substr($raw, $y * ($stride + 1) + 1, $stride);
        }

        return ['width' => $info['width'], 'height' => $info['height'], 'type' => $info['type'], 'pixels' => $pixels];
    }

    /**
     * Read a big-endian 32-bit integer.
     *
     * @param  string  $bytes
     * @return int
     */
    private static function uint32(string $bytes): int
    {
        return (ord($bytes[0]) << 24) | (ord($bytes[1]) << 16) | (ord($bytes[2]) << 8) | ord($bytes[3]);
    }
}

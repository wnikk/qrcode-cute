<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use PHPUnit\Framework\TestCase;
use Wnikk\QrCodeCute\QrCode;
use Wnikk\QrCodeCute\Raster\Rasterizer;

final class WebpTest extends TestCase
{
    private const TEXT = 'https://example.com';

    public function test_the_file_has_a_correct_riff_header(): void
    {
        $webp = QrCode::webp(self::TEXT, [], 300);

        $this->assertSame('RIFF', substr($webp, 0, 4));
        $this->assertSame(strlen($webp) - 8, unpack('V', substr($webp, 4, 4))[1] ?? null);
        $this->assertSame('WEBPVP8L', substr($webp, 8, 8));
        $this->assertSame(0, strlen($webp) % 2, 'A RIFF chunk has an even length.');
        $this->assertSame("\x2F", $webp[20], 'The VP8L signature is present.');

        // The VP8L header has the width and the height minus one, 14 bits each.
        $bits = unpack('V', substr($webp, 21, 4))[1] ?? 0;
        $this->assertSame(299, $bits & 0x3FFF);
        $this->assertSame(299, ($bits >> 14) & 0x3FFF);
    }

    public function test_the_pixels_are_lossless(): void
    {
        if (! function_exists('imagecreatefromwebp') || ! (gd_info()['WebP Support'] ?? false)) {
            $this->markTestSkipped('GD with WebP support is necessary to decode the file.');
        }

        $cases = [
            ['shape' => 'circle', 'frame' => 'organic', 'pattern' => 'rounded', 'color' => '#7c3aed'],
            ['shape' => 'hexagon', 'pattern' => 'hearts', 'background' => 'transparent'],
            ['shape' => 'triangle', 'frame' => 'double', 'pattern' => 'pill-v', 'background' => '#fef3c7'],
        ];
        foreach ($cases as $options) {
            $node = (new QrCode(self::TEXT, $options))->toSvgNode();
            $rgba = Rasterizer::canvas($node, 160)->toRgba();
            $image = imagecreatefromstring(Rasterizer::webp($node, 160));
            $this->assertNotFalse($image, json_encode($options) ?: '');

            $wrong = 0;
            for ($y = 0; $y < 160; $y++) {
                for ($x = 0; $x < 160; $x++) {
                    $o = ($y * 160 + $x) * 4;
                    $color = imagecolorat($image, $x, $y);
                    $alpha = ord($rgba[$o + 3]);
                    // GD keeps alpha in 7 bits, 127 is transparent.
                    $gdAlpha = 127 - (($color >> 24) & 0x7F);
                    $same = $alpha === 0
                        ? $gdAlpha === 0
                        : (($color >> 16) & 0xFF) === ord($rgba[$o])
                            && (($color >> 8) & 0xFF) === ord($rgba[$o + 1])
                            && ($color & 0xFF) === ord($rgba[$o + 2])
                            && abs($gdAlpha - ($alpha >> 1)) <= 1;
                    if (! $same) {
                        $wrong++;
                    }
                }
            }
            $this->assertSame(0, $wrong, json_encode($options) ?: '');
        }
    }

    public function test_save_and_data_uri_support_webp(): void
    {
        $qr = new QrCode(self::TEXT);
        $this->assertStringStartsWith('data:image/webp;base64,UklGR', $qr->toDataUri('webp', 64));

        $path = sys_get_temp_dir().'/qrcode-cute-'.bin2hex(random_bytes(4)).'.webp';
        try {
            $qr->save($path, 64);
            $this->assertSame('RIFF', substr((string) file_get_contents($path), 0, 4));
        } finally {
            @unlink($path);
        }
    }
}

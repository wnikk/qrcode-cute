<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use PHPUnit\Framework\TestCase;
use Wnikk\QrCodeCute\Exception\RenderException;
use Wnikk\QrCodeCute\QrCode;
use Wnikk\QrCodeCute\Raster\Rasterizer;
use Wnikk\QrCodeCute\Svg\SvgNode;

final class PngTest extends TestCase
{
    private const TEXT = 'https://example.com';

    public function test_the_png_has_the_size_and_shows_the_code(): void
    {
        $img = PngDecoder::decode((new QrCode(self::TEXT))->toPng(300));

        $this->assertSame(300, $img['width']);
        $this->assertSame(300, $img['height']);
        $this->assertSame(6, $img['type'], 'The rounded corners of the card are transparent.');

        $dark = 0;
        for ($i = 0, $n = strlen($img['pixels']); $i < $n; $i += 4) {
            if (ord($img['pixels'][$i + 3]) > 128 && ord($img['pixels'][$i]) < 64) {
                $dark++;
            }
        }
        $share = $dark / (300 * 300);
        $this->assertGreaterThan(0.08, $share);
        $this->assertLessThan(0.6, $share);
    }

    public function test_all_shapes_and_frames_give_a_png(): void
    {
        foreach (QrCode::SHAPES as $shape) {
            foreach (QrCode::FRAMES as $frame) {
                $img = PngDecoder::decode((new QrCode(self::TEXT, ['shape' => $shape, 'frame' => $frame, 'color' => '#1d4ed8']))->toPng(120));
                $this->assertSame(120, $img['width'], "$shape/$frame");
            }
        }
    }

    public function test_a_transparent_background_gives_an_rgba_png(): void
    {
        // The width 300 is a multiple of 3. An earlier alpha check failed for these widths.
        foreach ([200, 300] as $size) {
            $img = PngDecoder::decode((new QrCode(self::TEXT, ['background' => 'transparent']))->toPng($size));

            $this->assertSame(6, $img['type'], "size $size");
            $this->assertSame(0, ord($img['pixels'][3]), 'The top-left pixel is transparent.');
        }
    }

    public function test_an_opaque_image_gives_an_rgb_png(): void
    {
        $root = new SvgNode('svg');
        $root->setAttribute('viewBox', '0 0 3 3');
        $rect = new SvgNode('rect');
        $rect->setAttribute('width', 3)->setAttribute('height', 3)->setAttribute('fill', '#ff0000');
        $root->appendChild($rect);

        $img = PngDecoder::decode(Rasterizer::png($root, 30));

        $this->assertSame(2, $img['type']);
        $this->assertSame(str_repeat("\xFF\x00\x00", 900), $img['pixels']);
    }

    public function test_the_module_colour_is_in_the_pixels(): void
    {
        $img = PngDecoder::decode((new QrCode(self::TEXT, ['color' => '#ff0000', 'pattern' => 'square']))->toPng(200));
        $this->assertStringContainsString("\xFF\x00\x00", $img['pixels']);
    }

    public function test_the_renderer_accepts_css_colours(): void
    {
        foreach (['#f00', '#ff000080', 'rgb(255, 0, 0)', 'rgba(255 0 0 / 50%)', 'hsl(0, 100%, 50%)', 'red', 'RebeccaPurple'] as $color) {
            $this->assertSame(64, PngDecoder::decode((new QrCode(self::TEXT, ['color' => $color]))->toPng(64))['width'], $color);
        }
    }

    public function test_an_unknown_colour_causes_an_exception(): void
    {
        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/Cannot draw the colour "var\(--brand\)"/');
        (new QrCode(self::TEXT, ['color' => 'var(--brand)']))->toPng(64);
    }

    public function test_a_bad_size_causes_an_exception(): void
    {
        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/size: expected 1-8192 pixels/');
        (new QrCode(self::TEXT))->toPng(0);
    }

    public function test_the_renderer_draws_an_edited_tree(): void
    {
        $root = new SvgNode('svg');
        $root->setAttribute('viewBox', '0 0 10 10');
        $rect = new SvgNode('rect');
        $rect->setAttribute('width', 10)->setAttribute('height', 5)->setAttribute('fill', '#0000ff');
        $root->appendChild($rect);
        $line = new SvgNode('path');
        $line->setAttribute('d', 'M0 7.5 H10')->setAttribute('stroke', '#00ff00')->setAttribute('stroke-width', 1);
        $root->appendChild($line);

        $img = PngDecoder::decode(Rasterizer::png($root, 10));
        $pixel = static fn (int $x, int $y): string => bin2hex(substr($img['pixels'], ($y * 10 + $x) * 4, 4));

        $this->assertSame('0000ffff', $pixel(5, 2), 'The rect is filled.');
        $this->assertSame('00ff00ff', $pixel(5, 7), 'The stroke is drawn.');
        $this->assertSame('00000000', $pixel(5, 9), 'The area below is empty.');
    }
}

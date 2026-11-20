<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use PHPUnit\Framework\TestCase;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;
use Wnikk\QrCodeCute\Exception\QrCodeException;
use Wnikk\QrCodeCute\QrCode;

final class OptionsTest extends TestCase
{
    private const TEXT = 'https://example.com';

    public function test_bad_options_cause_an_exception_with_a_clear_message(): void
    {
        $cases = [
            [self::TEXT, ['shape' => 'octagon'], '/shape: expected one of square, circle, triangle, hexagon - got "octagon"/'],
            [self::TEXT, ['pattern' => 'blobs'], '/pattern: expected one of/'],
            [self::TEXT, ['frame' => 'ornate'], '/frame: expected one of/'],
            [self::TEXT, ['eyeShape' => 'oval'], '/eyeShape: expected one of/'],
            [self::TEXT, ['errorCorrection' => 'X'], '/errorCorrection: expected one of L, M, Q, H/'],
            [self::TEXT, ['mask' => 9], '/mask: expected an integer 0-7/'],
            [self::TEXT, ['seed' => 'x'], '/seed: expected a number/'],
            [self::TEXT, ['colour' => 'red'], '/Unknown option "colour"/'],
            [self::TEXT, ['color' => ''], '/color: expected a colour string/'],
            [self::TEXT, ['eyeColor' => 5], '/eyeColor: expected a colour string or null/'],
        ];
        foreach ($cases as [$text, $options, $pattern]) {
            try {
                new QrCode($text, $options);
                $this->fail('No exception for '.json_encode($options));
            } catch (InvalidOptionException $e) {
                $this->assertMatchesRegularExpression($pattern, $e->getMessage());
            }
        }
    }

    public function test_all_exceptions_share_one_interface(): void
    {
        $this->expectException(QrCodeException::class);
        new QrCode(self::TEXT, ['shape' => 'octagon']);
    }

    public function test_null_gives_the_default(): void
    {
        $this->assertSame(QrCode::DEFAULTS, (new QrCode(self::TEXT, ['shape' => null]))->getOptions());
    }

    public function test_with_gives_a_changed_copy(): void
    {
        $a = new QrCode(self::TEXT, ['shape' => 'circle']);
        $b = $a->with(['pattern' => 'hearts']);

        $this->assertSame('dots', $a->getOptions()['pattern']);
        $this->assertSame('hearts', $b->getOptions()['pattern']);
        $this->assertSame('circle', $b->getOptions()['shape']);
        $this->assertSame(self::TEXT, $b->getText());
    }

    public function test_data_uris(): void
    {
        $qr = new QrCode(self::TEXT);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr->toDataUri('svg'));
        $this->assertStringStartsWith('data:image/png;base64,', $qr->toDataUri('png', 64));

        $this->expectException(InvalidOptionException::class);
        $qr->toDataUri('gif');
    }

    public function test_save_writes_the_format_of_the_extension(): void
    {
        $qr = new QrCode(self::TEXT);
        $dir = sys_get_temp_dir().'/qrcode-cute-'.bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $qr->save("$dir/a.svg");
            $qr->save("$dir/a.png", 64);
            $this->assertStringStartsWith('<?xml', (string) file_get_contents("$dir/a.svg"));
            $this->assertSame(64, PngDecoder::decode((string) file_get_contents("$dir/a.png"))['width']);
        } finally {
            @unlink("$dir/a.svg");
            @unlink("$dir/a.png");
            rmdir($dir);
        }

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageMatches('/expected a .svg, .png or .webp file/');
        $qr->save("$dir/a.gif");
    }
}

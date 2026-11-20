<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use PHPUnit\Framework\TestCase;
use Wnikk\QrCodeCute\Encoder\Encoder;
use Wnikk\QrCodeCute\Exception\DataTooLongException;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;
use Wnikk\QrCodeCute\QrCode;

final class EncoderTest extends TestCase
{
    private const TEXT = 'https://example.com';

    public function test_the_matrix_is_square_with_the_correct_version(): void
    {
        $matrix = (new QrCode(self::TEXT))->getMatrix();

        // At level H, a payload of 19 bytes needs version 3 (29 modules).
        $this->assertCount(29, $matrix);
        foreach ($matrix as $row) {
            $this->assertCount(29, $row);
        }
    }

    public function test_the_matrix_agrees_with_the_reference_encoder(): void
    {
        // Hashes of matrices from an independent reference encoder.
        $cases = [
            [self::TEXT, null, 'H', 'cdf47df09fe922e066954be0498ae8a1'],
            [self::TEXT, 5, 'H', '56a63fa1d3ca1eaa481095673984c8e5'],
            [str_repeat('A', 500), null, 'H', 'dbe55234aa66b052a7959dc08c175e5c'],
            [self::TEXT, null, 'L', '6f41e6f61e3f7f1a068f001d3d732624'],
            [self::TEXT, null, 'M', '48b7ff0dd6a0a9815c59e47307c3f58b'],
            [self::TEXT, null, 'Q', '4ffb67c8865570e312e58a91a7b550b6'],
            [str_repeat('B', 2900), null, 'L', '9033c632404fde7a9009f83498895019'],
            [str_repeat('C', 1500), null, 'M', '8f6be778a80c5d5279339a80a16c17b8'],
            [str_repeat('D', 700), null, 'Q', '681e86886ca0e7cbeab6ced5012e0657'],
        ];
        foreach ($cases as [$text, $mask, $level, $hash]) {
            $this->assertSame($hash, md5(self::serialize(Encoder::encode($text, $mask, $level))), "Level $level, ".strlen($text).' bytes');
        }
    }

    public function test_each_level_has_its_capacity(): void
    {
        $expected = ['L' => 2953, 'M' => 2331, 'Q' => 1663, 'H' => 1273];
        foreach ($expected as $level => $bytes) {
            $this->assertSame($bytes, Encoder::capacity($level));
            $this->assertCount(177, Encoder::encode(str_repeat('x', $bytes), null, $level), "Level $level uses version 40.");
        }
    }

    public function test_a_payload_over_the_capacity_causes_an_exception(): void
    {
        $this->expectException(DataTooLongException::class);
        $this->expectExceptionMessageMatches('/1274 bytes is too long for error correction level H.*Use a lower errorCorrection level/');
        Encoder::encode(str_repeat('x', 1274));
    }

    public function test_a_payload_over_the_level_l_capacity_causes_an_exception(): void
    {
        $this->expectException(DataTooLongException::class);
        $this->expectExceptionMessageMatches('/2954 bytes is too long for error correction level L \(maximum 2953 bytes\)$/');
        Encoder::encode(str_repeat('x', 2954), null, 'L');
    }

    public function test_a_lower_level_gives_a_smaller_matrix(): void
    {
        $text = str_repeat('x', 1000);
        $sizes = [];
        foreach (Encoder::LEVELS as $level) {
            $sizes[] = count(Encoder::encode($text, null, $level));
        }
        $sorted = $sizes;
        sort($sorted);
        $this->assertSame($sorted, $sizes);
        $this->assertLessThan(end($sizes), $sizes[0]);
    }

    public function test_an_unknown_level_causes_an_exception(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageMatches('/errorCorrection: expected one of L, M, Q, H/');
        Encoder::encode(self::TEXT, null, 'X');
    }

    public function test_utf8_text_is_encoded_as_bytes(): void
    {
        // "Привет" has 6 characters and 12 bytes.
        $this->assertSame(count(Encoder::encode(str_repeat('a', 12))), count(Encoder::encode('Привет')));
        $this->assertNotSame(count(Encoder::encode('abcdef')), count(Encoder::encode('Привет')));
    }

    /** @param bool[][] $matrix */
    private static function serialize(array $matrix): string
    {
        $s = '';
        foreach ($matrix as $row) {
            $s .= implode('', array_map('intval', $row))."\n";
        }

        return $s;
    }
}

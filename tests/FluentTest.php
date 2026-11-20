<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use PHPUnit\Framework\TestCase;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;
use Wnikk\QrCodeCute\Payload;
use Wnikk\QrCodeCute\QrCode;

final class FluentTest extends TestCase
{
    private const TEXT = 'https://example.com';

    public function test_the_setters_give_a_new_object(): void
    {
        $base = new QrCode(self::TEXT);
        $circle = $base->shape('circle')->color('#ff0000');

        $this->assertNotSame($base, $circle);
        $this->assertSame('square', $base->getOptions()['shape']);
        $this->assertSame('circle', $circle->getOptions()['shape']);
        $this->assertSame('#ff0000', $circle->getOptions()['color']);
        $this->assertSame(self::TEXT, $circle->getText());
    }

    public function test_all_setters_change_their_option(): void
    {
        $qr = (new QrCode)
            ->shape('hexagon')->frame('thin')->pattern('stars')->eyeShape('square')
            ->color('#111111')->background('#eeeeee')->eyeColor('#222222')->frameColor('#333333')
            ->errorCorrection('Q')->seed(7)->mask(3);

        $this->assertSame([
            'shape' => 'hexagon',
            'frame' => 'thin',
            'pattern' => 'stars',
            'eyeShape' => 'square',
            'color' => '#111111',
            'background' => '#eeeeee',
            'eyeColor' => '#222222',
            'frameColor' => '#333333',
            'errorCorrection' => 'Q',
            'seed' => 7,
            'mask' => 3,
        ], $qr->getOptions());
    }

    public function test_the_setters_and_the_options_array_give_the_same_code(): void
    {
        $options = ['shape' => 'circle', 'frame' => 'double', 'pattern' => 'rounded', 'color' => '#1d4ed8'];
        $fluent = (new QrCode)->shape('circle')->frame('double')->pattern('rounded')->color('#1d4ed8')->text(self::TEXT);

        $this->assertSame(QrCode::svg(self::TEXT, $options), $fluent->toSvg());
        $this->assertSame((string) $fluent, $fluent->toSvg());
    }

    public function test_the_static_methods_give_each_format(): void
    {
        $this->assertStringStartsWith('<svg', QrCode::svg(self::TEXT));
        $this->assertStringStartsWith("\x89PNG", QrCode::png(self::TEXT, [], 64));
        $this->assertStringStartsWith('RIFF', QrCode::webp(self::TEXT, [], 64));
    }

    public function test_the_content_methods_set_the_payload(): void
    {
        $qr = (new QrCode)->shape('circle');
        $cases = [
            [$qr->wifi('Office', 'secret'), Payload::wifi('Office', 'secret')],
            [$qr->email('info@example.com', 'Hi'), Payload::email('info@example.com', 'Hi')],
            [$qr->phone('+15551234567'), Payload::phone('+15551234567')],
            [$qr->sms('+15551234567', 'Hi'), Payload::sms('+15551234567', 'Hi')],
            [$qr->geo(1.5, 2.5), Payload::geo(1.5, 2.5)],
            [$qr->vcard(name: 'Ivan'), Payload::vcard(name: 'Ivan')],
            [$qr->calendar('Event', '2026-12-01 10:00 UTC'), Payload::calendar('Event', '2026-12-01 10:00 UTC')],
            [$qr->bitcoin('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', '0.1'), Payload::bitcoin('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', '0.1')],
            [$qr->litecoin('ltc1qg82hzx0t9tqsfp5xc4axh5l9hh0w04x9j5w0r5'), Payload::litecoin('ltc1qg82hzx0t9tqsfp5xc4axh5l9hh0w04x9j5w0r5')],
            [$qr->ethereum('0x89205A3A3b2A69De6Dbf7f01ED13B2108B2c43e7', 5), Payload::ethereum('0x89205A3A3b2A69De6Dbf7f01ED13B2108B2c43e7', 5)],
        ];
        foreach ($cases as [$code, $payload]) {
            $this->assertSame($payload, $code->getText());
            $this->assertSame('circle', $code->getOptions()['shape']);
        }
        $this->assertSame('', $qr->getText(), 'A content method does not change the design object.');
    }

    public function test_a_new_text_gives_a_new_matrix(): void
    {
        $short = new QrCode('a');
        $long = $short->text(str_repeat('a', 100));

        $this->assertCount(21, $short->getMatrix());
        $this->assertGreaterThan(21, count($long->getMatrix()));
    }

    public function test_an_output_without_a_text_causes_an_exception(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageMatches('/text: expected a non-empty string/');
        (new QrCode)->shape('circle')->toSvg();
    }

    public function test_bad_values_cause_an_exception_at_once(): void
    {
        foreach ([
            fn () => (new QrCode)->shape('octagon'),
            fn () => (new QrCode)->mask(9),
            fn () => (new QrCode)->wifi('Office', 'secret', 'WPA3'),
            fn () => new QrCode(self::TEXT, ['colour' => 'red']),
        ] as $call) {
            try {
                $call();
                $this->fail('No exception.');
            } catch (InvalidOptionException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use PHPUnit\Framework\TestCase;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;
use Wnikk\QrCodeCute\Exception\RenderException;
use Wnikk\QrCodeCute\Generator;
use Wnikk\QrCodeCute\Payload;
use Wnikk\QrCodeCute\QrCode;

final class GeneratorTest extends TestCase
{
    private const TEXT = 'https://example.com';

    public function test_the_setters_give_a_new_instance(): void
    {
        $base = new Generator;
        $circle = $base->shape('circle')->color('#ff0000')->size(256)->format('png');

        $this->assertSame('square', $base->getOptions()['shape']);
        $this->assertSame('svg', $base->getFormat());
        $this->assertSame('circle', $circle->getOptions()['shape']);
        $this->assertSame('#ff0000', $circle->getOptions()['color']);
        $this->assertSame(256, $circle->getSize());
        $this->assertSame('image/png', $circle->mimeType());
    }

    public function test_all_setters_change_their_option(): void
    {
        $generator = (new Generator)
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
        ], $generator->getOptions());
    }

    public function test_generate_gives_the_format(): void
    {
        $generator = (new Generator)->shape('circle');

        $this->assertSame(QrCode::svg(self::TEXT, ['shape' => 'circle']), $generator->generate(self::TEXT));
        $this->assertStringStartsWith("\x89PNG", $generator->format('png')->size(64)->generate(self::TEXT));
        $this->assertStringStartsWith('RIFF', $generator->format('webp')->size(64)->generate(self::TEXT));
        $this->assertStringStartsWith('data:image/png;base64,', $generator->format('png')->size(64)->dataUri(self::TEXT));
    }

    public function test_generate_writes_a_file(): void
    {
        $path = sys_get_temp_dir().'/qrcode-cute-'.bin2hex(random_bytes(4)).'.svg';
        try {
            $data = (new Generator)->generate(self::TEXT, $path);
            $this->assertSame($data, file_get_contents($path));
            $this->assertStringStartsWith('<?xml', $data);
        } finally {
            @unlink($path);
        }
    }

    public function test_content_type_methods_use_the_payloads(): void
    {
        $generator = new Generator;
        $cases = [
            [$generator->wifi('Office', 'secret'), Payload::wifi('Office', 'secret')],
            [$generator->email('info@example.com', 'Hi'), Payload::email('info@example.com', 'Hi')],
            [$generator->phone('+15551234567'), Payload::phone('+15551234567')],
            [$generator->sms('+15551234567', 'Hi'), Payload::sms('+15551234567', 'Hi')],
            [$generator->geo(1.5, 2.5), Payload::geo(1.5, 2.5)],
            [$generator->vcard(name: 'Ivan'), Payload::vcard(name: 'Ivan')],
            [$generator->calendar('Event', '2026-12-01 10:00 UTC'), Payload::calendar('Event', '2026-12-01 10:00 UTC')],
            [$generator->bitcoin('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', '0.1'), Payload::bitcoin('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', '0.1')],
            [$generator->litecoin('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'), Payload::litecoin('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq')],
            [$generator->ethereum('0x89205A3A3b2A69De6Dbf7f01ED13B2108B2c43e7', 5), Payload::ethereum('0x89205A3A3b2A69De6Dbf7f01ED13B2108B2c43e7', 5)],
        ];
        foreach ($cases as [$output, $payload]) {
            $this->assertSame($generator->generate($payload), $output);
        }
    }

    public function test_bad_values_cause_an_exception_at_once(): void
    {
        foreach ([
            fn () => (new Generator)->shape('octagon'),
            fn () => (new Generator)->format('gif'),
            fn () => new Generator(['colour' => 'red']),
        ] as $call) {
            try {
                $call();
                $this->fail('No exception.');
            } catch (InvalidOptionException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(RenderException::class);
        (new Generator)->size(0);
    }
}

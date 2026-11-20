<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;
use Wnikk\QrCodeCute\Payload;

final class PayloadTest extends TestCase
{
    private const BTC = 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq';

    private const ETH = '0x89205A3A3b2A69De6Dbf7f01ED13B2108B2c43e7';

    public function test_wifi(): void
    {
        $this->assertSame('WIFI:T:WPA;S:Office;P:secret;;', Payload::wifi('Office', 'secret'));
        $this->assertSame('WIFI:T:WEP;S:Office;P:secret;H:true;;', Payload::wifi('Office', 'secret', 'WEP', true));
        $this->assertSame('WIFI:T:nopass;S:Guest;;', Payload::wifi('Guest'));
        $this->assertSame('WIFI:T:WPA;S:a\;b\,c;P:x\:y\\\\z\";;', Payload::wifi('a;b,c', 'x:y\z"'));
    }

    public function test_email(): void
    {
        $this->assertSame('mailto:info@example.com', Payload::email('info@example.com'));
        $this->assertSame(
            'mailto:info@example.com?cc=a@example.com,b@example.com&bcc=c@example.com&subject=Hello%20there&body=Line%201%0ALine%202',
            Payload::email('info@example.com', 'Hello there', "Line 1\nLine 2", ['a@example.com', 'b@example.com'], 'c@example.com')
        );
    }

    public function test_phone_and_sms(): void
    {
        $this->assertSame('tel:+15551234567', Payload::phone('+1 (555) 123 4567'));
        $this->assertSame('tel:+7495000-00-00', Payload::phone('+7 495 000-00-00'));
        $this->assertSame('SMSTO:+15551234567', Payload::sms('+15551234567'));
        $this->assertSame('SMSTO:+15551234567:Meet at 5', Payload::sms('+15551234567', 'Meet at 5'));
    }

    public function test_geo(): void
    {
        $this->assertSame('geo:55.751244,37.618423', Payload::geo(55.751244, 37.618423));
        $this->assertSame('geo:-33.8688,151.2093?q=Sydney%20Opera', Payload::geo(-33.8688, 151.2093, 'Sydney Opera'));
        $this->assertSame('geo:0,0', Payload::geo(0.0, -0.0));
    }

    public function test_vcard(): void
    {
        $expected = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:Petrov;Ivan;;;',
            'FN:Ivan Petrov',
            'ORG:Acme\, Inc.',
            'TEL:+15551234567',
            'EMAIL:ivan@example.com',
            'ADR:;;Main St 1\; Springfield;;;;',
            'NOTE:Line 1\nLine 2',
            'END:VCARD',
        ]);
        $this->assertSame($expected, Payload::vcard(
            firstName: 'Ivan',
            lastName: 'Petrov',
            organization: 'Acme, Inc.',
            phone: '+1 555 123 4567',
            email: 'ivan@example.com',
            address: 'Main St 1; Springfield',
            note: "Line 1\nLine 2",
        ));
        $this->assertStringContainsString("FN:Acme Support\r\n", Payload::vcard(name: 'Acme Support'));
    }

    public function test_calendar(): void
    {
        $start = new DateTimeImmutable('2026-12-01 10:00', new DateTimeZone('Europe/Berlin'));
        $this->assertSame(
            implode("\r\n", [
                'BEGIN:VEVENT',
                'SUMMARY:Team meeting',
                'DTSTART:20261201T090000Z',
                'DTEND:20261201T100000Z',
                'LOCATION:Room 1\, floor 2',
                'END:VEVENT',
            ]),
            Payload::calendar('Team meeting', $start, location: 'Room 1, floor 2')
        );
        $this->assertStringContainsString(
            "DTSTART;VALUE=DATE:20261231\r\nDTEND;VALUE=DATE:20270101",
            Payload::calendar('Holiday', '2026-12-31', allDay: true)
        );
    }

    public function test_crypto_payments(): void
    {
        $this->assertSame('bitcoin:'.self::BTC, Payload::bitcoin(self::BTC));
        $this->assertSame('bitcoin:'.self::BTC.'?amount=0.0012&label=Shop&message=Order%20%2342', Payload::bitcoin(self::BTC, 0.0012, 'Shop', 'Order #42'));
        $this->assertSame('bitcoin:'.self::BTC.'?amount=0.00000001', Payload::bitcoin(self::BTC, 1.0E-8));
        $this->assertSame('litecoin:'.self::BTC.'?amount=1.5', Payload::litecoin(self::BTC, '1.5'));
        $this->assertSame('ethereum:'.self::ETH, Payload::ethereum(self::ETH));
        $this->assertSame('ethereum:'.self::ETH.'@1?value=1000000000000000000', Payload::ethereum(self::ETH, '1000000000000000000', 1));
    }

    public function test_bad_values_cause_an_exception(): void
    {
        $cases = [
            'wifi: the ssid' => fn () => Payload::wifi(''),
            'wifi: expected an encryption' => fn () => Payload::wifi('a', 'b', 'WPA9'),
            'email: expected an e-mail' => fn () => Payload::email('not-an-address'),
            'email cc: expected an e-mail' => fn () => Payload::email('a@example.com', cc: 'x'),
            'phone: expected a phone number' => fn () => Payload::phone('call me'),
            'geo: the latitude' => fn () => Payload::geo(91.0, 0.0),
            'geo: the longitude' => fn () => Payload::geo(0.0, 181.0),
            'vcard: give a name' => fn () => Payload::vcard(),
            'calendar: the end must not be before' => fn () => Payload::calendar('a', '2026-01-02', '2026-01-01'),
            'calendar: the start is not a date' => fn () => Payload::calendar('a', 'not a date'),
            'bitcoin: the address' => fn () => Payload::bitcoin('bad address'),
            'bitcoin: the amount' => fn () => Payload::bitcoin(self::BTC, -1),
            'ethereum: expected an address' => fn () => Payload::ethereum('0x123'),
            'ethereum: the value' => fn () => Payload::ethereum(self::ETH, '1.5'),
            'ethereum: the chain ID' => fn () => Payload::ethereum(self::ETH, null, 0),
        ];
        foreach ($cases as $message => $call) {
            try {
                $call();
                $this->fail('No exception for: '.$message);
            } catch (InvalidOptionException $e) {
                $this->assertStringStartsWith($message, $e->getMessage());
            }
        }
    }
}

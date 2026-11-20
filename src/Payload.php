<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;

/**
 * Make payload strings for the usual QR content types.
 *
 * Each method gives a string. Give the string to QrCode or to Generator:
 *
 *     $text = Payload::wifi('Office', 'secret');
 *     $svg = QrCode::svg($text);
 *
 * The formats follow the formats that phone cameras and the ZXing library
 * read. Use named arguments for the optional parameters.
 */
final class Payload
{
    /**
     * The Wi-Fi encryption types.
     *
     * @var string[]
     */
    public const WIFI_ENCRYPTION = ['WPA', 'WEP', 'nopass'];

    /**
     * Make a Wi-Fi network payload: "WIFI:T:WPA;S:ssid;P:password;;".
     *
     * @param  string  $ssid  The network name.
     * @param  string|null  $password  The password. Use null for an open network.
     * @param  string  $encryption  One of WIFI_ENCRYPTION. An open network always uses "nopass".
     * @param  bool  $hidden  Set to true if the network does not show its name.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function wifi(string $ssid, ?string $password = null, string $encryption = 'WPA', bool $hidden = false): string
    {
        if ($ssid === '') {
            throw new InvalidOptionException('wifi: the ssid must not be empty');
        }
        if (! in_array($encryption, self::WIFI_ENCRYPTION, true)) {
            throw new InvalidOptionException(sprintf(
                'wifi: expected an encryption of %s - got "%s"',
                implode(', ', self::WIFI_ENCRYPTION),
                $encryption
            ));
        }
        if ($password === null || $password === '') {
            $encryption = 'nopass';
        }

        $out = 'WIFI:T:'.$encryption.';S:'.self::escapeWifi($ssid).';';
        if ($encryption !== 'nopass') {
            $out .= 'P:'.self::escapeWifi((string) $password).';';
        }
        if ($hidden) {
            $out .= 'H:true;';
        }

        return $out.';';
    }

    /**
     * Make an e-mail payload: "mailto:address?subject=...&body=...".
     *
     * @param  string  $to  The address of the recipient.
     * @param  string|null  $subject
     * @param  string|null  $body
     * @param  string|string[]|null  $cc  One address or a list of addresses.
     * @param  string|string[]|null  $bcc  One address or a list of addresses.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function email(string $to, ?string $subject = null, ?string $body = null, string|array|null $cc = null, string|array|null $bcc = null): string
    {
        self::checkEmail($to, 'email');

        $query = [];
        foreach (['cc' => $cc, 'bcc' => $bcc] as $key => $list) {
            if ($list === null || $list === [] || $list === '') {
                continue;
            }
            $addresses = (array) $list;
            foreach ($addresses as $address) {
                self::checkEmail($address, 'email '.$key);
            }
            $query[$key] = implode(',', array_map([self::class, 'encodeAddress'], $addresses));
        }
        if ($subject !== null && $subject !== '') {
            $query['subject'] = rawurlencode($subject);
        }
        if ($body !== null && $body !== '') {
            $query['body'] = rawurlencode($body);
        }

        return 'mailto:'.self::encodeAddress($to).self::query($query);
    }

    /**
     * Make a phone call payload: "tel:+15551234567".
     *
     * @param  string  $number  The phone number. Spaces and the characters "()." are removed.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function phone(string $number): string
    {
        return 'tel:'.self::cleanPhone($number, 'phone');
    }

    /**
     * Make a text message payload: "SMSTO:+15551234567:message".
     *
     * @param  string  $number  The phone number of the recipient.
     * @param  string|null  $message  The text of the message.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function sms(string $number, ?string $message = null): string
    {
        $out = 'SMSTO:'.self::cleanPhone($number, 'sms');

        return $message === null || $message === '' ? $out : $out.':'.$message;
    }

    /**
     * Make a geographic location payload: "geo:latitude,longitude".
     *
     * @param  float  $latitude  The latitude, from -90 to 90.
     * @param  float  $longitude  The longitude, from -180 to 180.
     * @param  string|null  $label  A name for the location. Some map applications show it.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function geo(float $latitude, float $longitude, ?string $label = null): string
    {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidOptionException('geo: the latitude must be from -90 to 90 - got '.$latitude);
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidOptionException('geo: the longitude must be from -180 to 180 - got '.$longitude);
        }

        $out = 'geo:'.self::decimal($latitude).','.self::decimal($longitude);

        return $label === null || $label === '' ? $out : $out.'?q='.rawurlencode($label);
    }

    /**
     * Make a contact card payload (vCard 3.0).
     *
     * @param  string|null  $name  The full name. If it is null, the first and the last name make it.
     * @param  string|null  $firstName
     * @param  string|null  $lastName
     * @param  string|null  $organization
     * @param  string|null  $title  The job title.
     * @param  string|string[]|null  $phone  One phone number or a list of numbers.
     * @param  string|string[]|null  $email  One address or a list of addresses.
     * @param  string|null  $url  The website.
     * @param  string|null  $address  The postal address, as one line.
     * @param  string|null  $note
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function vcard(
        ?string $name = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $organization = null,
        ?string $title = null,
        string|array|null $phone = null,
        string|array|null $email = null,
        ?string $url = null,
        ?string $address = null,
        ?string $note = null,
    ): string {
        $fullName = $name ?? trim(($firstName ?? '').' '.($lastName ?? ''));
        if ($fullName === '') {
            throw new InvalidOptionException('vcard: give a name, a first name or a last name');
        }

        $lines = ['BEGIN:VCARD', 'VERSION:3.0'];
        $lines[] = 'N:'.self::escapeText($lastName ?? '').';'.self::escapeText($firstName ?? '').';;;';
        $lines[] = 'FN:'.self::escapeText($fullName);
        if ($organization !== null && $organization !== '') {
            $lines[] = 'ORG:'.self::escapeText($organization);
        }
        if ($title !== null && $title !== '') {
            $lines[] = 'TITLE:'.self::escapeText($title);
        }
        foreach ((array) $phone as $number) {
            $lines[] = 'TEL:'.self::cleanPhone($number, 'vcard');
        }
        foreach ((array) $email as $mail) {
            self::checkEmail($mail, 'vcard');
            $lines[] = 'EMAIL:'.$mail;
        }
        if ($url !== null && $url !== '') {
            $lines[] = 'URL:'.self::escapeText($url);
        }
        if ($address !== null && $address !== '') {
            $lines[] = 'ADR:;;'.self::escapeText($address).';;;;';
        }
        if ($note !== null && $note !== '') {
            $lines[] = 'NOTE:'.self::escapeText($note);
        }
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }

    /**
     * Make a calendar event payload (iCalendar VEVENT).
     *
     * A time with a time zone changes to UTC. An all-day event uses dates only.
     *
     * @param  string  $summary  The title of the event.
     * @param  DateTimeInterface|string  $start  The start. A string must be a format that DateTimeImmutable reads.
     * @param  DateTimeInterface|string|null  $end  The end. If it is null, the event takes one hour, or one day for an all-day event.
     * @param  string|null  $location
     * @param  string|null  $description
     * @param  bool  $allDay  Set to true for an event without a time.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function calendar(
        string $summary,
        DateTimeInterface|string $start,
        DateTimeInterface|string|null $end = null,
        ?string $location = null,
        ?string $description = null,
        bool $allDay = false,
    ): string {
        if ($summary === '') {
            throw new InvalidOptionException('calendar: the summary must not be empty');
        }
        $from = self::date($start, 'start');
        $to = $end === null ? $from->modify($allDay ? '+1 day' : '+1 hour') : self::date($end, 'end');
        if ($to < $from) {
            throw new InvalidOptionException('calendar: the end must not be before the start');
        }

        $lines = ['BEGIN:VEVENT', 'SUMMARY:'.self::escapeText($summary)];
        if ($allDay) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$from->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$to->format('Ymd');
        } else {
            $utc = new DateTimeZone('UTC');
            $lines[] = 'DTSTART:'.$from->setTimezone($utc)->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$to->setTimezone($utc)->format('Ymd\THis\Z');
        }
        if ($location !== null && $location !== '') {
            $lines[] = 'LOCATION:'.self::escapeText($location);
        }
        if ($description !== null && $description !== '') {
            $lines[] = 'DESCRIPTION:'.self::escapeText($description);
        }
        $lines[] = 'END:VEVENT';

        return implode("\r\n", $lines);
    }

    /**
     * Make a Bitcoin payment payload (BIP 21): "bitcoin:address?amount=...".
     *
     * @param  string  $address  The Bitcoin address.
     * @param  float|int|string|null  $amount  The amount in BTC. Use a string for an exact decimal value.
     * @param  string|null  $label  A name for the recipient.
     * @param  string|null  $message  A note for the payment.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function bitcoin(string $address, float|int|string|null $amount = null, ?string $label = null, ?string $message = null): string
    {
        return self::bip21('bitcoin', $address, $amount, $label, $message);
    }

    /**
     * Make a Litecoin payment payload: "litecoin:address?amount=...".
     *
     * @param  string  $address  The Litecoin address.
     * @param  float|int|string|null  $amount  The amount in LTC. Use a string for an exact decimal value.
     * @param  string|null  $label  A name for the recipient.
     * @param  string|null  $message  A note for the payment.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function litecoin(string $address, float|int|string|null $amount = null, ?string $label = null, ?string $message = null): string
    {
        return self::bip21('litecoin', $address, $amount, $label, $message);
    }

    /**
     * Make an Ethereum payment payload (EIP 681): "ethereum:0x...@1?value=...".
     *
     * @param  string  $address  The address: "0x" and 40 hexadecimal digits.
     * @param  int|string|null  $value  The amount in wei, as an integer. Use a string for a large value.
     * @param  int|null  $chainId  The chain ID, for example 1 for the main network.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function ethereum(string $address, int|string|null $value = null, ?int $chainId = null): string
    {
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            throw new InvalidOptionException('ethereum: expected an address of "0x" and 40 hexadecimal digits - got "'.$address.'"');
        }
        $out = 'ethereum:'.$address;
        if ($chainId !== null) {
            if ($chainId < 1) {
                throw new InvalidOptionException('ethereum: the chain ID must be a positive integer - got '.$chainId);
            }
            $out .= '@'.$chainId;
        }
        if ($value === null) {
            return $out;
        }
        $value = (string) $value;
        if (! preg_match('/^\d+$/', $value)) {
            throw new InvalidOptionException('ethereum: the value must be an integer number of wei - got "'.$value.'"');
        }

        return $out.'?value='.$value;
    }

    /**
     * Make a BIP 21 payment URI.
     *
     * @param  string  $scheme
     * @param  string  $address
     * @param  float|int|string|null  $amount
     * @param  string|null  $label
     * @param  string|null  $message
     * @return string
     *
     * @throws InvalidOptionException
     */
    private static function bip21(string $scheme, string $address, float|int|string|null $amount, ?string $label, ?string $message): string
    {
        if (! preg_match('/^[a-zA-Z0-9]{14,90}$/', $address)) {
            throw new InvalidOptionException($scheme.': the address must have only letters and digits - got "'.$address.'"');
        }

        $query = [];
        if ($amount !== null) {
            $text = is_float($amount) ? self::decimal($amount, 8) : (string) $amount;
            if (! preg_match('/^\d+(\.\d+)?$/', $text) || (float) $text <= 0) {
                throw new InvalidOptionException($scheme.': the amount must be a positive decimal number - got "'.$text.'"');
            }
            $query['amount'] = $text;
        }
        if ($label !== null && $label !== '') {
            $query['label'] = rawurlencode($label);
        }
        if ($message !== null && $message !== '') {
            $query['message'] = rawurlencode($message);
        }

        return $scheme.':'.$address.self::query($query);
    }

    /**
     * Join query parameters. The values must be encoded already.
     *
     * @param  array  $query
     * @param  array<string, string>  $query
     * @return string
     */
    private static function query(array $query): string
    {
        if ($query === []) {
            return '';
        }
        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return '?'.implode('&', $pairs);
    }

    /**
     * Write a float without an exponent and without trailing zeros.
     *
     * @param  float  $value
     * @param  int  $decimals
     * @return string
     */
    private static function decimal(float $value, int $decimals = 7): string
    {
        $text = rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    /**
     * Escape the Wi-Fi special characters \ ; , : and " with a backslash.
     *
     * @param  string  $value
     * @return string
     */
    private static function escapeWifi(string $value): string
    {
        return (string) preg_replace('/([\\\\;,:"])/', '\\\\$1', $value);
    }

    /**
     * Escape a vCard or iCalendar text value.
     *
     * @param  string  $value
     * @return string
     */
    private static function escapeText(string $value): string
    {
        $value = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);

        return str_replace(["\r\n", "\r", "\n"], '\\n', $value);
    }

    /**
     * Percent-encode an e-mail address, but keep the "@" sign.
     *
     * @param  string  $address
     * @return string
     */
    private static function encodeAddress(string $address): string
    {
        return str_replace('%40', '@', rawurlencode($address));
    }

    /**
     * Make sure that a value is an e-mail address.
     *
     * @param  mixed  $address
     * @param  string  $type
     * @return void
     *
     * @throws InvalidOptionException
     */
    private static function checkEmail(mixed $address, string $type): void
    {
        if (! is_string($address) || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidOptionException($type.': expected an e-mail address - got '.json_encode($address));
        }
    }

    /**
     * Remove spaces and the characters "()." from a phone number, then check it.
     *
     * @param  mixed  $number
     * @param  string  $type
     * @return string
     *
     * @throws InvalidOptionException
     */
    private static function cleanPhone(mixed $number, string $type): string
    {
        $clean = is_string($number) ? (string) preg_replace('/[\s().]+/', '', $number) : '';
        if (! preg_match('/^\+?[0-9*#-]{2,}$/', $clean)) {
            throw new InvalidOptionException($type.': expected a phone number - got '.json_encode($number));
        }

        return $clean;
    }

    /**
     * Read a date and a time.
     *
     * @param  DateTimeInterface|string  $value
     * @param  string  $name
     * @return DateTimeImmutable
     *
     * @throws InvalidOptionException
     */
    private static function date(DateTimeInterface|string $value, string $name): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidOptionException('calendar: the '.$name.' is not a date - got "'.$value.'"');
        }
    }
}

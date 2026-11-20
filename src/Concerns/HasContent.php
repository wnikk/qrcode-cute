<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Concerns;

use DateTimeInterface;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;
use Wnikk\QrCodeCute\Payload;

/**
 * The content methods of QrCode. Each method sets the payload text in the
 * format of a content type and gives a new object. The original object does not change.
 *
 * Only QrCode uses this trait. It sets the $text and $matrix properties of QrCode.
 *
 * @internal
 */
trait HasContent
{
    /**
     * Set the payload text.
     *
     * @param  string  $text  The payload, as UTF-8.
     * @return self
     */
    public function text(string $text): self
    {
        $copy = clone $this;
        $copy->text = $text;
        $copy->matrix = null;

        return $copy;
    }

    /**
     * Set a Wi-Fi network as the payload.
     *
     * @param  string  $ssid  The network name.
     * @param  string|null  $password
     * @param  string  $encryption  'WPA', 'WEP' or 'nopass'.
     * @param  bool  $hidden  Set to true for a network that does not show its name.
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function wifi(string $ssid, ?string $password = null, string $encryption = 'WPA', bool $hidden = false): self
    {
        return $this->text(Payload::wifi($ssid, $password, $encryption, $hidden));
    }

    /**
     * Set an e-mail message as the payload.
     *
     * @param  string  $to
     * @param  string|null  $subject
     * @param  string|null  $body
     * @param  string|string[]|null  $cc
     * @param  string|string[]|null  $bcc
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function email(string $to, ?string $subject = null, ?string $body = null, string|array|null $cc = null, string|array|null $bcc = null): self
    {
        return $this->text(Payload::email($to, $subject, $body, $cc, $bcc));
    }

    /**
     * Set a phone call as the payload.
     *
     * @param  string  $number
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function phone(string $number): self
    {
        return $this->text(Payload::phone($number));
    }

    /**
     * Set a text message as the payload.
     *
     * @param  string  $number
     * @param  string|null  $message
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function sms(string $number, ?string $message = null): self
    {
        return $this->text(Payload::sms($number, $message));
    }

    /**
     * Set a geographic location as the payload.
     *
     * @param  float  $latitude  -90 to 90.
     * @param  float  $longitude  -180 to 180.
     * @param  string|null  $label
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function geo(float $latitude, float $longitude, ?string $label = null): self
    {
        return $this->text(Payload::geo($latitude, $longitude, $label));
    }

    /**
     * Set a contact card (vCard 3.0) as the payload. Use named arguments.
     *
     * @param  string|null  $name  The full name. The default is the first and the last name.
     * @param  string|null  $firstName
     * @param  string|null  $lastName
     * @param  string|null  $organization
     * @param  string|null  $title
     * @param  string|string[]|null  $phone
     * @param  string|string[]|null  $email
     * @param  string|null  $url
     * @param  string|null  $address
     * @param  string|null  $note
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function vcard(
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
    ): self {
        return $this->text(Payload::vcard($name, $firstName, $lastName, $organization, $title, $phone, $email, $url, $address, $note));
    }

    /**
     * Set a calendar event (iCalendar VEVENT) as the payload.
     *
     * @param  string  $summary
     * @param  DateTimeInterface|string  $start
     * @param  DateTimeInterface|string|null  $end
     * @param  string|null  $location
     * @param  string|null  $description
     * @param  bool  $allDay
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function calendar(
        string $summary,
        DateTimeInterface|string $start,
        DateTimeInterface|string|null $end = null,
        ?string $location = null,
        ?string $description = null,
        bool $allDay = false,
    ): self {
        return $this->text(Payload::calendar($summary, $start, $end, $location, $description, $allDay));
    }

    /**
     * Set a Bitcoin payment (BIP-21) as the payload.
     *
     * @param  string  $address
     * @param  float|int|string|null  $amount  In BTC.
     * @param  string|null  $label
     * @param  string|null  $message
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function bitcoin(string $address, float|int|string|null $amount = null, ?string $label = null, ?string $message = null): self
    {
        return $this->text(Payload::bitcoin($address, $amount, $label, $message));
    }

    /**
     * Set a Litecoin payment as the payload.
     *
     * @param  string  $address
     * @param  float|int|string|null  $amount  In LTC.
     * @param  string|null  $label
     * @param  string|null  $message
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function litecoin(string $address, float|int|string|null $amount = null, ?string $label = null, ?string $message = null): self
    {
        return $this->text(Payload::litecoin($address, $amount, $label, $message));
    }

    /**
     * Set an Ethereum payment (EIP-681) as the payload.
     *
     * @param  string  $address
     * @param  int|string|null  $value  In wei.
     * @param  int|null  $chainId
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function ethereum(string $address, int|string|null $value = null, ?int $chainId = null): self
    {
        return $this->text(Payload::ethereum($address, $value, $chainId));
    }
}

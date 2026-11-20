<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute;

use DateTimeInterface;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;
use Wnikk\QrCodeCute\Exception\QrCodeException;
use Wnikk\QrCodeCute\Exception\RenderException;

/**
 * A fluent builder that keeps the options, the size and the format.
 *
 *     $png = (new Generator())->shape('circle')->format('png')->size(512)->generate('https://example.com');
 *
 * The builder is immutable: each setter gives a new instance. Thus one
 * instance can be a shared service, for example in the Laravel container.
 */
final class Generator
{
    /**
     * The QR code options, with the defaults filled in.
     *
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * The width and the height of raster images, in pixels.
     *
     * @var int
     */
    private int $size;

    /**
     * The output format. One of the QrCode::FORMATS keys.
     *
     * @var string
     */
    private string $format;

    /**
     * Create a generator.
     *
     * @param  array  $options
     * @param  int  $size  The width and the height of raster images, in pixels.
     * @param  string  $format  One of the QrCode::FORMATS keys.
     * @param  array<string, mixed>  $options  The QR code options. Refer to QrCode::DEFAULTS.
     * @return void
     *
     * @throws QrCodeException
     */
    public function __construct(array $options = [], int $size = 1024, string $format = 'svg')
    {
        $this->options = QrCode::resolveOptions($options);
        $this->size = self::checkSize($size);
        QrCode::mimeType($format);
        $this->format = $format;
    }

    /**
     * Set the outline of the code: square, circle, triangle or hexagon.
     *
     * @param  string  $shape
     * @return self
     */
    public function shape(string $shape): self
    {
        return $this->withOptions(['shape' => $shape]);
    }

    /**
     * Set the frame: none, thin, double or organic.
     *
     * @param  string  $frame
     * @return self
     */
    public function frame(string $frame): self
    {
        return $this->withOptions(['frame' => $frame]);
    }

    /**
     * Set the module pattern. Refer to QrCode::PATTERNS.
     *
     * @param  string  $pattern
     * @return self
     */
    public function pattern(string $pattern): self
    {
        return $this->withOptions(['pattern' => $pattern]);
    }

    /**
     * Set the shape of the finder eyes: circle, rounded or square.
     *
     * @param  string  $eyeShape
     * @return self
     */
    public function eyeShape(string $eyeShape): self
    {
        return $this->withOptions(['eyeShape' => $eyeShape]);
    }

    /**
     * Set the module colour.
     *
     * @param  string  $color  A CSS colour.
     * @return self
     */
    public function color(string $color): self
    {
        return $this->withOptions(['color' => $color]);
    }

    /**
     * Set the background colour.
     *
     * @param  string  $color  A CSS colour, or "transparent".
     * @return self
     */
    public function background(string $color): self
    {
        return $this->withOptions(['background' => $color]);
    }

    /**
     * Set the colour of the finder eyes. The value null gives the module colour.
     *
     * @param  string|null  $color
     * @return self
     */
    public function eyeColor(?string $color): self
    {
        return $this->withOptions(['eyeColor' => $color]);
    }

    /**
     * Set the colour of the frame. The value null gives the module colour.
     *
     * @param  string|null  $color
     * @return self
     */
    public function frameColor(?string $color): self
    {
        return $this->withOptions(['frameColor' => $color]);
    }

    /**
     * Set the error correction level: L, M, Q or H.
     *
     * @param  string  $level
     * @return self
     */
    public function errorCorrection(string $level): self
    {
        return $this->withOptions(['errorCorrection' => $level]);
    }

    /**
     * Set the seed for the random parts of the design.
     *
     * @param  int|float  $seed
     * @return self
     */
    public function seed(int|float $seed): self
    {
        return $this->withOptions(['seed' => $seed]);
    }

    /**
     * Set the QR mask, 0 to 7. The value null lets the encoder select the mask.
     *
     * @param  int|null  $mask
     * @return self
     */
    public function mask(?int $mask): self
    {
        return $this->withOptions(['mask' => $mask]);
    }

    /**
     * Set some QR code options in one call.
     *
     * @param  array  $options
     * @param  array<string, mixed>  $options
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function withOptions(array $options): self
    {
        $clone = clone $this;
        $clone->options = QrCode::resolveOptions(array_merge($this->options, $options));

        return $clone;
    }

    /**
     * Set the width and the height of raster images, in pixels.
     *
     * @param  int  $size
     * @return self
     *
     * @throws RenderException
     */
    public function size(int $size): self
    {
        $clone = clone $this;
        $clone->size = self::checkSize($size);

        return $clone;
    }

    /**
     * Set the output format: svg, png or webp.
     *
     * @param  string  $format
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function format(string $format): self
    {
        QrCode::mimeType($format);
        $clone = clone $this;
        $clone->format = $format;

        return $clone;
    }

    /**
     * Get the QR code options, with the defaults filled in.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the width and the height of raster images, in pixels.
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the output format.
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get the MIME type of the output format, for a Content-Type header.
     *
     * @return string
     */
    public function mimeType(): string
    {
        return QrCode::FORMATS[$this->format];
    }

    /**
     * Make a QR code object with the options of this generator.
     *
     * @param  string  $text
     * @return QrCode
     *
     * @throws InvalidOptionException
     */
    public function make(string $text): QrCode
    {
        return new QrCode($text, $this->options);
    }

    /**
     * Make the code in the output format.
     *
     * @param  string  $text  The payload. Payload makes payloads for the usual content types.
     * @param  string|null  $path  If you give a path, the generator also writes the file.
     * @return string The image data.
     *
     * @throws QrCodeException
     */
    public function generate(string $text, ?string $path = null): string
    {
        $qr = $this->make($text);
        $data = $this->format === 'svg' ? $qr->toSvg($path !== null) : $qr->render($this->format, $this->size);

        if ($path !== null && @file_put_contents($path, $data) === false) {
            throw new RenderException('Cannot write the file '.$path);
        }

        return $data;
    }

    /**
     * Make the code as a data URI in the output format.
     *
     * @param  string  $text
     * @return string
     *
     * @throws QrCodeException
     */
    public function dataUri(string $text): string
    {
        return $this->make($text)->toDataUri($this->format, $this->size);
    }

    /**
     * Make a Wi-Fi network code. Refer to Payload::wifi().
     *
     * @param  string  $ssid
     * @param  string|null  $password
     * @param  string  $encryption
     * @param  bool  $hidden
     * @return string
     *
     * @throws QrCodeException
     */
    public function wifi(string $ssid, ?string $password = null, string $encryption = 'WPA', bool $hidden = false): string
    {
        return $this->generate(Payload::wifi($ssid, $password, $encryption, $hidden));
    }

    /**
     * Make an e-mail code. Refer to Payload::email().
     *
     * @param  string  $to
     * @param  string|null  $subject
     * @param  string|null  $body
     * @param  string|string[]|null  $cc
     * @param  string|string[]|null  $bcc
     * @return string
     *
     * @throws QrCodeException
     */
    public function email(string $to, ?string $subject = null, ?string $body = null, string|array|null $cc = null, string|array|null $bcc = null): string
    {
        return $this->generate(Payload::email($to, $subject, $body, $cc, $bcc));
    }

    /**
     * Make a phone call code. Refer to Payload::phone().
     *
     * @param  string  $number
     * @return string
     *
     * @throws QrCodeException
     */
    public function phone(string $number): string
    {
        return $this->generate(Payload::phone($number));
    }

    /**
     * Make a text message code. Refer to Payload::sms().
     *
     * @param  string  $number
     * @param  string|null  $message
     * @return string
     *
     * @throws QrCodeException
     */
    public function sms(string $number, ?string $message = null): string
    {
        return $this->generate(Payload::sms($number, $message));
    }

    /**
     * Make a geographic location code. Refer to Payload::geo().
     *
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  string|null  $label
     * @return string
     *
     * @throws QrCodeException
     */
    public function geo(float $latitude, float $longitude, ?string $label = null): string
    {
        return $this->generate(Payload::geo($latitude, $longitude, $label));
    }

    /**
     * Make a contact card code. Refer to Payload::vcard().
     *
     * @param  string|null  $name
     * @param  string|null  $firstName
     * @param  string|null  $lastName
     * @param  string|null  $organization
     * @param  string|null  $title
     * @param  string|string[]|null  $phone
     * @param  string|string[]|null  $email
     * @param  string|null  $url
     * @param  string|null  $address
     * @param  string|null  $note
     * @return string
     *
     * @throws QrCodeException
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
    ): string {
        return $this->generate(Payload::vcard($name, $firstName, $lastName, $organization, $title, $phone, $email, $url, $address, $note));
    }

    /**
     * Make a calendar event code. Refer to Payload::calendar().
     *
     * @param  string  $summary
     * @param  DateTimeInterface|string  $start
     * @param  DateTimeInterface|string|null  $end
     * @param  string|null  $location
     * @param  string|null  $description
     * @param  bool  $allDay
     * @return string
     *
     * @throws QrCodeException
     */
    public function calendar(
        string $summary,
        DateTimeInterface|string $start,
        DateTimeInterface|string|null $end = null,
        ?string $location = null,
        ?string $description = null,
        bool $allDay = false,
    ): string {
        return $this->generate(Payload::calendar($summary, $start, $end, $location, $description, $allDay));
    }

    /**
     * Make a Bitcoin payment code. Refer to Payload::bitcoin().
     *
     * @param  string  $address
     * @param  float|int|string|null  $amount
     * @param  string|null  $label
     * @param  string|null  $message
     * @return string
     *
     * @throws QrCodeException
     */
    public function bitcoin(string $address, float|int|string|null $amount = null, ?string $label = null, ?string $message = null): string
    {
        return $this->generate(Payload::bitcoin($address, $amount, $label, $message));
    }

    /**
     * Make a Litecoin payment code. Refer to Payload::litecoin().
     *
     * @param  string  $address
     * @param  float|int|string|null  $amount
     * @param  string|null  $label
     * @param  string|null  $message
     * @return string
     *
     * @throws QrCodeException
     */
    public function litecoin(string $address, float|int|string|null $amount = null, ?string $label = null, ?string $message = null): string
    {
        return $this->generate(Payload::litecoin($address, $amount, $label, $message));
    }

    /**
     * Make an Ethereum payment code. Refer to Payload::ethereum().
     *
     * @param  string  $address
     * @param  int|string|null  $value
     * @param  int|null  $chainId
     * @return string
     *
     * @throws QrCodeException
     */
    public function ethereum(string $address, int|string|null $value = null, ?int $chainId = null): string
    {
        return $this->generate(Payload::ethereum($address, $value, $chainId));
    }

    /**
     * Make sure that a raster size is in the allowed range.
     *
     * @param  int  $size
     * @return int
     *
     * @throws RenderException
     */
    private static function checkSize(int $size): int
    {
        if ($size < 1 || $size > Raster\Rasterizer::MAX_SIZE) {
            throw new RenderException(sprintf('size: expected 1-%d pixels - got %d', Raster\Rasterizer::MAX_SIZE, $size));
        }

        return $size;
    }
}

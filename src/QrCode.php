<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute;

use DateTimeInterface;
use Wnikk\QrCodeCute\Encoder\Encoder;
use Wnikk\QrCodeCute\Exception\DataTooLongException;
use Wnikk\QrCodeCute\Exception\InvalidOptionException;
use Wnikk\QrCodeCute\Exception\QrCodeException;
use Wnikk\QrCodeCute\Exception\RenderException;
use Wnikk\QrCodeCute\Raster\Rasterizer;
use Wnikk\QrCodeCute\Render\CircleRenderer;
use Wnikk\QrCodeCute\Render\Context;
use Wnikk\QrCodeCute\Render\FrameRenderer;
use Wnikk\QrCodeCute\Render\Frames;
use Wnikk\QrCodeCute\Render\SquareRenderer;
use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * A QR code in the shape of a square, a circle, a triangle or a hexagon.
 *
 *     echo QrCode::svg('https://example.com', ['shape' => 'circle']);
 *
 *     $png = (new QrCode)->shape('hexagon')->frame('thin')->wifi('Office', 'secret')->toPng(512);
 *
 * This class is the public interface of the library. The object is immutable:
 * each setter and each content method gives a new object. Thus one object can
 * be a shared design, for example in the Laravel container.
 *
 * Each setter validates its value. An unknown option or value causes an
 * exception. The library does not replace a bad value with a default.
 */
final class QrCode
{
    /**
     * The outlines for the code.
     *
     * @var string[]
     */
    public const SHAPES = ['square', 'circle', 'triangle', 'hexagon'];

    /**
     * The shapes for one data module.
     *
     * @var string[]
     */
    public const PATTERNS = [
        'dots', 'square', 'small', 'star', 'rounded',
        'stars', 'hearts', 'triangles', 'diamonds',
        'pill-h', 'pill-v', 'bar-h', 'bar-v',
    ];

    /**
     * The shapes for the three finder eyes.
     *
     * @var string[]
     */
    public const EYE_SHAPES = ['circle', 'rounded', 'square'];

    /**
     * The frames around the code. All shapes have all frames.
     *
     * @var string[]
     */
    public const FRAMES = ['none', 'thin', 'double', 'organic'];

    /**
     * The error correction levels, from the lowest to the highest.
     *
     * @var string[]
     */
    public const ERROR_CORRECTION_LEVELS = Encoder::LEVELS;

    /**
     * The output formats and their MIME types.
     *
     * @var array<string, string>
     */
    public const FORMATS = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * The default value of each option.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'shape' => 'square',
        'frame' => 'none',
        'pattern' => 'dots',
        'eyeShape' => 'circle',
        'color' => '#000000',
        'background' => '#ffffff',
        'eyeColor' => null,
        'frameColor' => null,
        'errorCorrection' => 'H',
        'seed' => 0,
        'mask' => null,
    ];

    /**
     * The payload.
     *
     * @var string
     */
    private string $text;

    /**
     * All options, with the defaults filled in.
     *
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * The module matrix, after the first call to getMatrix().
     *
     * @var bool[][]|null
     */
    private ?array $matrix = null;

    /**
     * Create a QR code.
     *
     * The text can be empty here. Set it later with text() or with a content
     * method, for example wifi(). An output method needs a text.
     *
     * @param  string  $text  The payload, as UTF-8.
     * @param  array<string, mixed>  $options  Refer to DEFAULTS and to the README.
     * @return void
     *
     * @throws InvalidOptionException
     */
    public function __construct(string $text = '', array $options = [])
    {
        $this->text = $text;
        $this->options = self::resolveOptions($options);
    }

    /**
     * Make the SVG markup in one call.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $options
     * @return string
     *
     * @throws QrCodeException
     */
    public static function svg(string $text, array $options = []): string
    {
        return (new self($text, $options))->toSvg();
    }

    /**
     * Make the PNG bytes in one call.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $options
     * @param  int  $size  The width and the height in pixels.
     * @return string
     *
     * @throws QrCodeException
     */
    public static function png(string $text, array $options = [], int $size = 1024): string
    {
        return (new self($text, $options))->toPng($size);
    }

    /**
     * Make the lossless WebP bytes in one call.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $options
     * @param  int  $size  The width and the height in pixels.
     * @return string
     *
     * @throws QrCodeException
     */
    public static function webp(string $text, array $options = [], int $size = 1024): string
    {
        return (new self($text, $options))->toWebp($size);
    }

    /**
     * Validate options and fill in the defaults.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws InvalidOptionException
     */
    public static function resolveOptions(array $options): array
    {
        $unknown = array_diff(array_keys($options), array_keys(self::DEFAULTS));
        if ($unknown) {
            throw new InvalidOptionException(sprintf(
                'Unknown option %s - expected one of %s',
                self::describe(reset($unknown)),
                implode(', ', array_keys(self::DEFAULTS))
            ));
        }

        $resolved = [];
        foreach (self::DEFAULTS as $key => $default) {
            $resolved[$key] = $options[$key] ?? $default;
        }

        self::oneOf($resolved['shape'], self::SHAPES, 'shape');
        self::oneOf($resolved['frame'], self::FRAMES, 'frame');
        self::oneOf($resolved['pattern'], self::PATTERNS, 'pattern');
        self::oneOf($resolved['eyeShape'], self::EYE_SHAPES, 'eyeShape');
        self::oneOf($resolved['errorCorrection'], self::ERROR_CORRECTION_LEVELS, 'errorCorrection');

        foreach (['color', 'background'] as $key) {
            if (! is_string($resolved[$key]) || trim($resolved[$key]) === '') {
                throw new InvalidOptionException($key.': expected a colour string - got '.self::describe($resolved[$key]));
            }
        }
        foreach (['eyeColor', 'frameColor'] as $key) {
            if ($resolved[$key] !== null && (! is_string($resolved[$key]) || trim($resolved[$key]) === '')) {
                throw new InvalidOptionException($key.': expected a colour string or null - got '.self::describe($resolved[$key]));
            }
        }

        if (! is_int($resolved['seed']) && ! is_float($resolved['seed'])) {
            throw new InvalidOptionException('seed: expected a number - got '.self::describe($resolved['seed']));
        }

        $mask = $resolved['mask'];
        if ($mask !== null && (! is_int($mask) || $mask < 0 || $mask > 7)) {
            throw new InvalidOptionException('mask: expected an integer 0-7 - got '.self::describe($mask));
        }

        return $resolved;
    }

    /**
     * Get the MIME type of a format.
     *
     * @param  string  $format  One of the FORMATS keys.
     * @return string
     *
     * @throws InvalidOptionException
     */
    public static function mimeType(string $format): string
    {
        self::oneOf($format, array_keys(self::FORMATS), 'format');

        return self::FORMATS[$format];
    }

    /* ── Design setters ────────────────────────────────────────── */

    /**
     * Get a copy of this code with some options changed. This object does not change.
     *
     * The value null sets the default of an option.
     *
     * @param  array<string, mixed>  $options
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function with(array $options): self
    {
        return new self($this->text, array_merge($this->options, $options));
    }

    /**
     * Set the outline. One of SHAPES.
     *
     * @param  string  $shape
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function shape(string $shape): self
    {
        return $this->with(['shape' => $shape]);
    }

    /**
     * Set the frame. One of FRAMES.
     *
     * @param  string  $frame
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function frame(string $frame): self
    {
        return $this->with(['frame' => $frame]);
    }

    /**
     * Set the module shape. One of PATTERNS.
     *
     * @param  string  $pattern
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function pattern(string $pattern): self
    {
        return $this->with(['pattern' => $pattern]);
    }

    /**
     * Set the shape of the finder eyes. One of EYE_SHAPES.
     *
     * @param  string  $eyeShape
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function eyeShape(string $eyeShape): self
    {
        return $this->with(['eyeShape' => $eyeShape]);
    }

    /**
     * Set the module colour.
     *
     * @param  string  $color
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function color(string $color): self
    {
        return $this->with(['color' => $color]);
    }

    /**
     * Set the background colour, or 'transparent'.
     *
     * @param  string  $background
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function background(string $background): self
    {
        return $this->with(['background' => $background]);
    }

    /**
     * Set the colour of the finder eyes. The value null gives the module colour.
     *
     * @param  string|null  $eyeColor
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function eyeColor(?string $eyeColor): self
    {
        return $this->with(['eyeColor' => $eyeColor]);
    }

    /**
     * Set the colour of the frame. The value null gives the module colour.
     *
     * @param  string|null  $frameColor
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function frameColor(?string $frameColor): self
    {
        return $this->with(['frameColor' => $frameColor]);
    }

    /**
     * Set the error correction level. One of ERROR_CORRECTION_LEVELS.
     *
     * @param  string  $errorCorrection
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function errorCorrection(string $errorCorrection): self
    {
        return $this->with(['errorCorrection' => $errorCorrection]);
    }

    /**
     * Set the seed for the 'star' pattern and for the decorative fill.
     *
     * @param  int|float  $seed
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function seed(int|float $seed): self
    {
        return $this->with(['seed' => $seed]);
    }

    /**
     * Set the mask, 0-7. The value null selects the best mask.
     *
     * @param  int|null  $mask
     * @return self
     *
     * @throws InvalidOptionException
     */
    public function mask(?int $mask): self
    {
        return $this->with(['mask' => $mask]);
    }

    /* ── Content ───────────────────────────────────────────────── */

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

    /* ── Output ────────────────────────────────────────────────── */

    /**
     * Get the payload.
     *
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Get all options, with the defaults filled in.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the module matrix. The value true is a dark module.
     *
     * @return bool[][]
     *
     * @throws InvalidOptionException
     * @throws DataTooLongException
     */
    public function getMatrix(): array
    {
        if ($this->text === '') {
            throw new InvalidOptionException('text: expected a non-empty string - set it with text() or a content method');
        }

        return $this->matrix ??= Encoder::encode($this->text, $this->options['mask'], $this->options['errorCorrection']);
    }

    /**
     * Get the code as a tree of SVG nodes.
     *
     * Use the tree to add attributes or elements before you serialize it.
     * Each call makes a new tree.
     *
     * @return SvgNode
     *
     * @throws QrCodeException
     */
    public function toSvgNode(): SvgNode
    {
        $options = $this->options;
        $context = new Context;
        $context->pattern = $options['pattern'];
        $context->eyeShape = $options['eyeShape'];
        $context->color = $options['color'];
        $context->background = $options['background'];
        $context->eyeColor = $options['eyeColor'];
        $context->frameColor = $options['frameColor'];
        $context->seed = (float) $options['seed'];

        $matrix = $this->getMatrix();
        $shape = $options['shape'];

        if ($options['frame'] !== 'none') {
            return FrameRenderer::render($context, $matrix, Frames::KEYS[$shape][$options['frame']]);
        }
        if (isset(Frames::FRAMELESS_KEYS[$shape])) {
            return FrameRenderer::render($context, $matrix, Frames::FRAMELESS_KEYS[$shape]);
        }
        if ($shape === 'circle') {
            return CircleRenderer::render($context, $matrix);
        }

        return SquareRenderer::render($context, $matrix);
    }

    /**
     * Get the SVG markup.
     *
     * @param  bool  $withDeclaration  Set to true to add an XML declaration for a standalone file.
     * @return string
     *
     * @throws QrCodeException
     */
    public function toSvg(bool $withDeclaration = false): string
    {
        return $this->toSvgNode()->toXml($withDeclaration);
    }

    /**
     * Get the PNG bytes.
     *
     * @param  int  $size  The width and the height in pixels.
     * @return string
     *
     * @throws QrCodeException
     */
    public function toPng(int $size = 1024): string
    {
        return Rasterizer::png($this->toSvgNode(), $size);
    }

    /**
     * Get the lossless WebP bytes.
     *
     * @param  int  $size  The width and the height in pixels.
     * @return string
     *
     * @throws QrCodeException
     */
    public function toWebp(int $size = 1024): string
    {
        return Rasterizer::webp($this->toSvgNode(), $size);
    }

    /**
     * Get the code in a format.
     *
     * @param  string  $format  One of the FORMATS keys.
     * @param  int  $size  The width and the height in pixels. The SVG format does not use it.
     * @return string
     *
     * @throws QrCodeException
     */
    public function render(string $format = 'svg', int $size = 1024): string
    {
        self::oneOf($format, array_keys(self::FORMATS), 'format');

        return match ($format) {
            'png' => $this->toPng($size),
            'webp' => $this->toWebp($size),
            default => $this->toSvg(),
        };
    }

    /**
     * Get a data URI for an <img src> attribute.
     *
     * @param  string  $format  One of the FORMATS keys.
     * @param  int  $size  The width and the height in pixels. The SVG format does not use it.
     * @return string
     *
     * @throws QrCodeException
     */
    public function toDataUri(string $format = 'svg', int $size = 1024): string
    {
        return 'data:'.self::mimeType($format).';base64,'.base64_encode($this->render($format, $size));
    }

    /**
     * Write the code to a file. The file extension sets the format: .svg, .png or .webp.
     *
     * @param  string  $path
     * @param  int  $size  The width and the height in pixels. The SVG format does not use it.
     * @return void
     *
     * @throws QrCodeException
     */
    public function save(string $path, int $size = 1024): void
    {
        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! isset(self::FORMATS[$format])) {
            throw new InvalidOptionException('path: expected a .svg, .png or .webp file - got '.self::describe($path));
        }
        $data = $format === 'svg' ? $this->toSvg(true) : $this->render($format, $size);

        if (@file_put_contents($path, $data) === false) {
            throw new RenderException('Cannot write the file '.$path);
        }
    }

    /**
     * Get the SVG markup.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toSvg();
    }

    /**
     * Make sure that a value is in a list.
     *
     * @param  mixed  $value
     * @param  string[]  $allowed
     * @param  string  $name
     * @return void
     *
     * @throws InvalidOptionException
     */
    private static function oneOf(mixed $value, array $allowed, string $name): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new InvalidOptionException(sprintf(
                '%s: expected one of %s - got %s',
                $name,
                implode(', ', $allowed),
                self::describe($value)
            ));
        }
    }

    /**
     * Write a value for an error message.
     *
     * @param  mixed  $value
     * @return string
     */
    private static function describe(mixed $value): string
    {
        return is_string($value) ? '"'.$value.'"' : (string) json_encode($value);
    }
}

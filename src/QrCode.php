<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute;

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
 *     $qr = new QrCode('https://example.com', ['shape' => 'circle']);
 *     $qr->save('qr.svg');
 *     $qr->save('qr.png', 1024);
 *
 * The constructor validates the options. An unknown option or value causes
 * an exception. The library does not replace a bad value with a default.
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
     * @param  string  $text  The payload, as UTF-8. Payload makes payloads for the usual content types.
     * @param  array  $options
     * @param  array<string, mixed>  $options  Refer to DEFAULTS and to the README.
     * @return void
     *
     * @throws InvalidOptionException
     */
    public function __construct(string $text, array $options = [])
    {
        if ($text === '') {
            throw new InvalidOptionException('text: expected a non-empty string');
        }
        $this->text = $text;
        $this->options = self::resolveOptions($options);
    }

    /**
     * Make the SVG markup in one call.
     *
     * @param  string  $text
     * @param  array  $options
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
     * @param  array  $options
     * @param  int  $size  The width and the height in pixels.
     * @param  array<string, mixed>  $options
     * @return string
     *
     * @throws QrCodeException
     */
    public static function png(string $text, array $options = [], int $size = 1024): string
    {
        return (new self($text, $options))->toPng($size);
    }

    /**
     * Make the WebP bytes in one call.
     *
     * @param  string  $text
     * @param  array  $options
     * @param  int  $size  The width and the height in pixels.
     * @param  array<string, mixed>  $options
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
     * @param  array  $options
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

    /**
     * Get a copy of this code with some options changed. This object does not change.
     *
     * @param  array  $options
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
     * @throws DataTooLongException
     */
    public function getMatrix(): array
    {
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
     * @throws DataTooLongException
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
     * @throws DataTooLongException
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

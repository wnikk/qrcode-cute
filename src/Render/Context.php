<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Render;

/**
 * The settings that all renderers use.
 *
 * @internal
 */
final class Context
{
    /**
     * The module shape. One of QrCode::PATTERNS.
     *
     * @var string
     */
    public string $pattern = 'dots';

    /**
     * The finder-eye shape. One of QrCode::EYE_SHAPES.
     *
     * @var string
     */
    public string $eyeShape = 'circle';

    /**
     * The module colour.
     *
     * @var string
     */
    public string $color = '#000000';

    /**
     * The background colour, or 'transparent'.
     *
     * @var string
     */
    public string $background = '#ffffff';

    /**
     * The eye colour. The value null gives the module colour.
     *
     * @var string|null
     */
    public ?string $eyeColor = null;

    /**
     * The frame colour. The value null gives the module colour.
     *
     * @var string|null
     */
    public ?string $frameColor = null;

    /**
     * The canvas units for one module. All renderers set this value to 10.
     *
     * @var int
     */
    public int $moduleSize = 10;

    /**
     * The seed for the 'star' pattern and for the decorative fill.
     *
     * @var float
     */
    public float $seed = 0.0;

    /**
     * Gives a pseudo-random number in [0, 1) for a cell. The same cell always gives the same number.
     * The 'star' pattern and the decorative fill use it.
     *
     * @param  float  $ix
     * @param  float  $iy
     * @return float
     */
    public function rand(float $ix, float $iy): float
    {
        $s = sin($ix * 127.1 + $iy * 311.7 + $this->seed) * 43758.5453123;

        return $s - floor($s);
    }

    /**
     * Get the colour of the finder eyes.
     *
     * @return string
     */
    public function eyeFill(): string
    {
        return $this->eyeColor ?? $this->color;
    }

    /**
     * Get the colour of the frame.
     *
     * @return string
     */
    public function frameFill(): string
    {
        return $this->frameColor ?? $this->color;
    }

    /**
     * Determine if the pattern merges modules into pills or bars.
     *
     * @return bool
     */
    public function isPillLike(): bool
    {
        return in_array($this->pattern, ['pill-h', 'pill-v', 'bar-h', 'bar-v'], true);
    }

    /**
     * Gives 'h' or 'v' for the pill and bar patterns.
     *
     * @return string
     */
    public function pillMode(): string
    {
        return ($this->pattern === 'pill-h' || $this->pattern === 'bar-h') ? 'h' : 'v';
    }

    /**
     * Gives true for the bar patterns, which have square corners.
     *
     * @return bool
     */
    public function pillSharp(): bool
    {
        return $this->pattern === 'bar-h' || $this->pattern === 'bar-v';
    }
}

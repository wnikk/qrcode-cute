<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Concerns;

use Wnikk\QrCodeCute\Exception\InvalidOptionException;

/**
 * The design setters of QrCode. Each setter has the name of an option and
 * gives a new object. The original object does not change.
 *
 * Only QrCode uses this trait. It reads the $text and $options properties of QrCode.
 *
 * @internal
 */
trait HasDesign
{
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
}

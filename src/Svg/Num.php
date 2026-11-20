<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Svg;

/**
 * Formats numbers for the SVG output.
 *
 * @internal
 */
final class Num
{
    /**
     * Gives a short form: a maximum of four decimals, with no trailing zeros.
     * Four decimals are sufficient for a QR code. They keep the output the same on all platforms.
     *
     * @param  int|float  $value
     * @return string
     */
    public static function fmt(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (! is_finite($value)) {
            return '0';
        }
        $rounded = round($value, 4);
        if ($rounded == 0.0) {
            return '0';
        }
        if ($rounded == floor($rounded) && abs($rounded) < 1e15) {
            return (string) (int) $rounded;
        }

        return rtrim(rtrim(number_format($rounded, 4, '.', ''), '0'), '.');
    }

    /**
     * Gives a fixed number of decimals. A half rounds away from zero.
     *
     * @param  int|float  $value
     * @param  int  $decimals
     * @return string
     */
    public static function fixed(int|float $value, int $decimals): string
    {
        $s = number_format((float) $value, $decimals, '.', '');
        // number_format() can give "-0.00". Remove the minus sign.
        if ($s[0] === '-' && trim($s, '-0.') === '') {
            $s = substr($s, 1);
        }

        return $s;
    }
}

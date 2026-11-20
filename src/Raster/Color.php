<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Raster;

use Wnikk\QrCodeCute\Exception\RenderException;

/**
 * Reads CSS colours for the PNG renderer.
 *
 * These forms are possible: hex (#rgb, #rgba, #rrggbb, #rrggbbaa),
 * rgb(), rgba(), hsl() and hsla() with commas or spaces, the CSS colour
 * names, and 'transparent'.
 *
 * @internal
 */
final class Color
{
    /**
     * The CSS colour names and their hex values.
     *
     * @var string
     */
    private const NAMED = 'aliceblue:f0f8ff,antiquewhite:faebd7,aqua:00ffff,aquamarine:7fffd4,azure:f0ffff,beige:f5f5dc,bisque:ffe4c4,black:000000,blanchedalmond:ffebcd,blue:0000ff,blueviolet:8a2be2,brown:a52a2a,burlywood:deb887,cadetblue:5f9ea0,chartreuse:7fff00,chocolate:d2691e,coral:ff7f50,cornflowerblue:6495ed,cornsilk:fff8dc,crimson:dc143c,cyan:00ffff,darkblue:00008b,darkcyan:008b8b,darkgoldenrod:b8860b,darkgray:a9a9a9,darkgreen:006400,darkgrey:a9a9a9,darkkhaki:bdb76b,darkmagenta:8b008b,darkolivegreen:556b2f,darkorange:ff8c00,darkorchid:9932cc,darkred:8b0000,darksalmon:e9967a,darkseagreen:8fbc8f,darkslateblue:483d8b,darkslategray:2f4f4f,darkslategrey:2f4f4f,darkturquoise:00ced1,darkviolet:9400d3,deeppink:ff1493,deepskyblue:00bfff,dimgray:696969,dimgrey:696969,dodgerblue:1e90ff,firebrick:b22222,floralwhite:fffaf0,forestgreen:228b22,fuchsia:ff00ff,gainsboro:dcdcdc,ghostwhite:f8f8ff,gold:ffd700,goldenrod:daa520,gray:808080,green:008000,greenyellow:adff2f,grey:808080,honeydew:f0fff0,hotpink:ff69b4,indianred:cd5c5c,indigo:4b0082,ivory:fffff0,khaki:f0e68c,lavender:e6e6fa,lavenderblush:fff0f5,lawngreen:7cfc00,lemonchiffon:fffacd,lightblue:add8e6,lightcoral:f08080,lightcyan:e0ffff,lightgoldenrodyellow:fafad2,lightgray:d3d3d3,lightgreen:90ee90,lightgrey:d3d3d3,lightpink:ffb6c1,lightsalmon:ffa07a,lightseagreen:20b2aa,lightskyblue:87cefa,lightslategray:778899,lightslategrey:778899,lightsteelblue:b0c4de,lightyellow:ffffe0,lime:00ff00,limegreen:32cd32,linen:faf0e6,magenta:ff00ff,maroon:800000,mediumaquamarine:66cdaa,mediumblue:0000cd,mediumorchid:ba55d3,mediumpurple:9370db,mediumseagreen:3cb371,mediumslateblue:7b68ee,mediumspringgreen:00fa9a,mediumturquoise:48d1cc,mediumvioletred:c71585,midnightblue:191970,mintcream:f5fffa,mistyrose:ffe4e1,moccasin:ffe4b5,navajowhite:ffdead,navy:000080,oldlace:fdf5e6,olive:808000,olivedrab:6b8e23,orange:ffa500,orangered:ff4500,orchid:da70d6,palegoldenrod:eee8aa,palegreen:98fb98,paleturquoise:afeeee,palevioletred:db7093,papayawhip:ffefd5,peachpuff:ffdab9,peru:cd853f,pink:ffc0cb,plum:dda0dd,powderblue:b0e0e6,purple:800080,rebeccapurple:663399,red:ff0000,rosybrown:bc8f8f,royalblue:4169e1,saddlebrown:8b4513,salmon:fa8072,sandybrown:f4a460,seagreen:2e8b57,seashell:fff5ee,sienna:a0522d,silver:c0c0c0,skyblue:87ceeb,slateblue:6a5acd,slategray:708090,slategrey:708090,snow:fffafa,springgreen:00ff7f,steelblue:4682b4,tan:d2b48c,teal:008080,thistle:d8bfd8,tomato:ff6347,turquoise:40e0d0,violet:ee82ee,wheat:f5deb3,white:ffffff,whitesmoke:f5f5f5,yellow:ffff00,yellowgreen:9acd32';

    /**
     * The CSS colour names and their hex values, after the first use.
     *
     * @var array<string, string>|null
     */
    private static ?array $named = null;

    /**
     * The colours that are already read.
     *
     * @var array<string, array{0:int, 1:int, 2:int, 3:float}>
     */
    private static array $cache = [];

    /**
     * Read a CSS colour.
     *
     * @param  string  $value
     * @return array{0:int, 1:int, 2:int, 3:float} r, g, b in 0-255, alpha in 0-1
     */
    public static function parse(string $value): array
    {
        if (isset(self::$cache[$value])) {
            return self::$cache[$value];
        }
        if (count(self::$cache) > 256) {
            self::$cache = [];
        }

        return self::$cache[$value] = self::parseUncached($value);
    }

    /**
     * Read a CSS colour without the cache.
     *
     * @param  string  $value
     * @return array{0:int, 1:int, 2:int, 3:float}
     */
    private static function parseUncached(string $value): array
    {
        $v = strtolower(trim($value));

        if ($v === 'transparent' || $v === 'none') {
            return [0, 0, 0, 0.0];
        }

        if ($v !== '' && $v[0] === '#') {
            $hex = substr($v, 1);
            if (! ctype_xdigit($hex)) {
                throw self::error($value);
            }
            $len = strlen($hex);
            if ($len === 3 || $len === 4) {
                $hex = implode('', array_map(static fn (string $c): string => $c.$c, str_split($hex)));
                $len *= 2;
            }
            if ($len !== 6 && $len !== 8) {
                throw self::error($value);
            }

            return [
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
                $len === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0,
            ];
        }

        if (preg_match('/^(rgba?|hsla?)\(\s*([^)]*)\)$/', $v, $m)) {
            $parts = preg_split('/\s*[,\/]\s*|\s+/', trim($m[2])) ?: [];
            if (count($parts) !== 3 && count($parts) !== 4) {
                throw self::error($value);
            }
            $alpha = isset($parts[3]) ? self::unit($parts[3], 1.0) : 1.0;
            if ($m[1][0] === 'r') {
                return [
                    self::channel($parts[0]),
                    self::channel($parts[1]),
                    self::channel($parts[2]),
                    $alpha,
                ];
            }
            [$r, $g, $b] = self::hslToRgb(
                (float) $parts[0],
                self::unit($parts[1], 100.0),
                self::unit($parts[2], 100.0)
            );

            return [$r, $g, $b, $alpha];
        }

        if (self::$named === null) {
            self::$named = [];
            foreach (explode(',', self::NAMED) as $pair) {
                [$name, $hex] = explode(':', $pair);
                self::$named[$name] = $hex;
            }
        }
        if (isset(self::$named[$v])) {
            return self::parse('#'.self::$named[$v]);
        }

        throw self::error($value);
    }

    /**
     * Read one rgb() channel: a number 0-255 or a percentage.
     *
     * @param  string  $part
     * @return int
     */
    private static function channel(string $part): int
    {
        if (substr($part, -1) === '%') {
            $n = (float) $part * 2.55;
        } elseif (is_numeric($part)) {
            $n = (float) $part;
        } else {
            throw self::error($part);
        }

        return (int) round(max(0, min(255, $n)));
    }

    /**
     * Gives a number or a percentage as a fraction 0-1. Divides a plain number by $plainMax.
     *
     * @param  string  $part
     * @param  float  $plainMax
     * @return float
     */
    private static function unit(string $part, float $plainMax): float
    {
        if (substr($part, -1) === '%') {
            $n = (float) $part / 100;
        } elseif (is_numeric($part)) {
            $n = (float) $part / $plainMax;
        } else {
            throw self::error($part);
        }

        return max(0.0, min(1.0, $n));
    }

    /**
     * Change an HSL colour to RGB.
     *
     * @param  float  $h
     * @param  float  $s
     * @param  float  $l
     * @return int[]
     */
    private static function hslToRgb(float $h, float $s, float $l): array
    {
        $h = fmod(fmod($h, 360) + 360, 360) / 360;
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $out = [];
        foreach ([$h + 1 / 3, $h, $h - 1 / 3] as $t) {
            if ($t < 0) {
                $t += 1;
            }
            if ($t > 1) {
                $t -= 1;
            }
            if ($t < 1 / 6) {
                $c = $p + ($q - $p) * 6 * $t;
            } elseif ($t < 1 / 2) {
                $c = $q;
            } elseif ($t < 2 / 3) {
                $c = $p + ($q - $p) * (2 / 3 - $t) * 6;
            } else {
                $c = $p;
            }
            $out[] = (int) round($c * 255);
        }

        return $out;
    }

    /**
     * Make the exception for a colour that is not known.
     *
     * @param  string  $value
     * @return RenderException
     */
    private static function error(string $value): RenderException
    {
        return new RenderException(sprintf(
            'Cannot draw the colour "%s" in a PNG. Use hex, rgb(), rgba(), hsl(), hsla(), a CSS colour name or "transparent"',
            $value
        ));
    }
}

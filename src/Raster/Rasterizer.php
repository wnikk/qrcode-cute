<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Raster;

use Wnikk\QrCodeCute\Exception\RenderException;
use Wnikk\QrCodeCute\Svg\SvgNode;

/**
 * Draws an SVG node tree into a PNG or a WebP image. The code is pure PHP.
 *
 * It draws the SVG subset that the renderers make, and some more. Thus it
 * also draws a tree that you changed. Elements: <svg>, <g>, <rect>, <circle>,
 * <ellipse>, <line>, <polyline>, <polygon> and <path>. Attributes: transform,
 * fill, fill-rule, fill-opacity, stroke, stroke-width, stroke-opacity and
 * opacity. It does not draw text, gradients, clipping, masks, filters or CSS.
 */
final class Rasterizer
{
    /**
     * The maximum distance in pixels between a curve and its straight segments.
     *
     * @var float
     */
    private const TOLERANCE = 0.2;

    /**
     * The maximum width or height.
     *
     * @var int
     */
    public const MAX_SIZE = 8192;

    /**
     * Draw the tree and encode it as PNG.
     *
     * @param  SvgNode  $root
     * @param  int  $width  The image width in pixels. The height follows the viewBox aspect ratio.
     * @return string
     *
     * @throws RenderException
     */
    public static function png(SvgNode $root, int $width): string
    {
        return self::canvas($root, $width)->toPng();
    }

    /**
     * Draw the tree and encode it as lossless WebP.
     *
     * @param  SvgNode  $root
     * @param  int  $width  The image width in pixels. The height follows the viewBox aspect ratio.
     * @return string
     *
     * @throws RenderException
     */
    public static function webp(SvgNode $root, int $width): string
    {
        $canvas = self::canvas($root, $width);

        return WebpEncoder::encode($canvas->toRgba(), $canvas->width(), $canvas->height());
    }

    /**
     * Draw the tree on a new canvas.
     *
     * @param  SvgNode  $root
     * @param  int  $width
     * @return Canvas
     *
     * @internal
     */
    public static function canvas(SvgNode $root, int $width): Canvas
    {
        if ($width < 1 || $width > self::MAX_SIZE) {
            throw new RenderException(sprintf('size: expected 1-%d pixels - got %d', self::MAX_SIZE, $width));
        }

        $vb = preg_split('/[\s,]+/', trim((string) $root->getAttribute('viewBox'))) ?: [];
        if (count($vb) === 4) {
            [$vx, $vy, $vw, $vh] = array_map('floatval', $vb);
        } else {
            $vx = $vy = 0.0;
            $vw = (float) ($root->getAttribute('width') ?? $width);
            $vh = (float) ($root->getAttribute('height') ?? $width);
        }
        if ($vw <= 0 || $vh <= 0) {
            throw new RenderException('The root <svg> element must have a viewBox with a positive size');
        }

        $height = max(1, (int) round($width * $vh / $vw));
        $sx = $width / $vw;
        $sy = $height / $vh;

        $canvas = new Canvas($width, $height);
        $style = [
            'fill' => '#000000',
            'fill-rule' => 'nonzero',
            'fill-opacity' => 1.0,
            'stroke' => 'none',
            'stroke-width' => 1.0,
            'stroke-opacity' => 1.0,
            'opacity' => 1.0,
        ];
        $matrix = [$sx, 0.0, 0.0, $sy, -$vx * $sx, -$vy * $sy];

        self::drawChildren($canvas, $root, $matrix, $style);

        return $canvas;
    }

    /**
     * Draw a node and its children.
     *
     * @param  Canvas  $canvas
     * @param  SvgNode  $node
     * @param  float[]  $matrix  affine transform a, b, c, d, e, f
     * @param  array  $style
     * @param  array<string, mixed>  $style  inherited presentation attributes
     * @return void
     */
    private static function draw(Canvas $canvas, SvgNode $node, array $matrix, array $style): void
    {
        [$matrix, $style] = self::resolve($node, $matrix, $style);

        if (self::isContainer($node)) {
            self::drawChildren($canvas, $node, $matrix, $style);

            return;
        }

        $device = self::geometry($node, $matrix);
        if (! $device) {
            return;
        }
        $name = $node->getName();
        $scale = sqrt(abs($matrix[0] * $matrix[3] - $matrix[1] * $matrix[2])) ?: 1.0;

        if ($name !== 'line' && $name !== 'polyline' && ! self::isNone($style['fill'])) {
            $color = Color::parse($style['fill']);
            $color[3] *= $style['fill-opacity'] * $style['opacity'];
            $canvas->fill(array_column($device, 'points'), $style['fill-rule'] === 'evenodd', $color);
        }

        if (! self::isNone($style['stroke']) && $style['stroke-width'] > 0) {
            $color = Color::parse($style['stroke']);
            $color[3] *= $style['stroke-opacity'] * $style['opacity'];
            $canvas->fill(self::strokePolygons($device, $style['stroke-width'] * $scale / 2), false, $color);
        }
    }

    /**
     * Draws the children of a group.
     *
     * Adjacent shapes with the same plain fill go into one fill operation.
     * If two modules with a shared edge are filled one at a time, each module
     * covers the edge pixels only partially, and a thin line is visible. One
     * fill with the nonzero rule removes the shared edge.
     *
     * @param  Canvas  $canvas
     * @param  SvgNode  $node
     * @param  float[]  $matrix
     * @param  array  $style
     * @param  array<string, mixed>  $style
     * @return void
     */
    private static function drawChildren(Canvas $canvas, SvgNode $node, array $matrix, array $style): void
    {
        $batch = [];
        $batchFill = null;

        foreach ($node->getChildren() as $child) {
            if (self::isContainer($child) || $child->hasAttribute('transform')) {
                self::fillBatch($canvas, $batch, $batchFill);
                $batch = [];
                $batchFill = null;
                self::draw($canvas, $child, $matrix, $style);

                continue;
            }
            [, $childStyle] = self::resolve($child, $matrix, $style);
            $name = $child->getName();
            $simple = $name !== 'line' && $name !== 'polyline'
                && self::isNone($childStyle['stroke'])
                && ! self::isNone($childStyle['fill'])
                && $childStyle['fill-rule'] !== 'evenodd'
                && $childStyle['fill-opacity'] == 1.0
                && $childStyle['opacity'] == 1.0
                && Color::parse($childStyle['fill'])[3] >= 1.0;
            if (! $simple) {
                self::fillBatch($canvas, $batch, $batchFill);
                $batch = [];
                $batchFill = null;
                self::draw($canvas, $child, $matrix, $style);

                continue;
            }
            if ($batchFill !== $childStyle['fill']) {
                self::fillBatch($canvas, $batch, $batchFill);
                $batch = [];
                $batchFill = $childStyle['fill'];
            }
            $device = self::geometry($child, $matrix);
            // A shape with one outline cannot have a hole with the nonzero rule.
            // Thus the winding can change. Equal winding keeps overlapping shapes as a union.
            if (count($device) === 1) {
                $batch[] = self::positive($device[0]['points']);
            } else {
                foreach ($device as $sp) {
                    $batch[] = $sp['points'];
                }
            }
        }
        self::fillBatch($canvas, $batch, $batchFill);
    }

    /**
     * Fill the collected polygons of one colour with the nonzero rule.
     *
     * @param  Canvas  $canvas
     * @param  array<int, float[]>  $polygons
     * @param  string|null  $fill
     * @return void
     */
    private static function fillBatch(Canvas $canvas, array $polygons, ?string $fill): void
    {
        if ($polygons !== [] && $fill !== null) {
            $canvas->fill($polygons, false, Color::parse($fill));
        }
    }

    /**
     * Determine if a node is a container element.
     *
     * @param  SvgNode  $node
     * @return bool
     */
    private static function isContainer(SvgNode $node): bool
    {
        $name = $node->getName();

        return $name === 'g' || $name === 'svg' || $name === 'a';
    }

    /**
     * Applies the transform and the presentation attributes of a node.
     *
     * @param  SvgNode  $node
     * @param  float[]  $matrix
     * @param  array  $style
     * @param  array<string, mixed>  $style
     * @return array{0: float[], 1: array<string, mixed>}
     */
    private static function resolve(SvgNode $node, array $matrix, array $style): array
    {
        $transform = $node->getAttribute('transform');
        if ($transform !== null) {
            $matrix = self::multiply($matrix, self::parseTransform($transform));
        }
        foreach (['fill', 'fill-rule', 'stroke'] as $key) {
            $v = $node->getAttribute($key);
            if ($v !== null) {
                $style[$key] = trim($v);
            }
        }
        foreach (['fill-opacity', 'stroke-opacity', 'stroke-width'] as $key) {
            $v = $node->getAttribute($key);
            if ($v !== null) {
                $style[$key] = (float) $v;
            }
        }
        $opacity = $node->getAttribute('opacity');
        if ($opacity !== null) {
            $style['opacity'] *= (float) $opacity;
        }

        return [$matrix, $style];
    }

    /**
     * Gives the outline of a shape element in device space.
     *
     * @param  SvgNode  $node
     * @param  float[]  $matrix
     * @return array<int, array{points: float[], closed: bool}>
     */
    private static function geometry(SvgNode $node, array $matrix): array
    {
        $name = $node->getName();
        $scale = sqrt(abs($matrix[0] * $matrix[3] - $matrix[1] * $matrix[2])) ?: 1.0;
        $tol = self::TOLERANCE / $scale;
        $num = static fn (string $attr): float => (float) ($node->getAttribute($attr) ?? 0);

        switch ($name) {
            case 'path':
                $subpaths = Path::fromData((string) $node->getAttribute('d'), $tol);
                break;
            case 'rect':
                $rx = $node->getAttribute('rx');
                $ry = $node->getAttribute('ry');
                $rx = $rx !== null ? (float) $rx : ($ry !== null ? (float) $ry : 0.0);
                $ry = $ry !== null ? (float) $ry : $rx;
                $subpaths = Path::rect($num('x'), $num('y'), $num('width'), $num('height'), $rx, $ry, $tol);
                break;
            case 'circle':
                $subpaths = Path::ellipse($num('cx'), $num('cy'), $num('r'), $num('r'), $tol);
                break;
            case 'ellipse':
                $subpaths = Path::ellipse($num('cx'), $num('cy'), $num('rx'), $num('ry'), $tol);
                break;
            case 'polygon':
            case 'polyline':
                $subpaths = Path::polygon((string) $node->getAttribute('points'), $name === 'polygon');
                break;
            case 'line':
                $subpaths = [['points' => [$num('x1'), $num('y1'), $num('x2'), $num('y2')], 'closed' => false]];
                break;
            default:
                // This element is not geometry that the renderer draws (title, desc, defs, text).
                return [];
        }

        $device = [];
        foreach ($subpaths as $sp) {
            $device[] = ['points' => self::apply($matrix, $sp['points']), 'closed' => $sp['closed']];
        }

        return $device;
    }

    /**
     * Determine if a paint value draws nothing.
     *
     * @param  string  $paint
     * @return bool
     */
    private static function isNone(string $paint): bool
    {
        return $paint === 'none' || $paint === 'transparent' || $paint === '';
    }

    /**
     * Gives a stroke as a union of segment quads and round joins. All have the
     * same winding, so the nonzero rule fills their union.
     *
     * @param  array  $subpaths
     * @param  float  $half
     * @param  array<int, array{points: float[], closed: bool}>  $subpaths  device-space polylines
     * @return array<int, float[]>
     */
    private static function strokePolygons(array $subpaths, float $half): array
    {
        $out = [];
        $joinSteps = Path::arcSteps(2 * M_PI, $half, self::TOLERANCE);
        foreach ($subpaths as $sp) {
            $p = $sp['points'];
            $n = count($p);
            $segments = $sp['closed'] ? $n : $n - 2;
            for ($i = 0; $i < $segments; $i += 2) {
                $x0 = $p[$i];
                $y0 = $p[$i + 1];
                $x1 = $p[($i + 2) % $n];
                $y1 = $p[($i + 3) % $n];
                $len = hypot($x1 - $x0, $y1 - $y0);
                if ($len == 0) {
                    continue;
                }
                $nx = -($y1 - $y0) / $len * $half;
                $ny = ($x1 - $x0) / $len * $half;
                $out[] = self::positive([$x0 + $nx, $y0 + $ny, $x1 + $nx, $y1 + $ny, $x1 - $nx, $y1 - $ny, $x0 - $nx, $y0 - $ny]);
            }
            for ($i = 0; $i < $n; $i += 2) {
                $join = [];
                for ($k = 0; $k < $joinSteps; $k++) {
                    $t = 2 * M_PI * $k / $joinSteps;
                    $join[] = $p[$i] + $half * cos($t);
                    $join[] = $p[$i + 1] + $half * sin($t);
                }
                $out[] = $join;
            }
        }

        return $out;
    }

    /**
     * Gives the polygon with clockwise winding in screen space (a positive area).
     *
     * @param  float[]  $poly
     * @return float[]
     */
    private static function positive(array $poly): array
    {
        $area = 0.0;
        $n = count($poly);
        for ($i = 0; $i < $n; $i += 2) {
            $j = ($i + 2) % $n;
            $area += $poly[$i] * $poly[$j + 1] - $poly[$j] * $poly[$i + 1];
        }
        if ($area >= 0) {
            return $poly;
        }
        $rev = [];
        for ($i = $n - 2; $i >= 0; $i -= 2) {
            $rev[] = $poly[$i];
            $rev[] = $poly[$i + 1];
        }

        return $rev;
    }

    /**
     * Apply an affine transform to a list of points.
     *
     * @param  float[]  $m
     * @param  float[]  $points
     * @return float[]
     */
    private static function apply(array $m, array $points): array
    {
        [$a, $b, $c, $d, $e, $f] = $m;
        $out = [];
        $n = count($points);
        for ($i = 0; $i < $n; $i += 2) {
            $x = $points[$i];
            $y = $points[$i + 1];
            $out[] = $a * $x + $c * $y + $e;
            $out[] = $b * $x + $d * $y + $f;
        }

        return $out;
    }

    /**
     * Multiply two affine transforms.
     *
     * @param  float[]  $m
     * @param  float[]  $n
     * @return float[]
     */
    private static function multiply(array $m, array $n): array
    {
        return [
            $m[0] * $n[0] + $m[2] * $n[1],
            $m[1] * $n[0] + $m[3] * $n[1],
            $m[0] * $n[2] + $m[2] * $n[3],
            $m[1] * $n[2] + $m[3] * $n[3],
            $m[0] * $n[4] + $m[2] * $n[5] + $m[4],
            $m[1] * $n[4] + $m[3] * $n[5] + $m[5],
        ];
    }

    /**
     * Read a transform attribute.
     *
     * @param  string  $value
     * @return float[]
     */
    private static function parseTransform(string $value): array
    {
        $result = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        preg_match_all('/(matrix|translate|scale|rotate|skewX|skewY)\s*\(([^)]*)\)/', $value, $m, PREG_SET_ORDER);
        foreach ($m as [, $fn, $args]) {
            $a = array_map('floatval', preg_split('/[\s,]+/', trim($args)) ?: []);
            switch ($fn) {
                case 'matrix':
                    $t = count($a) === 6 ? $a : [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
                    break;
                case 'translate':
                    $t = [1.0, 0.0, 0.0, 1.0, $a[0] ?? 0.0, $a[1] ?? 0.0];
                    break;
                case 'scale':
                    $t = [$a[0] ?? 1.0, 0.0, 0.0, $a[1] ?? ($a[0] ?? 1.0), 0.0, 0.0];
                    break;
                case 'rotate':
                    $r = deg2rad($a[0] ?? 0.0);
                    $t = [cos($r), sin($r), -sin($r), cos($r), 0.0, 0.0];
                    if (isset($a[1], $a[2])) {
                        $t = self::multiply(
                            self::multiply([1.0, 0.0, 0.0, 1.0, $a[1], $a[2]], $t),
                            [1.0, 0.0, 0.0, 1.0, -$a[1], -$a[2]]
                        );
                    }
                    break;
                case 'skewX':
                    $t = [1.0, 0.0, tan(deg2rad($a[0] ?? 0.0)), 1.0, 0.0, 0.0];
                    break;
                default: // skewY
                    $t = [1.0, tan(deg2rad($a[0] ?? 0.0)), 0.0, 1.0, 0.0, 0.0];
            }
            $result = self::multiply($result, $t);
        }

        return $result;
    }
}

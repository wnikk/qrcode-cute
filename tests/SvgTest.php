<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Wnikk\QrCodeCute\QrCode;
use Wnikk\QrCodeCute\Svg\SvgNode;

final class SvgTest extends TestCase
{
    private const TEXT = 'https://example.com';

    public function test_all_shapes_and_frames_give_correct_svg(): void
    {
        foreach (QrCode::SHAPES as $shape) {
            foreach (QrCode::FRAMES as $frame) {
                $svg = QrCode::svg(self::TEXT, ['shape' => $shape, 'frame' => $frame]);
                $where = "$shape/$frame";
                $this->assertStringStartsWith('<svg ', $svg, $where);
                $this->assertStringEndsWith('</svg>', $svg, $where);
                $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg, $where);
                $this->assertMatchesRegularExpression('/viewBox="0 0 [\d.]+ [\d.]+"/', $svg, $where);
                $this->assertGreaterThan(2000, strlen($svg), $where);
                $this->assertDoesNotMatchRegularExpression('/NAN|INF/i', $svg, $where);
                $this->assertTrue((new DOMDocument)->loadXML($svg), "$where is not well-formed XML.");
            }
        }
    }

    public function test_all_patterns_give_output(): void
    {
        foreach (QrCode::PATTERNS as $pattern) {
            foreach (['square', 'circle'] as $shape) {
                $this->assertGreaterThan(2000, strlen(QrCode::svg(self::TEXT, ['shape' => $shape, 'pattern' => $pattern])), "$shape/$pattern");
            }
        }
    }

    public function test_each_eye_shape_gives_different_output(): void
    {
        $out = [];
        foreach (QrCode::EYE_SHAPES as $eyeShape) {
            $out[] = QrCode::svg(self::TEXT, ['eyeShape' => $eyeShape]);
        }
        $this->assertCount(count(QrCode::EYE_SHAPES), array_unique($out));
    }

    public function test_the_output_is_deterministic(): void
    {
        $this->assertSame(QrCode::svg(self::TEXT, ['pattern' => 'star']), QrCode::svg(self::TEXT, ['pattern' => 'star']));
    }

    public function test_the_seed_changes_only_the_random_parts(): void
    {
        $this->assertNotSame(QrCode::svg(self::TEXT, ['pattern' => 'star', 'seed' => 1]), QrCode::svg(self::TEXT, ['pattern' => 'star', 'seed' => 2]));
        // A square code with plain dots has no random parts.
        $this->assertSame(QrCode::svg(self::TEXT, ['seed' => 1]), QrCode::svg(self::TEXT, ['seed' => 2]));
        $this->assertNotSame(QrCode::svg(self::TEXT, ['shape' => 'circle', 'seed' => 1]), QrCode::svg(self::TEXT, ['shape' => 'circle', 'seed' => 2]));
    }

    public function test_the_colours_are_in_the_output(): void
    {
        $svg = QrCode::svg(self::TEXT, [
            'shape' => 'hexagon', 'frame' => 'thin',
            'color' => '#ff0000', 'background' => '#00ff00', 'eyeColor' => '#0000ff', 'frameColor' => '#123456',
        ]);
        foreach (['#ff0000', '#00ff00', '#0000ff', '#123456'] as $color) {
            $this->assertStringContainsString($color, $svg);
        }
    }

    public function test_a_transparent_background_has_no_background_card(): void
    {
        $hasCard = static function (SvgNode $node): bool {
            foreach ($node->getChildren() as $child) {
                if ($child->getAttribute('id') === 'Background') {
                    return true;
                }
            }

            return false;
        };
        foreach (QrCode::SHAPES as $shape) {
            $clear = (new QrCode(self::TEXT, ['shape' => $shape, 'background' => 'transparent']))->toSvgNode();
            $this->assertFalse($hasCard($clear), $shape);
            if ($shape !== 'circle') {
                $this->assertTrue($hasCard((new QrCode(self::TEXT, ['shape' => $shape]))->toSvgNode()), $shape);
            }
        }
    }

    public function test_a_forced_mask_changes_the_code(): void
    {
        $this->assertNotSame(QrCode::svg(self::TEXT, ['mask' => 0]), QrCode::svg(self::TEXT, ['mask' => 1]));
    }

    public function test_the_error_correction_level_changes_the_code(): void
    {
        $this->assertNotSame(QrCode::svg(self::TEXT, ['errorCorrection' => 'L']), QrCode::svg(self::TEXT));
    }

    public function test_the_node_tree_can_be_edited(): void
    {
        $node = (new QrCode(self::TEXT))->toSvgNode();
        $node->setAttribute('role', 'img')->setAttribute('aria-label', 'A & B');
        $node->appendChild(new SvgNode('title'));

        $xml = $node->toXml();
        $this->assertSame('svg', $node->getName());
        $this->assertStringContainsString('role="img"', $xml);
        $this->assertStringContainsString('aria-label="A &amp; B"', $xml);
        $this->assertStringStartsWith('<?xml', $node->toXml(true));
    }

    public function test_long_payloads_give_output_for_all_shapes(): void
    {
        foreach (QrCode::SHAPES as $shape) {
            $svg = QrCode::svg(str_repeat('a', 2900), ['shape' => $shape, 'errorCorrection' => 'L']);
            $this->assertGreaterThan(2000, strlen($svg), $shape);
        }
    }
}

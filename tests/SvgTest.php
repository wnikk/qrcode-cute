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
                $svg = (new QrCode(self::TEXT, ['shape' => $shape, 'frame' => $frame]))->toSvg();
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
                $this->assertGreaterThan(2000, strlen((new QrCode(self::TEXT, ['shape' => $shape, 'pattern' => $pattern]))->toSvg()), "$shape/$pattern");
            }
        }
    }

    public function test_each_eye_shape_gives_different_output(): void
    {
        $out = [];
        foreach (QrCode::EYE_SHAPES as $eyeShape) {
            $out[] = (new QrCode(self::TEXT, ['eyeShape' => $eyeShape]))->toSvg();
        }
        $this->assertCount(count(QrCode::EYE_SHAPES), array_unique($out));
    }

    public function test_the_output_is_deterministic(): void
    {
        $this->assertSame((new QrCode(self::TEXT, ['pattern' => 'star']))->toSvg(), (new QrCode(self::TEXT, ['pattern' => 'star']))->toSvg());
    }

    public function test_the_seed_changes_only_the_random_parts(): void
    {
        $this->assertNotSame((new QrCode(self::TEXT, ['pattern' => 'star', 'seed' => 1]))->toSvg(), (new QrCode(self::TEXT, ['pattern' => 'star', 'seed' => 2]))->toSvg());
        // A square code with plain dots has no random parts.
        $this->assertSame((new QrCode(self::TEXT, ['seed' => 1]))->toSvg(), (new QrCode(self::TEXT, ['seed' => 2]))->toSvg());
        $this->assertNotSame((new QrCode(self::TEXT, ['shape' => 'circle', 'seed' => 1]))->toSvg(), (new QrCode(self::TEXT, ['shape' => 'circle', 'seed' => 2]))->toSvg());
    }

    public function test_the_colours_are_in_the_output(): void
    {
        $svg = (new QrCode(self::TEXT, [
            'shape' => 'hexagon', 'frame' => 'thin',
            'color' => '#ff0000', 'background' => '#00ff00', 'eyeColor' => '#0000ff', 'frameColor' => '#123456',
        ]))->toSvg();
        foreach (['#ff0000', '#00ff00', '#0000ff', '#123456'] as $color) {
            $this->assertStringContainsString($color, $svg);
        }
    }

    public function test_a_transparent_background_has_no_background_card(): void
    {
        // The background card is a direct child of the root with the background colour.
        $hasCard = static function (SvgNode $node): bool {
            foreach ($node->getChildren() as $child) {
                if ($child->getAttribute('fill') === '#ffffff') {
                    return true;
                }
            }

            return false;
        };
        foreach (QrCode::SHAPES as $shape) {
            $clear = (new QrCode(self::TEXT, ['shape' => $shape, 'background' => 'transparent']))->toSvgNode();
            $this->assertFalse($hasCard($clear), $shape);
            $this->assertTrue($hasCard((new QrCode(self::TEXT, ['shape' => $shape]))->toSvgNode()), $shape);
        }
    }

    public function test_a_forced_mask_changes_the_code(): void
    {
        $this->assertNotSame((new QrCode(self::TEXT, ['mask' => 0]))->toSvg(), (new QrCode(self::TEXT, ['mask' => 1]))->toSvg());
    }

    public function test_the_error_correction_level_changes_the_code(): void
    {
        $this->assertNotSame((new QrCode(self::TEXT, ['errorCorrection' => 'L']))->toSvg(), (new QrCode(self::TEXT))->toSvg());
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
            $svg = (new QrCode(str_repeat('a', 2900), ['shape' => $shape, 'errorCorrection' => 'L']))->toSvg();
            $this->assertGreaterThan(2000, strlen($svg), $shape);
        }
    }
}

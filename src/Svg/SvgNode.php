<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Svg;

/**
 * A minimal SVG element: a name, attributes and children.
 *
 * The renderers make the code as a tree of these nodes. QrCode::toSvgNode()
 * gives this tree. You can add attributes or children before you serialize it.
 * The PNG renderer draws from the same tree.
 */
final class SvgNode
{
    /**
     * The SVG namespace.
     *
     * @var string
     */
    public const XMLNS = 'http://www.w3.org/2000/svg';

    /**
     * The attributes, by name.
     *
     * @var array<string, string|int|float>
     */
    private array $attributes = [];

    /**
     * The child elements.
     *
     * @var SvgNode[]
     */
    private array $children = [];

    /**
     * The parent element.
     *
     * @var SvgNode|null
     */
    private ?SvgNode $parent = null;

    /**
     * Values that a renderer gives to the next step. An example is the
     * radius of the decorative fill, which a frame uses to scale the code.
     * The serializer does not write these values.
     *
     * @internal
     *
     * @var array<string, mixed>
     */
    public array $meta = [];

    /**
     * The element name.
     *
     * @var string
     */
    private string $name;

    /**
     * Create an element.
     *
     * @param  string  $name
     * @return void
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the element name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set an attribute.
     *
     * @param  string  $name
     * @param  string|int|float  $value
     * @return self
     */
    public function setAttribute(string $name, string|int|float $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Get an attribute value as a string, or null if the attribute is not set.
     *
     * @param  string  $name
     * @return string|null
     */
    public function getAttribute(string $name): ?string
    {
        return array_key_exists($name, $this->attributes)
            ? self::formatValue($this->attributes[$name])
            : null;
    }

    /**
     * Determine if the element has an attribute.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Remove an attribute.
     *
     * @param  string  $name
     * @return self
     */
    public function removeAttribute(string $name): self
    {
        unset($this->attributes[$name]);

        return $this;
    }

    /**
     * Get all attributes as strings.
     *
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return array_map([self::class, 'formatValue'], $this->attributes);
    }

    /**
     * Add a child at the end of the element.
     *
     * @param  SvgNode  $child
     * @return SvgNode
     */
    public function appendChild(SvgNode $child): SvgNode
    {
        // Remove the child from its old parent first. A node has only one parent.
        if ($child->parent !== null) {
            $child->parent->removeChild($child);
        }
        $child->parent = $this;
        $this->children[] = $child;

        return $child;
    }

    /**
     * Remove a child from the element.
     *
     * @param  SvgNode  $child
     * @return SvgNode
     */
    public function removeChild(SvgNode $child): SvgNode
    {
        $at = array_search($child, $this->children, true);
        if ($at !== false) {
            array_splice($this->children, (int) $at, 1);
            $child->parent = null;
        }

        return $child;
    }

    /**
     * Remove the element from its parent.
     *
     * @return void
     */
    public function remove(): void
    {
        if ($this->parent !== null) {
            $this->parent->removeChild($this);
        }
    }

    /**
     * Get the children.
     *
     * @return SvgNode[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Get the parent element.
     *
     * @return SvgNode|null
     */
    public function getParent(): ?SvgNode
    {
        return $this->parent;
    }

    /**
     * Get the first child, or null if there are no children.
     *
     * @return SvgNode|null
     */
    public function firstChild(): ?SvgNode
    {
        return $this->children[0] ?? null;
    }

    /**
     * Moves all children of $source to the end of this node.
     *
     * @param  SvgNode  $source
     * @return void
     */
    public function adoptChildrenOf(SvgNode $source): void
    {
        while (($child = $source->firstChild()) !== null) {
            $this->appendChild($child);
        }
    }

    /**
     * Gives the markup of this node and its subtree.
     *
     * @return string
     */
    public function toString(): string
    {
        $out = '<'.$this->name;
        foreach ($this->attributes as $name => $value) {
            $out .= ' '.$name.'="'.htmlspecialchars(self::formatValue($value), ENT_QUOTES | ENT_XML1, 'UTF-8').'"';
        }
        if (! $this->children) {
            return $out.'/>';
        }
        $out .= '>';
        foreach ($this->children as $child) {
            $out .= $child->toString();
        }

        return $out.'</'.$this->name.'>';
    }

    /**
     * Get the markup of the element and its subtree.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Serializes a root <svg> element as a standalone document.
     * Adds the namespace if it is missing.
     *
     * @param  bool  $withDeclaration
     * @return string
     */
    public function toXml(bool $withDeclaration = false): string
    {
        if (! $this->hasAttribute('xmlns')) {
            $this->setAttribute('xmlns', self::XMLNS);
        }
        $xml = $this->toString();

        return $withDeclaration ? '<?xml version="1.0" encoding="UTF-8"?>'."\n".$xml : $xml;
    }

    /**
     * Format an attribute value.
     *
     * @param  string|int|float  $value
     * @return string
     */
    private static function formatValue(string|int|float $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return Num::fmt($value);
    }
}

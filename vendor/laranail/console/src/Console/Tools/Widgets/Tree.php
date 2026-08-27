<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Stringable;

/**
 * Renders a nested tree with `├─ │ └─` connectors (ASCII fallbacks without
 * Unicode), each node optionally prefixed with a status glyph. Responsive: deep
 * rows are clipped to the terminal width so they never wrap/overflow.
 */
final class Tree implements Stringable
{
    private readonly string $label;

    /** @var list<Tree> */
    private array $children = [];

    private ?string $status = null;

    private bool $responsive = true;

    private readonly Symbols $symbols;

    private readonly Capabilities $capabilities;

    public function __construct(string $label = '', ?Capabilities $capabilities = null)
    {
        $this->label = ConsoleUIFormatter::sanitizeText($label);
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->symbols = Symbols::for($this->capabilities);
    }

    public static function make(string $label = ''): self
    {
        return new self($label);
    }

    /**
     * Build a tree from a nested array: an array value becomes a branch labelled
     * by its key; a scalar value becomes a leaf (labelled `value` for list keys,
     * or `key: value` for string keys).
     *
     * @param array<int|string, mixed> $nested
     */
    public static function fromArray(string $label, array $nested): self
    {
        $tree = new self($label);
        $tree->appendArray($nested);

        return $tree;
    }

    /**
     * @param array<int|string, mixed> $nested
     */
    private function appendArray(array $nested): void
    {
        foreach ($nested as $key => $value) {
            if (is_array($value)) {
                /** @var array<int|string, mixed> $value */
                $this->child((string) $key, function (self $node) use ($value): void {
                    $node->appendArray($value);
                });

                continue;
            }

            $leaf = is_int($key) ? (string) $value : $key . ': ' . $value;
            $this->child($leaf);
        }
    }

    /**
     * Add a child node. The optional callback receives the new child for nesting.
     */
    public function child(string $label, ?callable $build = null): self
    {
        $node = new self($label);

        if ($build !== null) {
            $build($node);
        }

        $this->children[] = $node;

        return $this;
    }

    /**
     * Set the status glyph (success/error/warning/info/pending/running) for this node.
     */
    public function status(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function responsive(bool $responsive = true): self
    {
        $this->responsive = $responsive;

        return $this;
    }

    public function render(): string
    {
        $out = rtrim($this->label() . "\n" . $this->renderChildren(''));
        $cap = ResponsiveWidth::cap(null, $this->responsive, $this->capabilities);

        if ($cap === null) {
            return $out;
        }

        return implode("\n", array_map(
            static fn (string $line): string => DisplayWidth::truncate($line, $cap),
            explode("\n", $out),
        ));
    }

    private function renderChildren(string $prefix): string
    {
        $out = '';
        $last = count($this->children) - 1;

        foreach ($this->children as $i => $child) {
            $isLast = $i === $last;
            $connector = $this->symbols->get($isLast ? 'last' : 'branch');
            $stem = $isLast ? $this->symbols->get('gap') : $this->symbols->get('stem');

            $out .= $prefix . $connector . ' ' . $child->label() . "\n";
            $out .= $child->renderChildren($prefix . $stem);
        }

        return $out;
    }

    private function label(): string
    {
        return $this->status !== null
            ? $this->symbols->get($this->status) . ' ' . $this->label
            : $this->label;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Lang;
use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Stringable;

/**
 * A section header: a package glyph + title, with an optional item count.
 * Returns raw ANSI (echo-safe). Glyphs degrade to ASCII without Unicode.
 */
final class Header implements Stringable
{
    private int $count = 0;

    private ?string $itemLabel = null;

    private readonly Symbols $symbols;

    public function __construct(
        private readonly string $title,
        ?Capabilities $capabilities = null,
    ) {
        $this->symbols = Symbols::for($capabilities ?? Capabilities::detect());
    }

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function count(int $count, ?string $itemLabel = null): self
    {
        $this->count = $count;
        $this->itemLabel = $itemLabel;

        return $this;
    }

    public function render(): string
    {
        $fmt = ConsoleUIFormatter::create();
        $glyph = $this->symbols->get('package');
        $title = $fmt->colorize(trim($glyph . ' ' . $this->title), ConsoleUIFormatter::CYAN, true);

        if ($this->count > 0) {
            $label = $this->itemLabel ?? Lang::get('widgets.header.items', 'items');

            return $title . ' ' . $fmt->colorize("({$this->count} {$label})", ConsoleUIFormatter::GRAY);
        }

        return $title;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

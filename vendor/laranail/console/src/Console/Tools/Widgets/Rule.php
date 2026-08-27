<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Enums\BorderStyle;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Stringable;

/**
 * A full-width horizontal divider with an optional inline title.
 */
final class Rule implements Stringable
{
    private string $title = '';

    private BorderStyle $style;

    private ?int $width = null;

    private string $align = 'left';

    private readonly Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->style = $this->capabilities->supportsUnicode() ? BorderStyle::Light : BorderStyle::Ascii;
    }

    public static function make(string $title = ''): self
    {
        return (new self)->title($title);
    }

    public function title(string $title): self
    {
        $this->title = ConsoleUIFormatter::sanitizeText($title);

        return $this;
    }

    public function style(BorderStyle $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function width(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function center(): self
    {
        $this->align = 'center';

        return $this;
    }

    public function render(): string
    {
        $width = max($this->width ?? $this->capabilities->width(), 1);
        $line = $this->style->glyphs()['h'];

        // A titled rule needs the " title " label plus at least two rule chars.
        // If the width can't fit even a one-character title, fall back to a
        // plain rule so we never emit a blank/overflowing line.
        $titleBudget = $width - 4; // 2 rule chars + 2 surrounding spaces

        if ($this->title === '' || $titleBudget < 1) {
            return str_repeat($line, $width);
        }

        $label = ' ' . DisplayWidth::truncate($this->title, $titleBudget) . ' ';
        $remaining = $width - DisplayWidth::of($label); // >= 2 by construction

        if ($this->align === 'center') {
            $left = intdiv($remaining, 2);

            return str_repeat($line, $left) . $label . str_repeat($line, $remaining - $left);
        }

        return $line . $line . $label . str_repeat($line, $remaining - 2);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

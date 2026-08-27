<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Enums\BorderStyle;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Stringable;

/**
 * Frames text in a box drawn from a single {@see BorderStyle} family.
 *
 * Every interior line is padded to one column width using {@see DisplayWidth}
 * so the frame never drifts, even with wide characters or ANSI colour. Mixing
 * glyph families is impossible by construction — all glyphs come from one enum
 * case.
 */
final class Box implements Stringable
{
    private string $title = '';

    private string $footer = '';

    private BorderStyle $style;

    private int $padding = 1;

    private ?int $width = null;

    private bool $responsive = true;

    private readonly Capabilities $capabilities;

    /**
     * @param list<string> $lines
     */
    public function __construct(private readonly array $lines = [], ?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->style = $this->capabilities->supportsUnicode() ? BorderStyle::Rounded : BorderStyle::Ascii;
    }

    /**
     * @param list<string>|string $lines
     */
    public static function make(array|string $lines = []): self
    {
        return new self(is_array($lines) ? $lines : explode("\n", $lines));
    }

    public function title(string $title): self
    {
        $this->title = ConsoleUIFormatter::sanitizeText($title);

        return $this;
    }

    public function footer(string $footer): self
    {
        $this->footer = ConsoleUIFormatter::sanitizeText($footer);

        return $this;
    }

    public function style(BorderStyle $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function padding(int $padding): self
    {
        $this->padding = max($padding, 0);

        return $this;
    }

    public function width(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Clamp the box to the terminal width (default on). Long lines are truncated
     * so the frame never overflows a narrow terminal.
     */
    public function responsive(bool $responsive = true): self
    {
        $this->responsive = $responsive;

        return $this;
    }

    public function rounded(): self
    {
        return $this->style(BorderStyle::Rounded);
    }

    public function double(): self
    {
        return $this->style(BorderStyle::Double);
    }

    public function heavy(): self
    {
        return $this->style(BorderStyle::Heavy);
    }

    public function render(): string
    {
        $g = $this->style->glyphs();
        $lines = array_map(ConsoleUIFormatter::sanitizeText(...), $this->lines);

        // Always render at least one interior row so the box is a closed
        // rectangle even for empty content.
        if ($lines === []) {
            $lines = [''];
        }

        $bodyWidth = DisplayWidth::maxWidth($lines);

        // A fixed width() is a *minimum*: grow the interior to fit the content so
        // a long line never overflows the frame (DisplayWidth::pad only grows).
        $contentInner = $bodyWidth + ($this->padding * 2);
        $inner = $this->width !== null ? max($this->width - 2, $contentInner, 1) : $contentInner;

        // Ensure a titled/footed rule fits: leading edge glyph + " label ".
        foreach ([$this->title, $this->footer] as $label) {
            if ($label !== '') {
                $inner = max($inner, DisplayWidth::of($label) + 3);
            }
        }

        // Responsive: never let the frame exceed the terminal width (an explicit
        // width() still wins). Only clamps when content would otherwise overflow.
        if ($this->width === null && ResponsiveWidth::enabled() && $this->responsive) {
            $inner = min($inner, max($this->capabilities->width() - 2, 1));
        }

        $pad = str_repeat(' ', $this->padding);
        $interior = max($inner - ($this->padding * 2), 1);

        $top = $this->rule($g['tl'], $g['tr'], $g['h'], $inner, $this->title);
        $bottom = $this->rule($g['bl'], $g['br'], $g['h'], $inner, $this->footer);

        $body = [];
        foreach ($lines as $line) {
            $body[] = $g['v'] . $pad . DisplayWidth::pad(DisplayWidth::truncate($line, $interior), $interior) . $pad . $g['v'];
        }

        return implode("\n", [$top, ...$body, $bottom]);
    }

    private function rule(string $left, string $right, string $h, int $inner, string $label): string
    {
        if ($label === '') {
            return $left . str_repeat($h, $inner) . $right;
        }

        $tagged = ' ' . $label . ' ';
        $fill = max($inner - DisplayWidth::of($tagged), 0);

        return $left . $h . $tagged . str_repeat($h, max($fill - 1, 0)) . $right;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

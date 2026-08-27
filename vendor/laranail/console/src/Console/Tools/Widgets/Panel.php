<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Enums\BorderStyle;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Stringable;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A multi-block layout: stacks {@see Renderable} blocks vertically or arranges
 * them side-by-side, with optional border and dividers. Panels are themselves
 * Renderable, so they nest. Widths and truncation route through {@see DisplayWidth}
 * (multibyte/ANSI-aware) and borders through {@see BorderStyle} with an ASCII
 * fallback; capability-gated, renders to a string.
 */
final class Panel implements Renderable, Stringable
{
    /** @var list<Renderable> */
    private array $blocks = [];

    /** @var array<int, int> per-block fixed widths (horizontal layout) */
    private array $sizes = [];

    private bool $horizontal = false;

    private bool $border = false;

    private bool $dividers = false;

    private bool $responsive = true;

    private BorderStyle $style = BorderStyle::Light;

    private readonly Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
    }

    public static function make(): self
    {
        return new self;
    }

    public function add(Renderable $block): self
    {
        $this->blocks[] = $block;

        return $this;
    }

    public function vertical(): self
    {
        $this->horizontal = false;

        return $this;
    }

    public function horizontal(): self
    {
        $this->horizontal = true;

        return $this;
    }

    /**
     * @param array<int, int> $sizes per-block widths for horizontal layout
     */
    public function sizes(array $sizes): self
    {
        $this->sizes = $sizes;

        return $this;
    }

    public function border(bool $border = true): self
    {
        $this->border = $border;

        return $this;
    }

    public function dividers(bool $dividers = true): self
    {
        $this->dividers = $dividers;

        return $this;
    }

    /**
     * Clamp a horizontal layout to the terminal width (default on): block widths
     * shrink proportionally so the row never overflows. `sizes()` opts out.
     */
    public function responsive(bool $responsive = true): self
    {
        $this->responsive = $responsive;

        return $this;
    }

    public function style(BorderStyle $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function renderLines(): array
    {
        if ($this->blocks === []) {
            return [];
        }

        return $this->horizontal ? $this->renderHorizontal() : $this->renderVertical();
    }

    public function totalWidth(): int
    {
        return DisplayWidth::maxWidth($this->renderLines());
    }

    public function totalHeight(): int
    {
        return count($this->renderLines());
    }

    public function render(?OutputInterface $output = null): string
    {
        $rendered = implode("\n", $this->renderLines());

        $output?->writeln($rendered);

        return $rendered;
    }

    /**
     * @return list<string>
     */
    private function renderVertical(): array
    {
        $width = 0;
        foreach ($this->blocks as $block) {
            $width = max($width, $block->totalWidth());
        }

        $g = $this->glyphs();
        $lines = [];

        if ($this->border) {
            $lines[] = $g['tl'] . str_repeat($g['h'], $width) . $g['tr'];
        }

        $last = count($this->blocks) - 1;
        foreach ($this->blocks as $index => $block) {
            foreach ($block->renderLines() as $line) {
                $line = DisplayWidth::pad($line, $width);
                $lines[] = $this->border ? $g['v'] . $line . $g['v'] : $line;
            }

            if ($this->dividers && $index < $last) {
                $lines[] = $this->border
                    ? $g['teeRight'] . str_repeat($g['h'], $width) . $g['teeLeft']
                    : str_repeat($g['h'], $width);
            }
        }

        if ($this->border) {
            $lines[] = $g['bl'] . str_repeat($g['h'], $width) . $g['br'];
        }

        return $lines;
    }

    /**
     * Shrink block widths proportionally so a horizontal row fits the terminal.
     *
     * @param array<int, int> $widths
     * @return array<int, int>
     */
    private function fitWidths(array $widths): array
    {
        $count = count($widths);
        $overhead = ($this->border ? 2 : 0) + ($this->dividers ? max($count - 1, 0) : 0);
        $available = ResponsiveWidth::terminal($this->capabilities) - $overhead;
        $total = array_sum($widths);

        if ($total <= $available || $available < $count) {
            return $widths;
        }

        $scaled = [];
        foreach ($widths as $index => $width) {
            $scaled[$index] = max((int) floor($width / $total * $available), 1);
        }

        return $scaled;
    }

    /**
     * @return list<string>
     */
    private function renderHorizontal(): array
    {
        $widths = [];
        $height = 0;
        foreach ($this->blocks as $index => $block) {
            $widths[$index] = ($this->sizes[$index] ?? 0) > 0 ? $this->sizes[$index] : $block->totalWidth();
            $height = max($height, $block->totalHeight());
        }

        // Responsive: if no explicit sizes and the row would overflow, shrink block
        // widths proportionally so the whole panel fits the terminal.
        if ($this->sizes === [] && $this->responsive && ResponsiveWidth::enabled()) {
            $widths = $this->fitWidths($widths);
        }

        // Each block's lines, padded down to the common height.
        $blockLines = [];
        foreach ($this->blocks as $index => $block) {
            $rows = $block->renderLines();
            $rows = [...$rows, ...array_fill(0, max($height - count($rows), 0), str_repeat(' ', $widths[$index]))];
            $blockLines[$index] = $rows;
        }

        $g = $this->glyphs();
        $divider = $this->dividers ? $g['v'] : '';
        $innerWidth = array_sum($widths) + ($this->dividers ? max(count($this->blocks) - 1, 0) : 0);
        $lines = [];

        if ($this->border) {
            $lines[] = $g['tl'] . str_repeat($g['h'], $innerWidth) . $g['tr'];
        }

        for ($row = 0; $row < $height; $row++) {
            $segments = [];
            foreach (array_keys($this->blocks) as $index) {
                $seg = $blockLines[$index][$row] ?? str_repeat(' ', $widths[$index]);
                $seg = DisplayWidth::of($seg) > $widths[$index]
                    ? DisplayWidth::pad(DisplayWidth::truncateAnsi($seg, $widths[$index]), $widths[$index])
                    : DisplayWidth::pad($seg, $widths[$index]);
                $segments[] = $seg;
            }

            $line = implode($divider, $segments);
            $lines[] = $this->border ? $g['v'] . $line . $g['v'] : $line;
        }

        if ($this->border) {
            $lines[] = $g['bl'] . str_repeat($g['h'], $innerWidth) . $g['br'];
        }

        return $lines;
    }

    /**
     * @return array{tl:string,tr:string,bl:string,br:string,h:string,v:string,teeDown:string,teeUp:string,teeLeft:string,teeRight:string,cross:string}
     */
    private function glyphs(): array
    {
        $style = $this->capabilities->supportsUnicode() ? $this->style : $this->style->fallback();

        return $style->glyphs();
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Enums\BorderStyle;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Stringable;

/**
 * A single content block for a {@see Panel} (or standalone): text with optional
 * fixed width/height, word-wrap and a border. Word-wrap and truncation are
 * display-width aware via {@see DisplayWidth} (multibyte/ANSI-safe), borders use
 * {@see BorderStyle} glyphs with an ASCII fallback; renders to a string.
 */
final class PanelBlock implements Renderable, Stringable
{
    /** @var list<string> */
    private array $lines = [''];

    private ?int $fixedWidth = null;

    private ?int $fixedHeight = null;

    private bool $border = false;

    private bool $wrap = false;

    private BorderStyle $style = BorderStyle::Light;

    private readonly Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
    }

    /**
     * @param string|list<string> $content
     */
    public static function make(string|array $content = ''): self
    {
        return (new self)->content($content);
    }

    /**
     * @param string|list<string> $content
     */
    public function content(string|array $content): self
    {
        $lines = is_array($content) ? $content : explode("\n", $content);
        $this->lines = array_values(array_map(ConsoleUIFormatter::sanitizeText(...), $lines === [] ? [''] : $lines));

        return $this;
    }

    public function width(int $width): self
    {
        $this->fixedWidth = max($width, 0);

        return $this;
    }

    public function height(int $height): self
    {
        $this->fixedHeight = max($height, 0);

        return $this;
    }

    public function border(bool $border = true): self
    {
        $this->border = $border;

        return $this;
    }

    public function wrap(bool $wrap = true): self
    {
        $this->wrap = $wrap;

        return $this;
    }

    public function style(BorderStyle $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function totalWidth(): int
    {
        return $this->contentWidth() + ($this->border ? 2 : 0);
    }

    public function totalHeight(): int
    {
        return $this->contentHeight() + ($this->border ? 2 : 0);
    }

    public function renderLines(): array
    {
        $contentWidth = $this->contentWidth();
        $contentHeight = $this->contentHeight();
        $lines = $this->contentLines();

        // Pad / truncate to the content height.
        $lines = array_slice([...$lines, ...array_fill(0, max($contentHeight - count($lines), 0), '')], 0, max($contentHeight, 0));

        // Pad / truncate each line to the content width (display-width aware).
        $lines = array_map(fn (string $line): string => DisplayWidth::of($line) > $contentWidth
            ? DisplayWidth::truncate($line, $contentWidth)
            : DisplayWidth::pad($line, $contentWidth), $lines);

        if (! $this->border) {
            return array_values($lines);
        }

        $g = $this->glyphs();
        $top = $g['tl'] . str_repeat($g['h'], $contentWidth) . $g['tr'];
        $bottom = $g['bl'] . str_repeat($g['h'], $contentWidth) . $g['br'];
        $body = array_map(static fn (string $l): string => $g['v'] . $l . $g['v'], $lines);

        return [$top, ...$body, $bottom];
    }

    public function render(): string
    {
        return implode("\n", $this->renderLines());
    }

    private function contentWidth(): int
    {
        if ($this->fixedWidth !== null && $this->fixedWidth > 0) {
            return max($this->fixedWidth - ($this->border ? 2 : 0), 0);
        }

        return DisplayWidth::maxWidth($this->contentLines());
    }

    private function contentHeight(): int
    {
        if ($this->fixedHeight !== null && $this->fixedHeight > 0) {
            return max($this->fixedHeight - ($this->border ? 2 : 0), 0);
        }

        return count($this->contentLines());
    }

    /**
     * @return list<string>
     */
    private function contentLines(): array
    {
        if ($this->wrap && $this->fixedWidth !== null && $this->fixedWidth > 0) {
            $width = max($this->fixedWidth - ($this->border ? 2 : 0), 1);
            $wrapped = [];

            foreach ($this->lines as $line) {
                foreach ($this->wrapLine($line, $width) as $piece) {
                    $wrapped[] = $piece;
                }
            }

            return $wrapped === [] ? [''] : $wrapped;
        }

        return $this->lines;
    }

    /**
     * Greedy word-wrap by display width.
     *
     * @return list<string>
     */
    private function wrapLine(string $line, int $width): array
    {
        $out = [];
        $current = '';

        foreach (explode(' ', $line) as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if (DisplayWidth::of($candidate) <= $width) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $out[] = $current;
                $current = '';
            }

            while (DisplayWidth::of($word) > $width) {
                $out[] = DisplayWidth::truncate($word, $width);
                $word = mb_substr($word, mb_strlen(DisplayWidth::truncate($word, $width)));
            }

            $current = $word;
        }

        if ($current !== '') {
            $out[] = $current;
        }

        return $out === [] ? [''] : $out;
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

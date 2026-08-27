<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Align;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\Emoji;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A word-wrapped, themeable prose block. Wraps to the available width (responsive
 * by default), is wide-char aware, resolves `:emoji:` shortcodes, and supports
 * left / center / right / justify alignment.
 */
final class Paragraph implements Renderable, Stringable
{
    use RendersBlock;

    public const int DEFAULT_WIDTH = 80;

    private ?int $width = null;

    private bool $responsive = true;

    private string $align = Align::LEFT;

    private ?Style $style = null;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

    private readonly string $text;

    public function __construct(string $text, ?Capabilities $capabilities = null, ?Theme $theme = null, private readonly bool $preformatted = false)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
        // Pre-styled text (rich) is wrapped as-is; plain text is sanitised and has
        // :emoji: shortcodes resolved.
        $this->text = $this->preformatted
            ? $text
            : Emoji::make($this->capabilities)->render(ConsoleUIFormatter::sanitizeText($text));
    }

    public static function make(string $text): self
    {
        return new self($text);
    }

    /**
     * Wrap text that already carries ANSI styling (e.g. from InlineMarkup) — it is
     * not sanitised; wrapping stays width-correct (DisplayWidth ignores escapes).
     */
    public static function rich(string $styled, ?Capabilities $capabilities = null, ?Theme $theme = null): self
    {
        return new self($styled, $capabilities, $theme, true);
    }

    public function width(int $width): self
    {
        $this->width = max($width, 1);

        return $this;
    }

    public function responsive(bool $responsive = true): self
    {
        $this->responsive = $responsive;

        return $this;
    }

    public function align(string $align): self
    {
        $this->align = match (strtolower($align)) {
            Align::JUSTIFY => Align::JUSTIFY,
            default => Align::normalize($align),
        };

        return $this;
    }

    public function style(Style $style): self
    {
        $this->style = $style;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $width = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? self::DEFAULT_WIDTH;
        $wrapped = $this->wrap($this->text, $width);
        $style = $this->style ?? $this->theme->style('paragraph');

        $out = [];
        $last = count($wrapped) - 1;
        $carry = '';

        foreach ($wrapped as $i => $line) {
            // Pre-styled (rich) text: re-open any colour still active at the end of
            // the previous line, so a styled span keeps its colour across a wrap.
            if ($this->preformatted && $carry !== '') {
                $line = $carry . $line;
            }

            $aligned = $this->align === Align::JUSTIFY
                ? $this->justify($line, $width, $i === $last)
                : match ($this->align) {
                    Align::CENTER => DisplayWidth::center($line, $width),
                    Align::RIGHT => DisplayWidth::padLeft($line, $width),
                    default => $line,
                };

            // Pre-styled lines keep their own ANSI; close them so colour never
            // bleeds across a wrap. Plain lines get the paragraph/theme style.
            if ($this->preformatted) {
                $carry = $this->activeSgr($line);
                $out[] = $this->closeAnsi($aligned);
            } else {
                $out[] = $style->apply($aligned);
            }
        }

        return $out === [] ? [''] : $out;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $width): array
    {
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [''] as $paragraph) {
            $current = '';

            foreach (preg_split('/ +/', trim($paragraph)) ?: [] as $word) {
                if ($word === '') {
                    continue;
                }

                foreach ($this->breakLongWord($word, $width) as $piece) {
                    $candidate = $current === '' ? $piece : $current . ' ' . $piece;

                    if (DisplayWidth::of($candidate) <= $width) {
                        $current = $candidate;
                    } else {
                        if ($current !== '') {
                            $lines[] = $current;
                        }
                        $current = $piece;
                    }
                }
            }

            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Hard-break a word wider than the available width into width-sized chunks.
     *
     * @return list<string>
     */
    private function breakLongWord(string $word, int $width): array
    {
        if (DisplayWidth::of($word) <= $width) {
            return [$word];
        }

        $chunks = [];
        $chunk = '';

        foreach (mb_str_split($word) as $char) {
            if (DisplayWidth::of($chunk . $char) > $width && $chunk !== '') {
                $chunks[] = $chunk;
                $chunk = '';
            }
            $chunk .= $char;
        }

        if ($chunk !== '') {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * The SGR sequences still "open" at the end of a line — the concatenation of
     * colour/attribute opens since the last reset (`\e[0m`). Used to re-open style
     * on the next wrapped line so a long styled span keeps its colour.
     */
    private function activeSgr(string $line): string
    {
        if (! str_contains($line, "\033[")) {
            return '';
        }

        preg_match_all('/\033\[[0-9;]*m/', $line, $matches);

        $open = '';
        foreach ($matches[0] as $seq) {
            $open = ($seq === "\033[0m" || $seq === "\033[m") ? '' : $open . $seq;
        }

        return $open;
    }

    /**
     * Ensure a styled line is reset-terminated (no colour bleed across wraps).
     */
    private function closeAnsi(string $line): string
    {
        if (! str_contains($line, "\033[") || str_ends_with(rtrim($line), "\033[0m")) {
            return $line;
        }

        return $line . "\033[0m";
    }

    private function justify(string $line, int $width, bool $isLast): string
    {
        $words = preg_split('/ +/', trim($line)) ?: [];

        if ($isLast || count($words) < 2) {
            return DisplayWidth::pad($line, $width);
        }

        $wordsWidth = array_sum(array_map(DisplayWidth::of(...), $words));
        $gaps = count($words) - 1;
        $spaces = max($width - $wordsWidth, $gaps);
        $per = intdiv($spaces, $gaps);
        $extra = $spaces % $gaps;

        $out = '';
        foreach ($words as $i => $word) {
            $out .= $word;
            if ($i < $gaps) {
                $out .= str_repeat(' ', $per + ($i < $extra ? 1 : 0));
            }
        }

        return $out;
    }
}

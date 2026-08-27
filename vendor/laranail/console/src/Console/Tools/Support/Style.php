<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

use Simtabi\Laranail\Console\Tools\Enums\Sgr;
use Stringable;

/**
 * An immutable, chainable text style — foreground/background colour plus SGR
 * attributes — applied to a string with full graceful degradation.
 *
 * Every setter returns a NEW instance (value object; concurrency-safe). Colours
 * go through {@see Color} (truecolor→256→16→strip); attributes through {@see Sgr}.
 * When colour is unsupported (NO_COLOR / non-TTY) `apply()` returns the text
 * unchanged.
 *
 *   Style::make()->fg('#7c3aed')->bold()->underline()->apply('Hello');
 */
final readonly class Style implements Stringable
{
    public function __construct(
        public ?string $fg = null,
        public ?string $bg = null,
        public bool $bold = false,
        public bool $dim = false,
        public bool $italic = false,
        public bool $underline = false,
        public bool $strikethrough = false,
        public bool $inverse = false,
        public bool $blink = false,
        private ?Capabilities $capabilities = null,
    ) {}

    public static function make(?Capabilities $capabilities = null): self
    {
        return new self(capabilities: $capabilities);
    }

    public function fg(string $color): self
    {
        return $this->with(fg: $color);
    }

    public function bg(string $color): self
    {
        return $this->with(bg: $color);
    }

    public function bold(bool $on = true): self
    {
        return $this->with(bold: $on);
    }

    public function dim(bool $on = true): self
    {
        return $this->with(dim: $on);
    }

    public function italic(bool $on = true): self
    {
        return $this->with(italic: $on);
    }

    public function underline(bool $on = true): self
    {
        return $this->with(underline: $on);
    }

    public function strikethrough(bool $on = true): self
    {
        return $this->with(strikethrough: $on);
    }

    public function inverse(bool $on = true): self
    {
        return $this->with(inverse: $on);
    }

    public function blink(bool $on = true): self
    {
        return $this->with(blink: $on);
    }

    /**
     * Whether this style has any visual effect.
     */
    public function isEmpty(): bool
    {
        return $this->fg === null && $this->bg === null
            && ! ($this->bold || $this->dim || $this->italic || $this->underline
                || $this->strikethrough || $this->inverse || $this->blink);
    }

    /**
     * Apply the style to text (no-op when colour is unsupported or text is empty).
     */
    public function apply(string $text): string
    {
        $capabilities = $this->capabilities ?? Capabilities::detect();

        if ($text === '' || $this->isEmpty() || ! $capabilities->supportsColor()) {
            return $text;
        }

        $color = new Color($capabilities);
        $open = '';

        if ($this->fg !== null) {
            $open .= $color->sequence($this->fg, false);
        }

        if ($this->bg !== null) {
            $open .= $color->sequence($this->bg, true);
        }

        $codes = [];
        if ($this->bold) {
            $codes[] = Sgr::Bold;
        }
        if ($this->dim) {
            $codes[] = Sgr::Faint;
        }
        if ($this->italic) {
            $codes[] = Sgr::Italic;
        }
        if ($this->underline) {
            $codes[] = Sgr::Underline;
        }
        if ($this->strikethrough) {
            $codes[] = Sgr::Strikethrough;
        }
        if ($this->inverse) {
            $codes[] = Sgr::Reverse;
        }
        if ($this->blink) {
            $codes[] = Sgr::Blink;
        }

        if ($codes !== []) {
            $open .= Sgr::sequence(...$codes);
        }

        return $open === '' ? $text : $open . $text . "\033[0m";
    }

    public function __toString(): string
    {
        return $this->apply('');
    }

    private function with(
        ?string $fg = null,
        ?string $bg = null,
        ?bool $bold = null,
        ?bool $dim = null,
        ?bool $italic = null,
        ?bool $underline = null,
        ?bool $strikethrough = null,
        ?bool $inverse = null,
        ?bool $blink = null,
    ): self {
        return new self(
            $fg ?? $this->fg,
            $bg ?? $this->bg,
            $bold ?? $this->bold,
            $dim ?? $this->dim,
            $italic ?? $this->italic,
            $underline ?? $this->underline,
            $strikethrough ?? $this->strikethrough,
            $inverse ?? $this->inverse,
            $blink ?? $this->blink,
            $this->capabilities,
        );
    }
}

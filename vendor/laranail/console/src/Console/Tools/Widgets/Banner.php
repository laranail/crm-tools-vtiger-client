<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Enums\BorderStyle;
use Simtabi\Laranail\Console\Tools\Exceptions\FontException;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Color;
use Simtabi\Laranail\Console\Tools\Support\Config;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\Figlet;
use Stringable;

/**
 * A start-of-run masthead / banner designer.
 *
 * Renders a title (optionally as FIGlet big-text) with an optional subtitle,
 * aligned left/centre/right, optionally colourised (solid or gradient), wrapped
 * in a rule or a framed box. Without a font it behaves exactly as before
 * (centred plain title) — backward compatible. A missing font, or big-text too
 * wide for the terminal, falls back to the plain title.
 */
final class Banner implements Stringable
{
    private string $subtitle = '';

    private bool $boxed = false;

    private ?int $width = null;

    private ?string $font = null;

    private string $align = 'center';

    private ?string $color = null;

    /** @var list<string>|null */
    private ?array $gradient = null;

    private ?BorderStyle $border = null;

    private int $padding = 1;

    private readonly Capabilities $capabilities;

    public function __construct(private string $title, ?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->title = ConsoleUIFormatter::sanitizeText($title);

        $configFont = Config::get('banner.font');
        $this->font = is_string($configFont) && $configFont !== '' ? $configFont : null;

        $configWidth = Config::get('banner.width');
        $this->width = is_numeric($configWidth) ? (int) $configWidth : null;
    }

    public static function make(string $title): self
    {
        return new self($title);
    }

    public static function success(string $title): self
    {
        return self::make($title)->theme('success');
    }

    public static function error(string $title): self
    {
        return self::make($title)->theme('error');
    }

    public static function warning(string $title): self
    {
        return self::make($title)->theme('warning');
    }

    public static function info(string $title): self
    {
        return self::make($title)->theme('info');
    }

    /**
     * Apply a named or custom banner theme (font/colour/border/align/padding).
     * Sets defaults; explicit setters called afterwards still override.
     */
    public function theme(string|BannerTheme $theme): self
    {
        $t = BannerTheme::make($theme);

        if ($t->font !== null) {
            $this->font($t->font);
        }
        if ($t->color !== null) {
            $this->color($t->color);
        }
        if ($t->gradient !== null) {
            $this->gradient($t->gradient);
        }
        if ($t->border instanceof BorderStyle) {
            $this->border($t->border);
        }
        if ($t->align !== null) {
            $this->align($t->align);
        }
        if ($t->padding !== null) {
            $this->padding($t->padding);
        }

        return $this;
    }

    public function subtitle(string $subtitle): self
    {
        $this->subtitle = ConsoleUIFormatter::sanitizeText($subtitle);

        return $this;
    }

    public function boxed(bool $boxed = true): self
    {
        $this->boxed = $boxed;

        return $this;
    }

    public function width(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Render the title as big-text using a bundled font name or a `.flf` path.
     * Pass null to disable (plain title).
     */
    public function font(?string $font): self
    {
        $this->font = $font;

        return $this;
    }

    /**
     * Alignment: 'left' | 'center' | 'right'.
     */
    public function align(string $align): self
    {
        $this->align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';

        return $this;
    }

    public function color(string $hex): self
    {
        $this->color = $hex;
        $this->gradient = null;

        return $this;
    }

    /**
     * @param list<string> $stops two or more hex colours
     */
    public function gradient(array $stops): self
    {
        $this->gradient = $stops;
        $this->color = null;

        return $this;
    }

    public function border(BorderStyle $style): self
    {
        $this->border = $style;
        $this->boxed = true;

        return $this;
    }

    public function padding(int $padding): self
    {
        $this->padding = max($padding, 0);

        return $this;
    }

    public function render(): string
    {
        $titleLines = $this->titleLines();

        // Width is the inner content width: the requested width, but at least as
        // wide as the rendered title block (so big-text is never clipped).
        $contentWidth = max(
            $this->width ?? min($this->capabilities->width(), 60),
            DisplayWidth::maxWidth($titleLines),
        );

        $lines = array_map(fn (string $l): string => $this->style($l, $contentWidth), $titleLines);

        if ($this->subtitle !== '') {
            $lines[] = $this->alignLine($this->subtitle, $contentWidth);
        }

        if ($this->boxed || $this->border instanceof BorderStyle) {
            $box = Box::make($lines)->padding($this->padding);

            if ($this->border instanceof BorderStyle) {
                $box->style($this->border);
            }

            return $box->render();
        }

        $rule = new Rule($this->capabilities)->width($contentWidth)->render();

        return implode("\n", [$rule, ...$lines, $rule]);
    }

    /**
     * The title rendered as plain text or, when a font is set and fits the
     * terminal, FIGlet big-text. Falls back to the plain title otherwise.
     *
     * @return list<string>
     */
    private function titleLines(): array
    {
        if ($this->font === null || $this->title === '') {
            return [$this->title];
        }

        try {
            $rendered = Figlet::font($this->font)->render($this->title);
        } catch (FontException) {
            return [$this->title];
        }

        $maxWidth = DisplayWidth::maxWidth($rendered);

        // Too wide for the terminal → fall back to the plain title.
        return $maxWidth > $this->capabilities->width() ? [$this->title] : $rendered;
    }

    /**
     * Align a line to the content width, then apply colour/gradient (measured by
     * display width, so ANSI never skews alignment).
     */
    private function style(string $line, int $width): string
    {
        return $this->colorize($this->alignLine($line, $width));
    }

    private function alignLine(string $line, int $width): string
    {
        return match ($this->align) {
            'left' => DisplayWidth::pad($line, $width),
            'right' => DisplayWidth::padLeft($line, $width),
            default => DisplayWidth::center($line, $width),
        };
    }

    private function colorize(string $line): string
    {
        if ($this->gradient !== null && count($this->gradient) >= 2) {
            return Color::make()->gradient($line, $this->gradient);
        }

        if ($this->color !== null) {
            return Color::make()->fg($line, $this->color);
        }

        return $line;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Document;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Emoji;
use Simtabi\Laranail\Console\Tools\Support\Hyperlink;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Theme\Theme;

/**
 * Renders inline Markdown to a styled string: `**bold**`/`__bold__`, `*italic*`/
 * `_italic_`, `` `code` `` and `[label](url)`, plus `:emoji:` shortcodes. Styling
 * comes from the theme and degrades with the terminal (no colour → markers simply
 * vanish, links become `label (url)`). Input is sanitised before any ANSI is added.
 */
final readonly class InlineMarkup
{
    private Capabilities $capabilities;

    private Theme $theme;

    public function __construct(?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(?Capabilities $capabilities = null, ?Theme $theme = null): self
    {
        return new self($capabilities, $theme);
    }

    public function render(string $text): string
    {
        $text = Emoji::make($this->capabilities)->render(ConsoleUIFormatter::sanitizeText($text));

        $code = $this->theme->style('code');
        $bold = Style::make($this->capabilities)->bold();
        $italic = Style::make($this->capabilities)->italic();
        $link = $this->theme->style('link');

        // Inline code first (its content must not be re-parsed for emphasis).
        $text = (string) preg_replace_callback('/`([^`]+)`/', static fn (array $m): string => $code->apply($m[1]), $text);

        // Bold, then italic (bold consumes the doubled markers first).
        $text = (string) preg_replace_callback('/\*\*([^*]+)\*\*|__([^_]+)__/', static fn (array $m): string => $bold->apply($m[1] !== '' ? $m[1] : ($m[2] ?? '')), $text);
        $text = (string) preg_replace_callback('/\*([^*]+)\*|_([^_]+)_/', static fn (array $m): string => $italic->apply($m[1] !== '' ? $m[1] : ($m[2] ?? '')), $text);

        // Links last: [label](url) → OSC-8 (TTY) or "label (url)".
        return (string) preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            fn (array $m): string => Hyperlink::render($link->apply($m[1]), $m[2], $this->capabilities),
            $text,
        );
    }
}

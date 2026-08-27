<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A fenced code block: an indented, themed gutter-barred block with an optional
 * caption (e.g. a language label). Plain-rendered — syntax highlighting is a
 * future addition. Lines are clipped to the available width (no wrap, like a
 * real code fence).
 */
final class CodeBlock implements Renderable, Stringable
{
    use RendersBlock;

    private ?string $caption = null;

    private ?string $language = null;

    private ?int $width = null;

    private bool $responsive = true;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

    /** @var list<string> */
    private readonly array $lines;

    public function __construct(string $code, ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
        $this->lines = array_map(
            ConsoleUIFormatter::sanitizeText(...),
            preg_split('/\r\n|\r|\n/', $code) ?: [''],
        );
    }

    public static function make(string $code): self
    {
        return new self($code);
    }

    public function caption(string $caption): self
    {
        $this->caption = ConsoleUIFormatter::sanitizeText($caption);

        return $this;
    }

    /**
     * Set the language for basic syntax highlighting (php/json; others render plain).
     */
    public function language(string $language): self
    {
        $this->language = ConsoleUIFormatter::sanitizeText($language);

        return $this;
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

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $gutter = $this->capabilities->supportsUnicode() ? '▏ ' : '| ';
        $cap = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities);
        $codeStyle = $this->theme->style('code');
        $gutterStyle = $this->theme->style('muted');

        $highlighter = SyntaxHighlighter::make($this->capabilities, $this->theme);
        $highlight = $this->language !== null && $highlighter->supports($this->language);

        $out = [];

        if ($this->caption !== null && $this->caption !== '') {
            $out[] = $gutterStyle->apply($this->caption);
        }

        foreach ($this->lines as $line) {
            if ($cap !== null && DisplayWidth::of($line) > $cap - 2) {
                $line = DisplayWidth::truncate($line, max($cap - 2, 1));
            }

            $rendered = $highlight
                ? $highlighter->highlightLine($line, (string) $this->language)
                : $codeStyle->apply($line);

            $out[] = $gutterStyle->apply($gutter) . $rendered;
        }

        return $out;
    }
}

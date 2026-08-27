<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A block quote: a themed left bar + indented, word-wrapped body. Nestable
 * (a quote can wrap another renderable's text). Responsive.
 */
final class BlockQuote implements Renderable, Stringable
{
    use RendersBlock;

    private ?int $width = null;

    private bool $responsive = true;

    private bool $rich = false;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

    public function __construct(private readonly string $text, ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(string $text): self
    {
        return new self($text);
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
     * Treat the body as already-styled (ANSI from InlineMarkup) — wrapped without
     * re-sanitising, so inline emphasis renders inside the quote.
     */
    public function rich(bool $rich = true): self
    {
        $this->rich = $rich;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $bar = $this->theme->style('quote')->apply($this->capabilities->supportsUnicode() ? '│ ' : '| ');
        $barWidth = 2;

        $cap = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? Paragraph::DEFAULT_WIDTH;
        $bodyWidth = max($cap - $barWidth, 1);

        $body = $this->rich
            ? Paragraph::rich($this->text, $this->capabilities, $this->theme)->width($bodyWidth)->responsive(false)->renderLines()
            : Paragraph::make($this->text)->width($bodyWidth)->responsive(false)->style($this->theme->style('quote'))->renderLines();

        return array_map(static fn (string $line): string => $bar . $line, $body);
    }
}

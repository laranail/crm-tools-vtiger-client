<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A short inline quotation — themed (italic/muted) and wrapped in typographic
 * quotation marks (ASCII fallback). For multi-line quotes use {@see BlockQuote}.
 */
final readonly class Quote implements Renderable, Stringable
{
    use RendersBlock;

    private Capabilities $capabilities;

    private Theme $theme;

    private string $text;

    public function __construct(string $text, ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
        $this->text = ConsoleUIFormatter::sanitizeText($text);
    }

    public static function make(string $text): self
    {
        return new self($text);
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        [$open, $close] = $this->capabilities->supportsUnicode() ? ['“', '”'] : ['"', '"'];

        return [$this->theme->style('quote')->apply($open . $this->text . $close)];
    }
}

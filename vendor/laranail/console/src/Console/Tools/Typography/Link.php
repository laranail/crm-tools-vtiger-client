<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Hyperlink;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A themed OSC-8 hyperlink (via {@see Hyperlink}: scheme allow-list + plain
 * `label (url)` fallback when not in a TTY or the URL is unsafe).
 */
final readonly class Link implements Renderable, Stringable
{
    use RendersBlock;

    private Capabilities $capabilities;

    private Theme $theme;

    private string $label;

    public function __construct(string $label, private string $url, ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->label = ConsoleUIFormatter::sanitizeText($label);
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(string $label, string $url): self
    {
        return new self($label, $url);
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $label = $this->theme->style('link')->apply($this->label);

        return [Hyperlink::render($label, $this->url, $this->capabilities)];
    }
}

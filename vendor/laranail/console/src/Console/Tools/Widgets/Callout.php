<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Lang;
use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Stringable;

/**
 * A framed admonition with a status glyph and title spliced into the top rule,
 * e.g. a "warning" or "info" callout. Built on {@see Box}.
 */
final class Callout implements Stringable
{
    private string $title = '';

    private readonly Symbols $symbols;

    private readonly Capabilities $capabilities;

    /**
     * @param list<string> $lines
     */
    public function __construct(private readonly string $status, private readonly array $lines, ?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->symbols = Symbols::for($this->capabilities);
    }

    /**
     * @param list<string>|string $content
     */
    public static function make(string $status, array|string $content): self
    {
        return new self($status, is_array($content) ? array_values($content) : explode("\n", $content));
    }

    public static function success(string $message): self
    {
        return new self('success', explode("\n", $message));
    }

    public static function error(string $message): self
    {
        return new self('error', explode("\n", $message));
    }

    public static function warning(string $message): self
    {
        return new self('warning', explode("\n", $message));
    }

    public static function info(string $message): self
    {
        return new self('info', explode("\n", $message));
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function render(): string
    {
        $label = $this->title !== ''
            ? $this->title
            : Lang::get('widgets.callout.' . $this->status, ucfirst($this->status));

        $heading = trim($this->symbols->get($this->status) . ' ' . $label);

        return Box::make($this->lines)
            ->title($heading)
            ->render();
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

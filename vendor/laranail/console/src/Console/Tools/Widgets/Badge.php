<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Color;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A themed inline badge — a filled, padded label coloured by a semantic role
 * (primary/success/warning/danger/info/muted). Echo-safe; degrades to plain text
 * without colour. See {@see Pill} for the rounded variant (the only subclass).
 */
class Badge implements Renderable, Stringable
{
    use RendersBlock;

    private readonly Theme $theme;

    private readonly Capabilities $capabilities;

    private readonly string $label;

    public function __construct(string $label, private readonly string $role = 'primary', ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->label = ConsoleUIFormatter::sanitizeText($label);
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(string $label, string $role = 'primary'): self
    {
        return new self($label, $role);
    }

    public static function success(string $label): self
    {
        return new self($label, 'success');
    }

    public static function warning(string $label): self
    {
        return new self($label, 'warning');
    }

    public static function danger(string $label): self
    {
        return new self($label, 'danger');
    }

    public static function info(string $label): self
    {
        return new self($label, 'info');
    }

    protected function background(): string
    {
        return $this->theme->color($this->role) ?? '#64748b';
    }

    protected function capabilities(): Capabilities
    {
        return $this->capabilities;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        return [$this->style()->apply(' ' . $this->label . ' ')];
    }

    private function style(): Style
    {
        $bg = $this->background();
        $fg = $this->contrast($bg);

        return Style::make($this->capabilities)->bg($bg)->fg($fg)->bold();
    }

    /**
     * Black or white text, whichever reads better on $hex (luminance).
     */
    private function contrast(string $hex): string
    {
        $parsed = Color::parse($hex);

        if ($parsed === null) {
            return '#ffffff';
        }

        [$r, $g, $b] = Color::hexToRgb($parsed);
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? '#000000' : '#ffffff';
    }
}

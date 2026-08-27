<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A visual button affordance — a themed, bracketed label. Pure (renders a static
 * string); for an interactive choice use {@see ButtonGroup}. `focused()` renders
 * the filled/selected state.
 */
final class Button implements Renderable, Stringable
{
    use RendersBlock;

    private bool $focused = false;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

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

    public function focused(bool $focused = true): self
    {
        $this->focused = $focused;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $color = $this->theme->color($this->role) ?? '#64748b';
        $label = ' ' . $this->label . ' ';

        $style = $this->focused
            ? Style::make($this->capabilities)->bg($color)->fg('#ffffff')->bold()
            : Style::make($this->capabilities)->fg($color)->bold();

        return ['[' . $style->apply($label) . ']'];
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Emoji;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A fluent inline text builder unifying colour/style + emoji + symbols + theme
 * roles into one echo-safe string.
 *
 *   Console::text('Deploying ')->emoji('rocket')->fg('#7c3aed')->bold()->render();
 *   Console::text('Saved')->success()->render();
 */
final class Text implements Stringable
{
    private string $buffer = '';

    private Style $style;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

    private readonly Emoji $emoji;

    private readonly Symbols $symbols;

    public function __construct(string $text = '', ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
        $this->emoji = Emoji::make($this->capabilities);
        $this->symbols = Symbols::for($this->capabilities);
        $this->style = Style::make($this->capabilities);
        $this->buffer = $this->emoji->render(ConsoleUIFormatter::sanitizeText($text));
    }

    public static function make(string $text = ''): self
    {
        return new self($text);
    }

    public function text(string $text): self
    {
        $this->buffer .= $this->emoji->render(ConsoleUIFormatter::sanitizeText($text));

        return $this;
    }

    public function emoji(string $name): self
    {
        $this->buffer .= $this->emoji->get($name);

        return $this;
    }

    public function symbol(string $name): self
    {
        $this->buffer .= $this->symbols->get($name);

        return $this;
    }

    public function space(int $count = 1): self
    {
        $this->buffer .= str_repeat(' ', max($count, 0));

        return $this;
    }

    public function fg(string $color): self
    {
        $this->style = $this->style->fg($color);

        return $this;
    }

    public function bg(string $color): self
    {
        $this->style = $this->style->bg($color);

        return $this;
    }

    public function bold(bool $on = true): self
    {
        $this->style = $this->style->bold($on);

        return $this;
    }

    public function dim(bool $on = true): self
    {
        $this->style = $this->style->dim($on);

        return $this;
    }

    public function italic(bool $on = true): self
    {
        $this->style = $this->style->italic($on);

        return $this;
    }

    public function underline(bool $on = true): self
    {
        $this->style = $this->style->underline($on);

        return $this;
    }

    public function strikethrough(bool $on = true): self
    {
        $this->style = $this->style->strikethrough($on);

        return $this;
    }

    /**
     * Apply a semantic theme role (success/warning/danger/info/primary/accent/muted).
     */
    public function role(string $role): self
    {
        $color = $this->theme->color($role);

        if ($color !== null) {
            $this->style = $this->style->fg($color);
        }

        return $this;
    }

    public function success(): self
    {
        return $this->role('success');
    }

    public function warning(): self
    {
        return $this->role('warning');
    }

    public function danger(): self
    {
        return $this->role('danger');
    }

    public function info(): self
    {
        return $this->role('info');
    }

    public function muted(): self
    {
        return $this->role('muted');
    }

    public function render(): string
    {
        return $this->style->apply($this->buffer);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

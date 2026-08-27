<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Align;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A themed heading (levels 1–6). h1/h2 get an underline rule; deeper levels are
 * styled inline. Responsive: the underline never exceeds the available width.
 */
final class Heading implements Renderable, Stringable
{
    use RendersBlock;

    private int $level = 1;

    private ?int $width = null;

    private bool $responsive = true;

    private string $align = Align::LEFT;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

    private readonly string $text;

    public function __construct(string $text, int $level = 1, ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->text = ConsoleUIFormatter::sanitizeText($text);
        $this->level = $this->clampLevel($level);
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(string $text, int $level = 1): self
    {
        return new self($text, $level);
    }

    public function level(int $level): self
    {
        $this->level = $this->clampLevel($level);

        return $this;
    }

    public function align(string $align): self
    {
        $this->align = Align::normalize($align);

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
        $style = $this->theme->style('h' . $this->level);
        $cap = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities);

        $text = $this->text;
        if ($cap !== null && DisplayWidth::of($text) > $cap) {
            $text = DisplayWidth::truncate($text, $cap);
        }

        $lines = [$style->apply($text)];

        if ($this->level <= 2) {
            $ruleChar = $this->capabilities->supportsUnicode() ? ($this->level === 1 ? '═' : '─') : '-';
            $lines[] = $this->theme->style('rule')->apply(str_repeat($ruleChar, max(DisplayWidth::of($text), 1)));
        }

        if ($this->align === Align::LEFT) {
            return $lines;
        }

        return Align::place($lines, $cap ?? DisplayWidth::maxWidth($lines), $this->align);
    }

    private function clampLevel(int $level): int
    {
        return max(1, min(6, $level));
    }
}

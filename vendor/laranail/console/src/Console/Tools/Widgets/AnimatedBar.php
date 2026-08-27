<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A single-line bar for live progress/loading — determinate (a 0–1 fraction) or
 * indeterminate (a moving block driven by a tick). A pure {@see Renderable};
 * animate it by redrawing through {@see Live}.
 */
final class AnimatedBar implements Renderable, Stringable
{
    use RendersBlock;

    private float $fraction = 0.0;

    private ?int $tick = null;

    private string $label = '';

    private bool $showPercent = true;

    private ?int $width = null;

    private bool $responsive = true;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

    public function __construct(?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(): self
    {
        return new self;
    }

    public function label(string $label): self
    {
        $this->label = ConsoleUIFormatter::sanitizeText($label);

        return $this;
    }

    public function fraction(float $fraction): self
    {
        $this->fraction = max(0.0, min(1.0, $fraction));
        $this->tick = null;

        return $this;
    }

    public function percent(bool $show = true): self
    {
        $this->showPercent = $show;

        return $this;
    }

    public function indeterminate(int $tick): self
    {
        $this->tick = max($tick, 0);

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
        $unicode = $this->capabilities->supportsUnicode();
        $fill = $unicode ? '█' : '#';
        $track = $unicode ? '░' : '-';

        $total = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? 40;
        $prefix = $this->label !== '' ? $this->label . ' ' : '';
        $suffix = ($this->tick === null && $this->showPercent) ? ' ' . str_pad((string) (int) round($this->fraction * 100), 3, ' ', STR_PAD_LEFT) . '%' : '';
        $barArea = max($total - DisplayWidth::of($prefix) - DisplayWidth::of($suffix), 1);

        $fillStyle = $this->theme->style('primary');
        $trackStyle = $this->theme->style('muted');

        if ($this->tick !== null) {
            $bar = $this->indeterminateBar($barArea, $this->tick, $fill, $track, $fillStyle, $trackStyle);
        } else {
            $filled = (int) round($this->fraction * $barArea);
            $bar = $fillStyle->apply(str_repeat($fill, $filled)) . $trackStyle->apply(str_repeat($track, $barArea - $filled));
        }

        return [$prefix . $bar . $suffix];
    }

    private function indeterminateBar(int $area, int $tick, string $fill, string $track, Style $fillStyle, Style $trackStyle): string
    {
        $block = max((int) round($area / 4), 1);
        $span = max($area - $block, 1);
        $cycle = $span * 2;
        $pos = $tick % $cycle;
        $offset = $pos <= $span ? $pos : $cycle - $pos;

        $before = str_repeat($track, $offset);
        $after = str_repeat($track, max($area - $offset - $block, 0));

        return $trackStyle->apply($before) . $fillStyle->apply(str_repeat($fill, $block)) . $trackStyle->apply($after);
    }
}

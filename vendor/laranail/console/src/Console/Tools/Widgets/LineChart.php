<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\ChartContext;
use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Support\BrailleCanvas;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\NumberFormat;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A line chart: one or more numeric series plotted into a {@see BrailleCanvas}
 * (2×4 sub-cell resolution) with a min/max y-axis gutter. Each series is drawn
 * with its own theme colour (one colour per cell, last-writer wins on overlap).
 * Responsive width; degrades to an ASCII plot without Unicode.
 */
final class LineChart implements Renderable, Stringable
{
    use ChartContext;
    use RendersBlock;

    /** @var array<string, list<float>> series name => values */
    private array $series = [];

    private int $height = 8;

    /**
     * @param array<string, list<int|float>>|list<int|float> $series a single series
     *                                                               (list of numbers) or named series (name => list of numbers)
     */
    public function __construct(array $series = [], ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->initContext($capabilities, $theme);

        if ($series !== [] && $this->isSingleSeries($series)) {
            $series = ['' => $series];
        }

        /** @var array<string, list<int|float>> $series */
        foreach ($series as $name => $values) {
            $this->series[(string) $name] = array_map(static fn (int|float $v): float => (float) $v, array_values($values));
        }
    }

    /**
     * @param array<string, list<int|float>>|list<int|float> $series
     */
    public static function make(array $series = []): self
    {
        return new self($series);
    }

    /**
     * @param list<int|float> $values
     */
    public function series(string $name, array $values): self
    {
        $this->series[$name] = array_map(static fn (int|float $v): float => (float) $v, array_values($values));

        return $this;
    }

    public function height(int $rows): self
    {
        $this->height = max($rows, 1);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $points = array_values(array_filter($this->series, static fn (array $v): bool => $v !== []));

        if ($points === []) {
            return [''];
        }

        $all = array_merge(...$points);
        $min = min($all);
        $max = max($all);
        $range = $max - $min ?: 1.0;

        $gutter = max(DisplayWidth::of($this->formatNumber($max)), DisplayWidth::of($this->formatNumber($min)));
        $total = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? 60;
        $cells = max($total - $gutter - 1, 4);

        $canvas = new BrailleCanvas($cells, $this->height, $this->capabilities);
        $pw = $canvas->pixelWidth();
        $ph = $canvas->pixelHeight();

        $pens = [];
        $pen = 1;
        foreach ($this->series as $values) {
            if ($values === []) {
                continue;
            }

            $pens[$pen] = $this->seriesStyle($pen - 1);
            $this->plot($canvas, $values, $min, $range, $pw, $ph, $pen);
            $pen++;
        }

        $body = $canvas->render($pens);

        // Prefix the y-axis gutter: max at the top row, min at the bottom.
        $out = [];
        $last = count($body) - 1;
        foreach ($body as $i => $line) {
            $label = $i === 0 ? $this->formatNumber($max) : ($i === $last ? $this->formatNumber($min) : '');
            $out[] = DisplayWidth::padLeft($label, $gutter) . ' ' . $line;
        }

        return $out;
    }

    /**
     * @param list<float> $values
     */
    private function plot(BrailleCanvas $canvas, array $values, float $min, float $range, int $pw, int $ph, int $pen): void
    {
        $count = count($values);
        $prevX = null;
        $prevY = null;

        foreach ($values as $i => $value) {
            $x = $count > 1 ? (int) round($i / ($count - 1) * ($pw - 1)) : 0;
            $y = (int) round((1.0 - ($value - $min) / $range) * ($ph - 1));

            if ($prevX !== null && $prevY !== null) {
                $canvas->line($prevX, $prevY, $x, $y, $pen);
            } else {
                $canvas->set($x, $y, $pen);
            }

            $prevX = $x;
            $prevY = $y;
        }
    }

    private function seriesStyle(int $index): Style
    {
        $role = $this->cycleRole($index);

        return Style::make($this->capabilities)->fg($this->theme->color($role) ?? '#22d3ee');
    }

    /**
     * @param array<string, list<int|float>>|list<int|float> $series
     */
    private function isSingleSeries(array $series): bool
    {
        return ! is_array(reset($series));
    }

    private function formatNumber(float $value): string
    {
        return NumberFormat::trim($value);
    }
}

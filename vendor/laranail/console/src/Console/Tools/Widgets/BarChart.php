<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\ChartContext;
use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\NumberFormat;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A labelled horizontal bar chart. Bars scale to the largest value and to the
 * available width (responsive). Glyphs + colours come from the theme; degrades to
 * ASCII without Unicode and to plain text without colour.
 */
final class BarChart implements Renderable, Stringable
{
    use ChartContext;
    use RendersBlock;

    /** @var array<array-key, float> */
    private array $data = [];

    private bool $showValues = true;

    /**
     * @param array<string, int|float> $data label => value
     */
    public function __construct(array $data = [], ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        foreach ($data as $label => $value) {
            $this->data[ConsoleUIFormatter::sanitizeText((string) $label)] = (float) $value;
        }
        $this->initContext($capabilities, $theme);
    }

    /**
     * @param array<string, int|float> $data
     */
    public static function make(array $data = []): self
    {
        return new self($data);
    }

    public function add(string $label, int|float $value): self
    {
        $this->data[ConsoleUIFormatter::sanitizeText($label)] = (float) $value;

        return $this;
    }

    public function showValues(bool $show = true): self
    {
        $this->showValues = $show;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        if ($this->data === []) {
            return [''];
        }

        $total = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? 60;
        $unicode = $this->capabilities->supportsUnicode();
        $fill = $unicode ? '█' : '#';
        $track = $unicode ? '░' : '-';

        // Numeric-string labels (e.g. "1") become int array keys — stringify for width/render.
        $labels = array_map(strval(...), array_keys($this->data));
        $labelWidth = min(DisplayWidth::maxWidth($labels), max((int) floor($total / 3), 1));
        $max = max(1.0, max($this->data));
        $valueWidth = $this->showValues ? DisplayWidth::maxWidth(array_map($this->formatValue(...), $this->data)) + 1 : 0;
        $barArea = max($total - $labelWidth - $valueWidth - 1, 1);

        $barStyle = $this->theme->style('primary');
        $trackStyle = $this->theme->style('muted');

        $lines = [];
        foreach (array_values($this->data) as $i => $value) {
            $label = $labels[$i];
            $filled = (int) round($value / $max * $barArea);
            $filled = max(0, min($barArea, $filled));

            $bar = $barStyle->apply(str_repeat($fill, $filled)) . $trackStyle->apply(str_repeat($track, $barArea - $filled));
            $line = DisplayWidth::pad(DisplayWidth::truncate($label, $labelWidth), $labelWidth) . ' ' . $bar;

            if ($this->showValues) {
                $line .= ' ' . $this->theme->style('muted')->apply($this->formatValue($value));
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function formatValue(float $value): string
    {
        return NumberFormat::trim($value);
    }
}

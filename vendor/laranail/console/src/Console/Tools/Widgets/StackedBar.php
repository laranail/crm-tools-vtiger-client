<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\ChartContext;
use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\NumberFormat;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A single horizontal **proportion (stacked) bar**: one bar split into segments
 * sized by each value's share of the total, each in a distinct theme colour, with
 * a legend (`swatch label  value (pct%)`). The terminal-friendly alternative to a
 * pie chart. Responsive width; without colour, segments use a distinct glyph cycle
 * so they stay readable; the legend is always shown.
 */
final class StackedBar implements Renderable, Stringable
{
    use ChartContext;
    use RendersBlock;

    /** Palette roles cycled across segments. */
    private const array GLYPHS_UNICODE = ['█', '▓', '▒', '░'];

    private const array GLYPHS_ASCII = ['#', '=', '*', '+'];

    /** @var array<string, float> */
    private array $data = [];

    private bool $showLegend = true;

    /**
     * @param array<string, int|float> $data label => value
     */
    public function __construct(array $data = [], ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        foreach ($data as $label => $value) {
            $this->data[ConsoleUIFormatter::sanitizeText((string) $label)] = max(0.0, (float) $value);
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
        $this->data[ConsoleUIFormatter::sanitizeText($label)] = max(0.0, (float) $value);

        return $this;
    }

    public function showLegend(bool $show = true): self
    {
        $this->showLegend = $show;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $total = array_sum($this->data);

        if ($this->data === [] || $total <= 0.0) {
            return [''];
        }

        $width = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? 60;
        $colour = $this->capabilities->supportsColor();
        $unicode = $this->capabilities->supportsUnicode();

        $bar = '';
        $used = 0;
        $index = 0;
        $count = count($this->data);

        foreach ($this->data as $value) {
            // Last segment takes the remainder so the bar fills exactly.
            $segWidth = $index === $count - 1
                ? max($width - $used, 0)
                : (int) round($value / $total * $width);
            $segWidth = max(0, min($segWidth, $width - $used));
            $used += $segWidth;

            $bar .= $this->segment($index, $segWidth, $colour, $unicode);
            $index++;
        }

        $lines = [$bar];

        if ($this->showLegend) {
            $index = 0;
            foreach ($this->data as $label => $value) {
                $swatch = $this->segment($index, 2, $colour, $unicode);
                $pct = (int) round($value / $total * 100);
                $lines[] = $swatch . ' ' . $label . '  ' . $this->theme->style('muted')->apply($this->formatValue($value) . " ({$pct}%)");
                $index++;
            }
        }

        return $lines;
    }

    private function segment(int $index, int $segWidth, bool $colour, bool $unicode): string
    {
        if ($segWidth <= 0) {
            return '';
        }

        if ($colour) {
            $glyph = $unicode ? '█' : '#';
            $role = $this->cycleRole($index);

            return $this->theme->style($role)->apply(str_repeat($glyph, $segWidth));
        }

        // No colour: distinguish segments by a glyph cycle instead.
        $glyphs = $unicode ? self::GLYPHS_UNICODE : self::GLYPHS_ASCII;

        return str_repeat($glyphs[$index % count($glyphs)], $segWidth);
    }

    private function formatValue(float $value): string
    {
        return NumberFormat::trim($value);
    }
}

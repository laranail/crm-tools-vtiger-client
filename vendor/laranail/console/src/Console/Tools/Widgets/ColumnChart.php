<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\ChartContext;
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
 * A labelled vertical bar (column) chart. Bars use block-eighths (`▁▂▃▄▅▆▇█`) for
 * sub-row precision and scale to the largest value; the layout is responsive
 * (columns shrink to fit). Colours come from the theme; degrades to `#` columns
 * without Unicode and drops colour without colour support.
 */
final class ColumnChart implements Renderable, Stringable
{
    use ChartContext;
    use RendersBlock;

    private const array EIGHTHS = ['▁', '▂', '▃', '▄', '▅', '▆', '▇'];

    /** @var array<array-key, float> */
    private array $data = [];

    private int $height = 8;

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
        if ($this->data === []) {
            return [''];
        }

        $total = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? 60;
        $count = count($this->data);
        $values = array_values($this->data);
        // Numeric-string labels (e.g. "1") become int array keys — stringify for width math.
        $labels = array_map(strval(...), array_keys($this->data));
        $colWidth = max(1, min(DisplayWidth::maxWidth($labels), (int) floor(($total + 1) / $count) - 1));
        $unicode = $this->capabilities->supportsUnicode();
        $max = max(1.0, max($this->data));
        $style = $this->theme->style('primary');

        $lines = [];
        for ($row = $this->height - 1; $row >= 0; $row--) {
            $cells = [];
            foreach ($values as $value) {
                $cells[] = $this->cell($value, $max, $row, $colWidth, $unicode, $style);
            }
            $lines[] = rtrim(implode(' ', $cells));
        }

        // Label row beneath the columns.
        $muted = $this->theme->style('muted');
        $labelCells = array_map(
            fn (string $label): string => $muted->apply(DisplayWidth::pad(DisplayWidth::truncate($label, $colWidth), $colWidth)),
            $labels,
        );
        $lines[] = rtrim(implode(' ', $labelCells));

        return $lines;
    }

    private function cell(float $value, float $max, int $row, int $colWidth, bool $unicode, Style $style): string
    {
        if (! $unicode) {
            $filled = (int) round($value / $max * $this->height);
            $char = $row < $filled ? '#' : ' ';

            return $char === ' ' ? str_repeat(' ', $colWidth) : $style->apply(str_repeat($char, $colWidth));
        }

        $eighths = (int) round($value / $max * $this->height * 8);
        $cell = max(0, min(8, $eighths - $row * 8));

        if ($cell === 0) {
            return str_repeat(' ', $colWidth);
        }

        $char = $cell >= 8 ? '█' : self::EIGHTHS[$cell - 1];

        return $style->apply(str_repeat($char, $colWidth));
    }
}

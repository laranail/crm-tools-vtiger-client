<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\ChartContext;
use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Color;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A heatmap: a 2D matrix whose cells are coloured by intensity (a low→high blend,
 * truecolor→256→16 downgrade handled by {@see Style}). Without colour it falls back
 * to a Unicode shade ramp (`░▒▓█`) or an ASCII ramp. Optional row/column labels.
 * Cells shrink to keep the grid within the terminal (columns are never dropped).
 */
final class Heatmap implements Renderable, Stringable
{
    use ChartContext;
    use RendersBlock;

    private const array SHADES_UNICODE = [' ', '░', '▒', '▓', '█'];

    private const array SHADES_ASCII = [' ', '.', ':', '+', '#'];

    /** @var list<list<float>> */
    private array $matrix = [];

    /** @var list<string> */
    private array $rowLabels = [];

    /** @var list<string> */
    private array $colLabels = [];

    private int $cellWidth = 2;

    /**
     * @param list<list<int|float>> $matrix rows of values
     */
    public function __construct(array $matrix = [], ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        foreach ($matrix as $row) {
            $this->matrix[] = array_map(static fn (int|float $v): float => (float) $v, array_values($row));
        }
        $this->initContext($capabilities, $theme);
    }

    /**
     * @param list<list<int|float>> $matrix
     */
    public static function make(array $matrix = []): self
    {
        return new self($matrix);
    }

    /**
     * @param list<string> $rows
     * @param list<string> $cols
     */
    public function labels(array $rows = [], array $cols = []): self
    {
        $this->rowLabels = array_map(ConsoleUIFormatter::sanitizeText(...), array_values($rows));
        $this->colLabels = array_map(ConsoleUIFormatter::sanitizeText(...), array_values($cols));

        return $this;
    }

    public function cellWidth(int $width): self
    {
        $this->cellWidth = max($width, 1);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        if ($this->matrix === [] || $this->matrix[0] === []) {
            return [''];
        }

        $flat = array_merge(...$this->matrix);

        if ($flat === []) {
            return [''];
        }

        $min = min($flat);
        $max = max($flat);
        $range = $max - $min ?: 1.0;

        $cols = max(array_map(count(...), $this->matrix));
        $rowLabelWidth = $this->rowLabels === [] ? 0 : DisplayWidth::maxWidth($this->rowLabels) + 1;
        $cellWidth = $this->fitCellWidth($cols, $rowLabelWidth);

        $low = $this->theme->color('info') ?? '#1d4ed8';
        $high = $this->theme->color('danger') ?? '#dc2626';
        $colour = $this->capabilities->supportsColor();
        $unicode = $this->capabilities->supportsUnicode();

        $lines = [];

        if ($this->colLabels !== []) {
            $lines[] = $this->columnHeader($cols, $cellWidth, $rowLabelWidth);
        }

        foreach ($this->matrix as $r => $row) {
            $line = $rowLabelWidth > 0
                ? DisplayWidth::pad(DisplayWidth::truncate($this->rowLabels[$r] ?? '', $rowLabelWidth - 1), $rowLabelWidth)
                : '';

            for ($c = 0; $c < $cols; $c++) {
                $value = $row[$c] ?? $min;
                $t = ($value - $min) / $range;
                $line .= $this->cell($t, $cellWidth, $colour, $unicode, $low, $high);
            }

            $lines[] = rtrim($line);
        }

        return $lines;
    }

    private function cell(float $t, int $cellWidth, bool $colour, bool $unicode, string $low, string $high): string
    {
        if ($colour) {
            $hex = Color::blend($low, $high, $t);

            return Style::make($this->capabilities)->bg($hex)->apply(str_repeat(' ', $cellWidth));
        }

        $ramp = $unicode ? self::SHADES_UNICODE : self::SHADES_ASCII;
        $shade = $ramp[(int) round($t * (count($ramp) - 1))];

        return str_repeat($shade, $cellWidth);
    }

    private function columnHeader(int $cols, int $cellWidth, int $rowLabelWidth): string
    {
        $header = str_repeat(' ', $rowLabelWidth);

        for ($c = 0; $c < $cols; $c++) {
            $label = $this->colLabels[$c] ?? '';
            $header .= DisplayWidth::pad(DisplayWidth::truncate($label, $cellWidth), $cellWidth);
        }

        return $this->theme->style('muted')->apply(rtrim($header));
    }

    private function fitCellWidth(int $cols, int $rowLabelWidth): int
    {
        if (! $this->responsive && $this->width === null) {
            return $this->cellWidth;
        }

        $total = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? 80;
        $available = max($total - $rowLabelWidth, $cols);

        return max(1, min($this->cellWidth, intdiv($available, $cols)));
    }
}

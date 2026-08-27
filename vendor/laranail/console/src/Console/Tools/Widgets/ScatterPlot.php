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
 * A scatter plot: `(x, y)` points placed into a {@see BrailleCanvas} (2×4 sub-cell
 * resolution), auto-scaled to fit, with a min/max y-axis gutter. Themed; responsive
 * width; degrades to an ASCII plot without Unicode.
 */
final class ScatterPlot implements Renderable, Stringable
{
    use ChartContext;
    use RendersBlock;

    /** @var list<array{0: float, 1: float}> */
    private array $points = [];

    private int $height = 8;

    /**
     * @param list<array{0: int|float, 1: int|float}> $points
     */
    public function __construct(array $points = [], ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        foreach ($points as $point) {
            $this->points[] = [(float) $point[0], (float) $point[1]];
        }
        $this->initContext($capabilities, $theme);
    }

    /**
     * @param list<array{0: int|float, 1: int|float}> $points
     */
    public static function make(array $points = []): self
    {
        return new self($points);
    }

    public function add(int|float $x, int|float $y): self
    {
        $this->points[] = [(float) $x, (float) $y];

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
        if ($this->points === []) {
            return [''];
        }

        $xs = array_column($this->points, 0);
        $ys = array_column($this->points, 1);
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);
        $rangeX = $maxX - $minX ?: 1.0;
        $rangeY = $maxY - $minY ?: 1.0;

        $gutter = max(DisplayWidth::of($this->formatNumber($maxY)), DisplayWidth::of($this->formatNumber($minY)));
        $total = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? 60;
        $cells = max($total - $gutter - 1, 4);

        $canvas = new BrailleCanvas($cells, $this->height, $this->capabilities);
        $pw = $canvas->pixelWidth();
        $ph = $canvas->pixelHeight();

        foreach ($this->points as [$x, $y]) {
            $px = (int) round(($x - $minX) / $rangeX * ($pw - 1));
            $py = (int) round((1.0 - ($y - $minY) / $rangeY) * ($ph - 1));
            $canvas->set($px, $py, 1);
        }

        $style = Style::make($this->capabilities)->fg($this->theme->color('primary') ?? '#22d3ee');
        $body = $canvas->render([1 => $style]);

        $out = [];
        $last = count($body) - 1;
        foreach ($body as $i => $line) {
            $label = $i === 0 ? $this->formatNumber($maxY) : ($i === $last ? $this->formatNumber($minY) : '');
            $out[] = DisplayWidth::padLeft($label, $gutter) . ' ' . $line;
        }

        return $out;
    }

    private function formatNumber(float $value): string
    {
        return NumberFormat::trim($value);
    }
}

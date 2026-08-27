<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Concerns\ChartContext;
use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\NumberFormat;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A frequency histogram: bins a set of raw values and renders the distribution as
 * a {@see ColumnChart}. The bin count defaults to Sturges' rule (`⌈log2 n⌉ + 1`)
 * and can be set with `bins()`.
 */
final class Histogram implements Renderable, Stringable
{
    use ChartContext;
    use RendersBlock;

    /** @var list<float> */
    private array $values = [];

    private ?int $bins = null;

    private int $height = 8;

    /**
     * @param list<int|float> $values
     */
    public function __construct(array $values = [], ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->values = array_map(static fn (int|float $v): float => (float) $v, array_values($values));
        $this->initContext($capabilities, $theme);
    }

    /**
     * @param list<int|float> $values
     */
    public static function make(array $values = []): self
    {
        return new self($values);
    }

    public function bins(int $bins): self
    {
        $this->bins = max($bins, 1);

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
        $n = count($this->values);

        if ($n === 0) {
            return [''];
        }

        $min = min($this->values);
        $max = max($this->values);
        $range = $max - $min;
        $bins = $this->bins ?? max(1, (int) ceil(log($n, 2)) + 1);

        $counts = array_fill(0, $bins, 0);

        if ($range <= 0.0) {
            $counts = [$n];
            $bins = 1;
        } else {
            $binWidth = $range / $bins;
            foreach ($this->values as $value) {
                $index = min($bins - 1, (int) floor(($value - $min) / $binWidth));
                $counts[$index]++;
            }
        }

        $data = [];
        $binWidth = $range > 0.0 ? $range / $bins : 0.0;
        foreach ($counts as $index => $count) {
            $data[$this->formatNumber($min + $index * $binWidth)] = $count;
        }

        $chart = new ColumnChart($data, $this->capabilities, $this->theme);
        $chart->height($this->height)->responsive($this->responsive);

        if ($this->width !== null) {
            $chart->width($this->width);
        }

        return $chart->renderLines();
    }

    private function formatNumber(float $value): string
    {
        return NumberFormat::trim($value);
    }
}

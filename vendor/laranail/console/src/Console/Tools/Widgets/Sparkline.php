<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\NumberFormat;
use Stringable;

/**
 * An inline mini-chart built from block-eighths (`▁▂▃▄▅▆▇█`).
 *
 * Unicode-only: without it there is no faithful ASCII equivalent, so the
 * sparkline degrades to a compact numeric summary.
 */
final readonly class Sparkline implements Stringable
{
    private const array TICKS = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];

    private bool $unicode;

    /**
     * @param list<int|float> $values
     */
    public function __construct(private array $values, ?Capabilities $capabilities = null)
    {
        $this->unicode = ($capabilities ?? Capabilities::detect())->supportsUnicode();
    }

    /**
     * @param list<int|float> $values
     */
    public static function make(array $values): self
    {
        return new self($values);
    }

    public function render(): string
    {
        if ($this->values === []) {
            return '';
        }

        $min = min($this->values);
        $max = max($this->values);

        if (! $this->unicode) {
            return sprintf('min %s, max %s, n %d', $this->trim($min), $this->trim($max), count($this->values));
        }

        // A flat series has no range: render a mid tick so it doesn't read as
        // "all minimum".
        if ($max === $min) {
            return str_repeat(self::TICKS[intdiv(count(self::TICKS), 2)], count($this->values));
        }

        $span = $max - $min;
        $out = '';
        foreach ($this->values as $value) {
            $level = (int) round((($value - $min) / $span) * (count(self::TICKS) - 1));
            $out .= self::TICKS[$level];
        }

        return $out;
    }

    private function trim(int|float $n): string
    {
        return NumberFormat::trim((float) $n);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

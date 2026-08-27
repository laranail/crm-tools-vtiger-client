<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Stringable;

/**
 * Flow a flat list of items into N balanced columns (like `ls` / `artisan list`).
 *
 * Items fill column-major (down, then across); each column is padded to its own
 * widest item (display-width aware). With no explicit count it auto-fits the
 * terminal width.
 */
final class Columns implements Stringable
{
    /** @var list<string> */
    private array $items;

    private int $count = 0; // 0 = auto-fit

    private int $gap = 2;

    private readonly Capabilities $capabilities;

    /**
     * @param list<string> $items
     */
    public function __construct(array $items, ?Capabilities $capabilities = null)
    {
        $this->items = array_values(array_map(ConsoleUIFormatter::sanitizeText(...), $items));
        $this->capabilities = $capabilities ?? Capabilities::detect();
    }

    /**
     * @param list<string> $items
     */
    public static function make(array $items): self
    {
        return new self($items);
    }

    public function columns(int $count): self
    {
        $this->count = max($count, 0);

        return $this;
    }

    public function gap(int $gap): self
    {
        $this->gap = max($gap, 0);

        return $this;
    }

    public function render(): string
    {
        if ($this->items === []) {
            return '';
        }

        $total = count($this->items);
        $cols = $this->resolveColumns();
        $rows = (int) ceil($total / $cols);

        // Per-column width (column-major: column $c holds rows $c*$rows .. +$rows-1).
        $colWidth = [];
        for ($c = 0; $c < $cols; $c++) {
            $width = 0;
            for ($r = 0; $r < $rows; $r++) {
                $i = $c * $rows + $r;
                if ($i < $total) {
                    $width = max($width, DisplayWidth::of($this->items[$i]));
                }
            }
            $colWidth[$c] = $width;
        }

        $lines = [];
        for ($r = 0; $r < $rows; $r++) {
            $cells = [];
            for ($c = 0; $c < $cols; $c++) {
                $i = $c * $rows + $r;
                if ($i >= $total) {
                    continue;
                }
                $cells[] = DisplayWidth::pad($this->items[$i], $colWidth[$c]);
            }
            $lines[] = rtrim(implode(str_repeat(' ', $this->gap), $cells));
        }

        return implode("\n", $lines);
    }

    private function resolveColumns(): int
    {
        $total = count($this->items);

        if ($this->count > 0) {
            return min($this->count, $total);
        }

        $widest = DisplayWidth::maxWidth($this->items);

        $cols = $widest > 0
            ? intdiv($this->capabilities->width() + $this->gap, $widest + $this->gap)
            : 1;

        return max(1, min($cols, $total));
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

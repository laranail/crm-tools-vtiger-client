<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * A virtual pixel grid rendered with braille dots: each terminal cell packs a 2×4
 * block of pixels (U+2800 + dot bitmask), giving sub-character resolution for line
 * and scatter plots. An optional per-cell "pen" id lets a caller colour cells —
 * one colour per cell, since a single braille glyph can't mix colours per dot
 * (last writer wins). Without Unicode it degrades to a coarse one-char-per-cell
 * ASCII plot.
 */
final class BrailleCanvas
{
    /** Dot bitmask indexed by [row 0..3][col 0..1]. */
    private const array DOTS = [
        [0x01, 0x08],
        [0x02, 0x10],
        [0x04, 0x20],
        [0x40, 0x80],
    ];

    private readonly int $width;

    private readonly int $height;

    private readonly int $pixelWidth;

    private readonly int $pixelHeight;

    private readonly Capabilities $capabilities;

    /** @var array<int, array<int, int>> cellY => cellX => dot mask */
    private array $dots = [];

    /** @var array<int, array<int, int>> cellY => cellX => pen id */
    private array $pens = [];

    public function __construct(int $width, int $height, ?Capabilities $capabilities = null)
    {
        $this->width = max($width, 1);
        $this->height = max($height, 1);
        $this->pixelWidth = $this->width * 2;
        $this->pixelHeight = $this->height * 4;
        $this->capabilities = $capabilities ?? Capabilities::detect();
    }

    public static function make(int $width, int $height, ?Capabilities $capabilities = null): self
    {
        return new self($width, $height, $capabilities);
    }

    public function pixelWidth(): int
    {
        return $this->pixelWidth;
    }

    public function pixelHeight(): int
    {
        return $this->pixelHeight;
    }

    /**
     * Light a single pixel (origin top-left). Out-of-bounds pixels are ignored.
     */
    public function set(int $x, int $y, int $pen = 0): self
    {
        if ($x < 0 || $y < 0 || $x >= $this->pixelWidth || $y >= $this->pixelHeight) {
            return $this;
        }

        $cellY = $y >> 2;
        $cellX = $x >> 1;
        $this->dots[$cellY][$cellX] = ($this->dots[$cellY][$cellX] ?? 0) | self::DOTS[$y & 3][$x & 1];
        $this->pens[$cellY][$cellX] = $pen;

        return $this;
    }

    /**
     * Draw a straight line between two pixels (Bresenham).
     */
    public function line(int $x0, int $y0, int $x1, int $y1, int $pen = 0): self
    {
        $dx = abs($x1 - $x0);
        $dy = -abs($y1 - $y0);
        $sx = $x0 < $x1 ? 1 : -1;
        $sy = $y0 < $y1 ? 1 : -1;
        $err = $dx + $dy;

        while (true) {
            $this->set($x0, $y0, $pen);

            if ($x0 === $x1 && $y0 === $y1) {
                break;
            }

            $e2 = 2 * $err;

            if ($e2 >= $dy) {
                $err += $dy;
                $x0 += $sx;
            }

            if ($e2 <= $dx) {
                $err += $dx;
                $y0 += $sy;
            }
        }

        return $this;
    }

    /**
     * Render to `height` lines of `width` cells. `$pens` maps a pen id → `Style`
     * (a cell with no matching pen renders plain).
     *
     * @param array<int, Style> $pens
     * @return list<string>
     */
    public function render(array $pens = []): array
    {
        $unicode = $this->capabilities->supportsUnicode();
        $lines = [];

        for ($cy = 0; $cy < $this->height; $cy++) {
            $line = '';

            for ($cx = 0; $cx < $this->width; $cx++) {
                $mask = $this->dots[$cy][$cx] ?? 0;

                if ($mask === 0) {
                    $line .= ' ';

                    continue;
                }

                $glyph = $unicode ? mb_chr(0x2800 + $mask) : '*';
                $pen = $this->pens[$cy][$cx] ?? 0;
                $line .= isset($pens[$pen]) ? $pens[$pen]->apply($glyph) : $glyph;
            }

            $lines[] = $line;
        }

        return $lines;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Contracts;

/**
 * Something that renders to a rectangular block of lines, so it can be composed
 * inside a Panel (or nested in another panel).
 *
 * @api Stable contract (SemVer-covered).
 */
interface Renderable
{
    /**
     * The rendered block, one string per line (each the same display width).
     *
     * @return list<string>
     */
    public function renderLines(): array;

    /**
     * Total display width of the rendered block (including any border).
     */
    public function totalWidth(): int;

    /**
     * Total height (line count) of the rendered block (including any border).
     */
    public function totalHeight(): int;
}

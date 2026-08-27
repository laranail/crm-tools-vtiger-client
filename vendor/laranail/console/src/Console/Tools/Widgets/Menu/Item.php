<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\Menu;

/**
 * An entry in a {@see Menu}.
 */
interface Item
{
    /**
     * The display label.
     */
    public function label(): string;

    /**
     * Whether the cursor can land on / activate this item.
     */
    public function selectable(): bool;
}

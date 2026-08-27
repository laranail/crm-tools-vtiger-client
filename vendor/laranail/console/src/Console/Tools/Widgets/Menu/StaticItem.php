<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\Menu;

/**
 * A non-selectable label / separator / line break.
 */
final readonly class StaticItem implements Item
{
    public function __construct(private string $label = '') {}

    public function label(): string
    {
        return $this->label;
    }

    public function selectable(): bool
    {
        return false;
    }
}

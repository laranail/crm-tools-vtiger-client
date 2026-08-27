<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\Menu;

/**
 * A selectable item that opens a nested {@see Menu}.
 */
final readonly class SubMenuItem implements Item
{
    public function __construct(
        private string $label,
        public Menu $submenu,
    ) {}

    public function label(): string
    {
        return $this->label;
    }

    public function selectable(): bool
    {
        return true;
    }
}

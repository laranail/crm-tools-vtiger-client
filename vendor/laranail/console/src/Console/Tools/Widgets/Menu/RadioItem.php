<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\Menu;

/**
 * A single-select item carrying a value (selecting one clears its siblings).
 */
final class RadioItem implements Item
{
    public function __construct(
        public readonly mixed $value,
        private readonly string $label,
        public bool $checked = false,
        public readonly string $group = 'default',
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

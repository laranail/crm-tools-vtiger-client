<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\Menu;

/**
 * A toggleable multi-select item carrying a value.
 */
final class CheckboxItem implements Item
{
    public function __construct(
        public readonly mixed $value,
        private readonly string $label,
        public bool $checked = false,
    ) {}

    public function label(): string
    {
        return $this->label;
    }

    public function selectable(): bool
    {
        return true;
    }

    public function toggle(): void
    {
        $this->checked = ! $this->checked;
    }
}

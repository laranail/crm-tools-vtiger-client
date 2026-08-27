<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\Menu;

use Closure;

/**
 * A selectable option carrying a value (and optional callback) alongside its label.
 */
final readonly class MenuItem implements Item
{
    public function __construct(
        public mixed $value,
        private string $label,
        public ?Closure $callback = null,
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

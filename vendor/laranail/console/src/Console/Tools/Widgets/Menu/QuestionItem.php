<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\Menu;

/**
 * A selectable item that, when chosen, asks a free-text question (bridged to
 * laravel/prompts) and returns the typed answer.
 */
final readonly class QuestionItem implements Item
{
    public function __construct(
        private string $label,
        public string $placeholder = '',
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

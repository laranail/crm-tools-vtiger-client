<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;

trait HasViewComposers
{
    /** @var array<string, Closure|string> Map of view name => composer */
    public array $viewComposers = [];

    /**
     * @param string|array<int, string> $view
     * @param Closure|string $viewComposer
     */
    public function hasViewComposer($view, $viewComposer): static
    {
        if (! is_array($view)) {
            $view = [$view];
        }

        foreach ($view as $viewName) {
            $this->viewComposers[$viewName] = $viewComposer;
        }

        return $this;
    }
}

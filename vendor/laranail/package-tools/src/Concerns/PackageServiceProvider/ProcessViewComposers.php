<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\View;

trait ProcessViewComposers
{
    protected function bootPackageViewComposers(): self
    {
        // Legacy composers registered directly on the Package via HasViewComposers.
        foreach ($this->package->viewComposers as $viewName => $viewComposer) {
            View::composer($viewName, $viewComposer);
        }

        // Enhanced registry/global composers and creators (HasEnhancedViewComposers).
        $this->package->bootPackageViewComposers();

        return $this;
    }
}

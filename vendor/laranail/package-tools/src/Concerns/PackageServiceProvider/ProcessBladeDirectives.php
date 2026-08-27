<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\Blade;

trait ProcessBladeDirectives
{
    /**
     * Register the package's Blade directives.
     */
    protected function bootPackageBladeDirectives(): self
    {
        if (empty($this->package->bladeDirectives)) {
            return $this;
        }

        foreach ($this->package->bladeDirectives as $name => $handler) {
            Blade::directive($name, $handler);
        }

        return $this;
    }
}

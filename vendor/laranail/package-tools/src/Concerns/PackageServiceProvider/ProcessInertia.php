<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Str;

trait ProcessInertia
{
    protected function bootPackageInertia(): self
    {
        if (! $this->package->hasInertiaComponents) {
            return $this;
        }

        $namespace = $this->package->viewNamespace;
        // packageView() falls back to the short name, but is typed ?string
        // because the view-namespace property is nullable; default to the
        // short name so the value passed downstream is always a string.
        $viewName = $this->packageView($namespace) ?? $this->package->shortName();
        $directoryName = Str::of($viewName)->studly()->remove('-')->value();
        $vendorComponents = $this->package->basePath('/resources/js/Pages');
        $appComponents = base_path("resources/js/Pages/{$directoryName}");

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$vendorComponents => $appComponents],
                "{$viewName}-inertia-components"
            );
        }

        return $this;
    }
}

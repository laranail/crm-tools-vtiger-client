<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;

trait ProcessViews
{
    protected function bootPackageViews(): self
    {
        if (! $this->package->hasViews) {
            return $this;
        }

        $viewNamespace = $this->package->viewNamespace();
        $viewsPath = $this->package->basePath('/resources/views');
        $vendorViews = realpath($viewsPath) ?: $viewsPath;
        $appViews = base_path("resources/views/vendor/{$viewNamespace}");

        $this->loadViewsFrom($vendorViews, $viewNamespace);

        // Blade's component-tag pattern is x[-\:]([\w\-\:\.]*) -- no forward slash -- so a tag
        // written against the canonical `vendor/package` namespace truncates at the slash and is
        // never compiled. Alias the hyphen form over the paths loadViewsFrom() just resolved, which
        // includes the application's published override directory, so both spellings find the same
        // file and publishing an override still wins for component tags.
        $componentPrefix = $this->package->componentPrefix();

        if ($componentPrefix !== $viewNamespace) {
            // getHints() is on FileViewFinder rather than the interface, so an application running
            // a custom finder falls back to the package path alone. It loses the published-override
            // lookup for component tags, but the tags still resolve.
            $finder = View::getFinder();

            View::addNamespace(
                $componentPrefix,
                $finder instanceof FileViewFinder
                    ? ($finder->getHints()[$viewNamespace] ?? [$vendorViews])
                    : [$vendorViews],
            );
        }

        if ($this->app->runningInConsole()) {
            $publishTag = $this->package->getNamespacedPublishTag('views');

            $this->publishes([$vendorViews => $appViews], $publishTag);
        }

        return $this;
    }
}

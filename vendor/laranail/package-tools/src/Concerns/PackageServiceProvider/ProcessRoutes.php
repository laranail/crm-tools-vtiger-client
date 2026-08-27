<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\Route;

trait ProcessRoutes
{
    protected function bootPackageRoutes(): self
    {
        if (empty($this->package->routeFileNames)
            && empty($this->package->conditionalRouteFileNames)
            && $this->package->routeGroups === []) {
            return $this;
        }

        foreach ($this->package->routeFileNames as $routeFileName) {
            $this->loadRoutesFrom("{$this->package->basePath('/routes/')}{$routeFileName}.php");
        }

        foreach ($this->package->conditionalRouteFileNames as $conditional) {
            if (! (bool) config($conditional['key'], $conditional['default'])) {
                continue;
            }

            foreach ($conditional['files'] as $routeFileName) {
                $this->loadRoutesFrom("{$this->package->basePath('/routes/')}{$routeFileName}.php");
            }
        }

        $this->bootPackageRouteGroups();

        return $this;
    }

    /**
     * Load the declarative route groups inside a
     * `Route::middleware(…)->prefix(…)->group()` wrapper. Unlike
     * loadRoutesFrom(), the group loader is NOT auto-guarded against cached
     * routes, so we guard it explicitly — otherwise a cached-route deploy
     * would re-register (and error on) these groups.
     */
    private function bootPackageRouteGroups(): void
    {
        if ($this->package->routeGroups === [] || $this->app->routesAreCached()) {
            return;
        }

        foreach ($this->package->routeGroups as $group) {
            if (! $group->shouldRegister()) {
                continue;
            }

            $registrar = Route::middleware($group->resolveMiddleware());

            $prefix = $group->resolvePrefix();
            if ($prefix !== '') {
                $registrar = $registrar->prefix($prefix);
            }

            if ($group->nameValue() !== null) {
                $registrar = $registrar->name($group->nameValue());
            }

            if ($group->domainValue() !== null) {
                $registrar = $registrar->domain($group->domainValue());
            }

            $registrar->group($this->package->basePath('/' . ltrim($group->file(), '/')));
        }
    }
}

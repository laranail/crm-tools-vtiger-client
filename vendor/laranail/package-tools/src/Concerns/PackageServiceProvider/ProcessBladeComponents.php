<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\Blade;

trait ProcessBladeComponents
{
    protected function bootPackageBladeComponents(): self
    {
        // both hasComponentNamespace() (previously write-only — never
        // applied; fixed in 2.0) and hasBladeComponentNamespace() register
        // through Blade::componentNamespace here
        $namespaces = [...$this->package->getComponentNamespaces(), ...$this->package->bladeComponentNamespaces];

        foreach ($namespaces as $classNamespace => $prefix) {
            Blade::componentNamespace($classNamespace, $prefix);
        }

        foreach ($this->package->bladeComponentAliases as $alias => $componentClass) {
            Blade::component($alias, $componentClass);
        }

        if (empty($this->package->viewComponents)) {
            return $this;
        }

        foreach ($this->package->viewComponents as $componentClass => $prefix) {
            $this->loadViewComponentsAs($prefix, [$componentClass]);
        }

        if ($this->app->runningInConsole()) {
            $vendorComponents = $this->package->basePath('/src/Components');
            $appComponents = base_path("app/View/Components/vendor/{$this->package->shortName()}");

            $publishTag = $this->package->getNamespacedPublishTag('components');

            $this->publishes([$vendorComponents => $appComponents], $publishTag);
        }

        return $this;
    }
}

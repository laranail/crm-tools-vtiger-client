<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasBladeComponents
{
    /** @var array<string, string> Map of component name => prefix */
    public array $viewComponents = [];

    /** @var array<string, string> Map of class namespace => tag prefix */
    public array $bladeComponentNamespaces = [];

    /** @var array<string, string> Map of exact alias => component class */
    public array $bladeComponentAliases = [];

    public function hasViewComponent(string $prefix, string $viewComponentName): static
    {
        $this->viewComponents[$viewComponentName] = $prefix;

        return $this;
    }

    /**
     * @param string ...$viewComponentNames
     */
    public function hasViewComponents(string $prefix, ...$viewComponentNames): static
    {
        foreach ($viewComponentNames as $componentName) {
            $this->viewComponents[$componentName] = $prefix;
        }

        return $this;
    }

    /**
     * register a class namespace for <x-prefix::component> resolution.
     */
    public function hasBladeComponentNamespace(string $classNamespace, string $prefix): static
    {
        $this->bladeComponentNamespaces[$classNamespace] = $prefix;

        return $this;
    }

    /**
     * register one exact component alias (Blade::component semantics —
     * something hasViewComponent's prefix loading cannot express).
     */
    public function hasBladeComponentAlias(string $alias, string $componentClass): static
    {
        $this->bladeComponentAliases[$alias] = $componentClass;

        return $this;
    }

    /**
     * @param array<string, string> $aliases [alias => component class]
     */
    public function hasBladeComponentAliases(array $aliases): static
    {
        foreach ($aliases as $alias => $componentClass) {
            $this->hasBladeComponentAlias($alias, $componentClass);
        }

        return $this;
    }
}

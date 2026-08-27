<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Component\ComponentNamespaceResolver;

/**
 * Registers Blade components under configurable namespaces.
 */
trait HasComponentNamespaces
{
    protected ?ComponentNamespaceResolver $componentNamespaceResolver = null;

    /**
     * Register Blade component namespace
     *
     * @param string $namespace Namespace path (e.g., 'modules/admin')
     * @param string|null $prefix Component prefix (auto-generated if null)
     */
    public function hasComponentNamespace(string $namespace, ?string $prefix = null): static
    {
        $resolver = $this->getComponentNamespaceResolver();

        $fullNamespace = $resolver->resolve($namespace);

        if (! $prefix) {
            $prefix = $resolver->getPrefix($namespace);
        }

        $this->registerComponentNamespace($fullNamespace, $prefix);

        return $this;
    }

    /**
     * Register multiple component namespaces
     *
     * @param array<string, string|null> $namespaces [namespace => prefix]
     */
    public function hasComponentNamespaces(array $namespaces): static
    {
        foreach ($namespaces as $namespace => $prefix) {
            $this->hasComponentNamespace($namespace, $prefix);
        }

        return $this;
    }

    /**
     * Register component with dynamic namespace resolution
     *
     * @param string $module Module name
     */
    public function hasModuleComponents(string $module): static
    {
        $resolver = $this->getComponentNamespaceResolver();
        $namespace = $resolver->buildNamespace($module);
        $prefix = $resolver->normalize($module);

        $this->registerComponentNamespace($namespace, $prefix);

        return $this;
    }

    /**
     * Get or create component namespace resolver instance
     */
    protected function getComponentNamespaceResolver(): ComponentNamespaceResolver
    {
        if (! $this->componentNamespaceResolver) {
            $this->componentNamespaceResolver = app(ComponentNamespaceResolver::class);
        }

        return $this->componentNamespaceResolver;
    }

    /**
     * Register component namespace (implementation in Package class)
     *
     * @param string $namespace Full namespace
     * @param string $prefix Component prefix
     */
    abstract protected function registerComponentNamespace(string $namespace, string $prefix): void;
}

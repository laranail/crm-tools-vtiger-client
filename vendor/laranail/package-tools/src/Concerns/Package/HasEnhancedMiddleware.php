<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Convenience middleware vocabulary over the one deferred registry. Since
 * 2.0 everything here stores on the package and is applied by
 * bootPackageMiddleware() at boot — the old eager path (which wrote to the
 * router at configure time) is gone, so aliases, groups, and global
 * middleware all follow one lifecycle.
 */
trait HasEnhancedMiddleware
{
    /**
     * Register middleware alias (deferred; equivalent to
     * registerRouteMiddleware).
     *
     * @param string $alias Middleware alias
     * @param string $class Middleware class
     */
    public function registerMiddlewareAlias(string $alias, string $class): static
    {
        return $this->registerRouteMiddleware($alias, $class);
    }

    /**
     * Register multiple middleware aliases
     *
     * @param array<string, string> $aliases [alias => class]
     */
    public function registerMiddlewareAliases(array $aliases): static
    {
        return $this->registerRouteMiddlewares($aliases);
    }

    /**
     * Register middleware group
     *
     * @param string $group Group name
     * @param array<int, string> $middleware Middleware classes
     */
    public function registerMiddlewareGroup(string $group, array $middleware): static
    {
        $this->middlewareGroups[$group] = array_values($middleware);

        return $this;
    }

    /**
     * Register multiple middleware groups
     *
     * @param array<string, array<int, string>> $groups [group => middleware[]]
     */
    public function registerMiddlewareGroups(array $groups): static
    {
        foreach ($groups as $group => $middleware) {
            $this->registerMiddlewareGroup($group, $middleware);
        }

        return $this;
    }

    /**
     * Add middleware to a group registered on this package
     *
     * @param string $group Group name
     * @param string $middleware Middleware class
     */
    public function addToMiddlewareGroup(string $group, string $middleware): static
    {
        $this->middlewareGroups[$group][] = $middleware;

        return $this;
    }

    /**
     * Register package middleware with prefix
     *
     * @param array<string, string> $middleware [alias => class]
     * @param string|null $prefix Alias prefix (defaults to package name)
     */
    public function registerPrefixedMiddleware(array $middleware, ?string $prefix = null): static
    {
        $prefix ??= $this->shortName();

        foreach ($middleware as $alias => $class) {
            $this->registerRouteMiddleware("{$prefix}.{$alias}", $class);
        }

        return $this;
    }

    /**
     * Get package short name
     */
    abstract protected function shortName(): string;
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Event;

use Illuminate\Contracts\Http\Kernel;
use Simtabi\Laranail\Package\Tools\Contracts\RegistryInterface;

/**
 * Registers middleware and middleware groups.
 */
class MiddlewareRegistry implements RegistryInterface
{
    /** @var array<string, string> */
    protected array $middleware = [];

    /** @var array<string, array<int, string>> */
    protected array $middlewareGroups = [];

    /** @var array<string, string> */
    protected array $aliases = [];

    public function __construct(protected Kernel $kernel) {}

    /**
     * Register a middleware with an alias
     *
     * @param string $alias Middleware alias
     * @param string $class Middleware class
     */
    public function registerMiddleware(string $alias, string $class): void
    {
        $router = app('router');
        $router->aliasMiddleware($alias, $class);

        $this->middleware[$alias] = $class;
        $this->aliases[$alias] = $class;
    }

    /**
     * Register a middleware group
     *
     * @param string $name Group name
     * @param array<int, string> $middleware Array of middleware
     */
    public function registerGroup(string $name, array $middleware): void
    {
        $router = app('router');
        $router->middlewareGroup($name, $middleware);

        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Create an alias for existing middleware
     *
     * @param string $alias New alias
     * @param string $existing Existing middleware/alias
     */
    public function alias(string $alias, string $existing): void
    {
        $class = $this->middleware[$existing] ?? $existing;
        $this->registerMiddleware($alias, $class);
    }

    /**
     * {@inheritDoc}
     */
    public function register(string $key, mixed $value): void
    {
        if (is_array($value)) {
            $this->registerGroup($key, $value);
        } else {
            $this->registerMiddleware($key, $value);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRegistered(): array
    {
        return [
            'middleware' => $this->middleware,
            'groups' => $this->middlewareGroups,
            'aliases' => $this->aliases,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset($this->middleware[$key]) || isset($this->middlewareGroups[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->middleware[$key] ?? $this->middlewareGroups[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(string $key): void
    {
        unset($this->middleware[$key], $this->middlewareGroups[$key], $this->aliases[$key]);
    }
}

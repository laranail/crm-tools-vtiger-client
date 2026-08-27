<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Illuminate\Routing\Router;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Declarative route-model bindings and explicit parameter binders, applied
 * at boot the same way {@see HasMiddlewareManagement::bootPackageMiddleware()}
 * wires middleware. A model may be a class-string or a Closure resolving to
 * one at boot (so a model read from config obeys the merge-ordering rule).
 */
trait HasRouteBindings
{
    /** @var array<string, string|Closure> */
    protected array $routeModels = [];

    /** @var array<string, Closure> */
    protected array $routeBinders = [];

    public function registerRouteModel(string $parameter, string|Closure $model): static
    {
        $this->routeModels[$parameter] = $model;

        return $this;
    }

    /**
     * @param array<string, string|Closure> $models [parameter => model]
     */
    public function registerRouteModels(array $models): static
    {
        foreach ($models as $parameter => $model) {
            $this->registerRouteModel($parameter, $model);
        }

        return $this;
    }

    /**
     * Bind a route parameter to a model class read from a config key at boot
     * (after the package's config has merged).
     */
    public function registerRouteModelFromConfig(string $parameter, string $key): static
    {
        return $this->registerRouteModel($parameter, static fn (): string => (string) config($key, ''));
    }

    public function registerRouteBinding(string $parameter, Closure $resolver): static
    {
        $this->routeBinders[$parameter] = $resolver;

        return $this;
    }

    /**
     * @param array<string, Closure> $bindings [parameter => resolver]
     */
    public function registerRouteBindings(array $bindings): static
    {
        foreach ($bindings as $parameter => $resolver) {
            $this->registerRouteBinding($parameter, $resolver);
        }

        return $this;
    }

    /**
     * Wire the bindings into Laravel. Call from the provider's boot().
     */
    public function bootPackageRouteBindings(Router $router): void
    {
        foreach ($this->routeModels as $parameter => $model) {
            // Critical: a model may be a closure resolved at boot; a dropped
            // binding surfaces later as a 404 or a wrong-record bug far from
            // the cause, so it must fail fast rather than degrade.
            FailurePolicy::run(function () use ($router, $parameter, $model): void {
                $class = $model instanceof Closure ? (string) $model() : $model;

                if ($class !== '' && class_exists($class)) {
                    $router->model($parameter, $class);
                }
            }, "route model binding [{$parameter}]", BootCriticality::Critical);
        }

        // Binder closures run at request time, so registration can't throw here.
        foreach ($this->routeBinders as $parameter => $resolver) {
            $router->bind($parameter, $resolver);
        }
    }

    /**
     * @return array<string, string|Closure>
     */
    public function getRouteModels(): array
    {
        return $this->routeModels;
    }

    /**
     * @return array<string, Closure>
     */
    public function getRouteBinders(): array
    {
        return $this->routeBinders;
    }
}

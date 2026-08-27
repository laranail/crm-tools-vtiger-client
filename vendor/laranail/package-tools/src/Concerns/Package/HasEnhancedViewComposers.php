<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Illuminate\Support\Facades\View;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Registers view composers and creators with automatic namespace prefixing,
 * wildcard patterns, multiple composers per view, and dependency injection.
 */
trait HasEnhancedViewComposers
{
    /** @var array<string, array<int, string|callable>> View composer registry */
    protected array $viewComposerRegistry = [];

    /** @var array<string, array<int, string|callable>> View creator registry */
    protected array $viewCreatorRegistry = [];

    /** @var bool Auto-prefix views with package namespace */
    protected bool $autoPrefixViewComposers = true;

    /**
     * Register a view composer
     *
     * @param string|array<string> $views View name(s)
     * @param string|callable $composer Composer class or callback
     * @param bool $autoPrefix Automatically prefix with view namespace
     *
     * @example
     * ```php
     * $package->registerViewComposer('dashboard', DashboardComposer::class);
     * // Registers: 'vendor::dashboard'
     * ```
     */
    public function registerViewComposer(
        string|array $views,
        string|callable $composer,
        bool $autoPrefix = true
    ): static {
        $views = (array) $views;

        foreach ($views as $view) {
            $viewName = $autoPrefix && $this->autoPrefixViewComposers
                ? $this->prefixViewName($view)
                : $view;

            if (! isset($this->viewComposerRegistry[$viewName])) {
                $this->viewComposerRegistry[$viewName] = [];
            }

            $this->viewComposerRegistry[$viewName][] = $composer;
        }

        return $this;
    }

    /**
     * Register multiple view composers at once.
     *
     * @param array<string, string|callable> $composers Map of [views => composer]
     * @param bool $autoPrefix Automatically prefix with view namespace
     */
    public function registerViewComposers(array $composers, bool $autoPrefix = true): static
    {
        foreach ($composers as $views => $composer) {
            $this->registerViewComposer($views, $composer, $autoPrefix);
        }

        return $this;
    }

    /**
     * Register a composer for all of the package's views.
     *
     * @param string|callable $composer Composer class or callback
     */
    public function registerGlobalViewComposer(string|callable $composer): static
    {
        return $this->registerViewComposer('*', $composer, autoPrefix: false);
    }

    /**
     * Register a view creator.
     *
     * @param string|array<string> $views View name(s)
     * @param string|callable $creator Creator class or callback
     * @param bool $autoPrefix Automatically prefix with view namespace
     */
    public function registerViewCreator(
        string|array $views,
        string|callable $creator,
        bool $autoPrefix = true
    ): static {
        $views = (array) $views;

        foreach ($views as $view) {
            $viewName = $autoPrefix && $this->autoPrefixViewComposers
                ? $this->prefixViewName($view)
                : $view;

            if (! isset($this->viewCreatorRegistry[$viewName])) {
                $this->viewCreatorRegistry[$viewName] = [];
            }

            $this->viewCreatorRegistry[$viewName][] = $creator;
        }

        return $this;
    }

    /**
     * Register a view composer with constructor dependencies resolved from
     * the container.
     *
     * @param string|array<string> $views View name(s)
     * @param string $composer Composer class
     * @param array<string, mixed> $dependencies Dependencies to inject
     * @param bool $autoPrefix Automatically prefix with view namespace
     */
    public function registerViewComposerWithDependencies(
        string|array $views,
        string $composer,
        array $dependencies = [],
        bool $autoPrefix = true
    ): static {
        $resolver = fn ($view) => app()->make($composer, $dependencies)->compose($view);

        return $this->registerViewComposer($views, $resolver, $autoPrefix);
    }

    /**
     * Register the queued composers and creators with Laravel. Call from the
     * service provider's boot() method.
     */
    public function bootPackageViewComposers(): void
    {
        foreach ($this->viewComposerRegistry as $viewName => $composers) {
            foreach ($composers as $composer) {
                FailurePolicy::run(
                    static fn () => View::composer($viewName, is_string($composer) ? $composer : Closure::fromCallable($composer)),
                    "view composer [{$viewName}]",
                    BootCriticality::Critical,
                );
            }
        }

        foreach ($this->viewCreatorRegistry as $viewName => $creators) {
            foreach ($creators as $creator) {
                FailurePolicy::run(
                    static fn () => View::creator($viewName, is_string($creator) ? $creator : Closure::fromCallable($creator)),
                    "view creator [{$viewName}]",
                    BootCriticality::Critical,
                );
            }
        }
    }

    /**
     * Prefix view name with package namespace
     *
     * @param string $view View name
     * @return string Prefixed view name
     */
    protected function prefixViewName(string $view): string
    {
        $namespace = $this->getViewNamespace();

        if (str_starts_with($view, $namespace . '::')) {
            return $view;
        }

        return $namespace . '::' . $view;
    }

    /**
     * Get all registered view composers
     *
     * @return array<string, array<int, string|callable>>
     */
    public function getViewComposerRegistry(): array
    {
        return $this->viewComposerRegistry;
    }

    /**
     * Get all registered view creators
     *
     * @return array<string, array<int, string|callable>>
     */
    public function getViewCreatorRegistry(): array
    {
        return $this->viewCreatorRegistry;
    }

    /**
     * Disable auto-prefixing for view composers
     */
    public function disableViewComposerAutoPrefix(): static
    {
        $this->autoPrefixViewComposers = false;

        return $this;
    }

    /**
     * Get the package view namespace.
     */
    abstract protected function getViewNamespace(): string;
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Discovery\AttributeDiscoverer;

/**
 * Loads multiple package resources in one call.
 */
trait HasBatchResourceLoading
{
    /**
     * Load all standard package resources
     *
     * @param array<string> $resources Resources to load (empty = all)
     */
    public function loadAllResources(array $resources = []): static
    {
        $defaultResources = [
            'configs' => fn () => $this->autoLoadConfigs(),
            'views' => fn () => $this->autoLoadViews(),
            'translations' => fn () => $this->autoLoadTranslations(),
            'migrations' => fn () => $this->autoLoadMigrations(),
            'routes' => fn () => $this->autoLoadRoutes(),
            'commands' => fn () => $this->autoLoadCommands(),
        ];

        $toLoad = $resources === [] ? $defaultResources : array_intersect_key($defaultResources, array_flip($resources));

        foreach ($toLoad as $loader) {
            $loader();
        }

        return $this;
    }

    /**
     * Auto-load all config files from config directory
     */
    protected function autoLoadConfigs(): static
    {
        $configPath = $this->packageBasePath('config');

        if (File::isDirectory($configPath)) {
            $files = glob($configPath . '/*.php') ?: [];

            foreach ($files as $file) {
                $fileName = basename($file, '.php');
                $this->hasConfigFile($fileName);
            }
        }

        return $this;
    }

    /**
     * Auto-load views if directory exists
     */
    protected function autoLoadViews(): static
    {
        $viewsPath = $this->packageBasePath('resources/views');

        if (File::isDirectory($viewsPath)) {
            $this->hasViews();
        }

        return $this;
    }

    /**
     * Auto-load translations if directory exists
     */
    protected function autoLoadTranslations(): static
    {
        $langPath = $this->packageBasePath('resources/lang');

        if (File::isDirectory($langPath)) {
            $this->hasTranslations();
        }

        return $this;
    }

    /**
     * Auto-load migrations if directory exists
     */
    protected function autoLoadMigrations(): static
    {
        $migrationsPath = $this->packageBasePath('database/migrations');

        if (File::isDirectory($migrationsPath)) {
            $this->hasMigrations();
        }

        return $this;
    }

    /**
     * Auto-load routes if files exist
     */
    protected function autoLoadRoutes(): static
    {
        $routesPath = $this->packageBasePath('routes');

        if (File::isDirectory($routesPath)) {
            if (File::exists($routesPath . '/web.php')) {
                $this->hasRoute('web');
            }
            if (File::exists($routesPath . '/api.php')) {
                $this->hasRoute('api');
            }
        }

        return $this;
    }

    /**
     * Auto-load commands by scanning the package's command directory for
     * Illuminate\Console\Command subclasses and registering each via
     * hasCommands().
     *
     * No-ops when the directory is missing or the package's root namespace
     * can't be resolved (host doesn't expose getNamespace()).
     *
     * @param string|null $dir Command directory; defaults to src/Commands.
     */
    protected function autoLoadCommands(?string $dir = null): static
    {
        $dir ??= $this->packageBasePath('src/Commands');

        if (! File::isDirectory($dir)) {
            return $this;
        }

        $namespace = method_exists($this, 'getNamespace')
            ? $this->getNamespace() . '\\Commands'
            : '';

        if ($namespace === '') {
            return $this;
        }

        $commands = iterator_to_array(
            (new AttributeDiscoverer)->discoverSubclasses($dir, $namespace, Command::class)
        );

        if ($commands !== []) {
            $this->hasCommands(...$commands);
        }

        return $this;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;

    abstract public function hasConfigFile($configFileName = null): static;

    abstract public function hasViews(?string $namespace = null): static;

    abstract public function hasTranslations(): static;

    abstract public function hasRoute(string $routeFileName): static;

    /**
     * @param string|array<int, string> ...$commandClassNames
     */
    abstract public function hasCommands(...$commandClassNames): static;
}

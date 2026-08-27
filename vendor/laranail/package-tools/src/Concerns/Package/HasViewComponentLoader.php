<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\View\ViewComponentLoader;

/**
 * Loads view components from paths or explicit class maps.
 */
trait HasViewComponentLoader
{
    protected ?ViewComponentLoader $viewComponentLoaderService = null;

    /**
     * @param string $path Path to components directory
     * @param string $namespace Component namespace
     */
    public function loadViewComponents(string $path, string $namespace): static
    {
        $loader = $this->getViewComponentLoaderService();

        $fullPath = $this->packageBasePath($path);
        $loader->loadFromPath($fullPath, $namespace);

        return $this;
    }

    /**
     * @param string $namespace Component namespace
     * @param array<string, string> $components [name => class]
     */
    public function loadComponentNamespace(string $namespace, array $components): static
    {
        $loader = $this->getViewComponentLoaderService();
        $loader->loadNamespace($namespace, $components);

        return $this;
    }

    /**
     * @param string $path Path to components directory
     * @param string $prefix Component prefix
     */
    public function loadAnonymousViewComponents(string $path, string $prefix): static
    {
        $loader = $this->getViewComponentLoaderService();

        $fullPath = $this->packageBasePath($path);
        $loader->loadAnonymous($fullPath, $prefix);

        return $this;
    }

    /**
     * Load anonymous components from resources/views/components if present.
     */
    public function autoLoadViewComponents(): static
    {
        $componentsPath = $this->packageBasePath('resources/views/components');

        if (File::isDirectory($componentsPath)) {
            $this->loadAnonymousViewComponents('resources/views/components', $this->shortName());
        }

        return $this;
    }

    protected function getViewComponentLoaderService(): ViewComponentLoader
    {
        if (! $this->viewComponentLoaderService) {
            $this->viewComponentLoaderService = app(ViewComponentLoader::class);
        }

        return $this->viewComponentLoaderService;
    }

    /**
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;

    abstract protected function shortName(): string;
}

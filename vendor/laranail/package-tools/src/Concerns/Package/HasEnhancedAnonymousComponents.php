<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Component\AnonymousComponentLoader;

/**
 * Loads anonymous Blade components from package paths.
 */
trait HasEnhancedAnonymousComponents
{
    protected ?AnonymousComponentLoader $anonymousComponentLoader = null;

    /**
     * Load anonymous components from custom path
     *
     * @param string $path Path to components directory
     * @param string|null $prefix Component prefix
     */
    public function hasAnonymousComponents(string $path, ?string $prefix = null): static
    {
        $loader = $this->getAnonymousComponentLoader();

        $fullPath = $this->packageBasePath($path);
        $componentPrefix = $prefix ?? $this->shortName();

        $loader->load($fullPath, $componentPrefix);

        return $this;
    }

    /**
     * Load anonymous components from multiple paths
     *
     * @param array<string, string|null> $paths [path => prefix]
     */
    public function hasMultipleAnonymousComponents(array $paths): static
    {
        foreach ($paths as $path => $prefix) {
            $this->hasAnonymousComponents($path, $prefix);
        }

        return $this;
    }

    /**
     * Load anonymous components from subdirectories
     *
     * @param string $baseDir Base directory (e.g., 'resources/views/components')
     * @param array<string> $subdirs Subdirectories to load
     */
    public function hasNestedAnonymousComponents(string $baseDir, array $subdirs): static
    {
        foreach ($subdirs as $subdir) {
            $path = trim($baseDir, '/') . '/' . trim($subdir, '/');
            $prefix = $this->shortName() . '-' . str_replace('/', '-', $subdir);

            $this->hasAnonymousComponents($path, $prefix);
        }

        return $this;
    }

    /**
     * Auto-discover and load all anonymous component directories
     *
     * @param string $baseDir Base directory to scan
     */
    public function discoverAnonymousComponents(string $baseDir = 'resources/views/components'): static
    {
        $fullPath = $this->packageBasePath($baseDir);

        if (File::isDirectory($fullPath)) {
            $loader = $this->getAnonymousComponentLoader();
            $loader->load($fullPath, $this->shortName());
        }

        return $this;
    }

    /**
     * Get the loader, creating it on first use.
     */
    protected function getAnonymousComponentLoader(): AnonymousComponentLoader
    {
        if (! $this->anonymousComponentLoader) {
            $this->anonymousComponentLoader = app(AnonymousComponentLoader::class);
        }

        return $this->anonymousComponentLoader;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;

    /**
     * Get package short name
     */
    abstract protected function shortName(): string;
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Component;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;
use Simtabi\Laranail\Package\Tools\Contracts\LoaderInterface;

/**
 * Loads and registers anonymous (file-based) Blade components.
 */
class AnonymousComponentLoader implements LoaderInterface
{
    /** @var array<string, string> */
    protected array $loaded = [];

    public function __construct(protected Application $app) {}

    /**
     * Load anonymous components from a directory
     *
     * @param string $path Path to components directory
     * @param string $prefix Component prefix
     */
    public function load(string $path, string $prefix = ''): void
    {
        if (! $this->canLoad($path)) {
            return;
        }

        // Normalize prefix
        $prefix = str_replace('/', '-', trim($prefix, '/'));

        // Register anonymous component path
        $this->app->make(BladeCompiler::class)
            ->anonymousComponentPath($path, $prefix);

        $this->loaded[$prefix] = $path;
    }

    /**
     * Discover all component files in a directory
     *
     * @param string $directory Directory to scan
     * @return array<string> Array of component file paths
     */
    public function discover(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        $components = [];
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $components[] = $file->getPathname();
            }
        }

        return $components;
    }

    /**
     * Register multiple component paths
     *
     * @param array<string, string> $components Array of prefix => path
     * @param string $basePrefix Base prefix to prepend
     */
    public function registerMultiple(array $components, string $basePrefix = ''): void
    {
        foreach ($components as $prefix => $path) {
            $fullPrefix = $basePrefix !== '' && $basePrefix !== '0' ? "{$basePrefix}-{$prefix}" : $prefix;
            $this->load($path, $fullPrefix);
        }
    }

    /**
     * Validate component path
     *
     * @param string $path Path to validate
     */
    public function validate(string $path): bool
    {
        return $this->canLoad($path);
    }

    /**
     * {@inheritDoc}
     */
    public function canLoad(string $path): bool
    {
        return File::isDirectory($path) && $this->discover($path) !== [];
    }

    /**
     * {@inheritDoc}
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }
}

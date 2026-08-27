<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Package;

use Illuminate\Support\Facades\File;

/**
 * Resolves package dependencies and version constraints.
 */
class DependencyResolver
{
    /**
     * Resolve dependencies from composer.json
     *
     * @param string $packagePath Package path
     * @return array<string, mixed>
     */
    public function resolve(string $packagePath): array
    {
        $composerPath = $packagePath . '/composer.json';

        if (! File::exists($composerPath)) {
            return [];
        }

        $composer = json_decode(File::get($composerPath), true);

        return [
            'runtime' => $this->resolveRuntimeDependencies($composer['require'] ?? []),
            'development' => $this->resolveRuntimeDependencies($composer['require-dev'] ?? []),
        ];
    }

    /**
     * Resolve runtime dependencies
     *
     * @param array<string, string> $dependencies Dependencies array
     * @return array<string, mixed>
     */
    protected function resolveRuntimeDependencies(array $dependencies): array
    {
        $resolved = [];

        foreach ($dependencies as $package => $version) {
            $resolved[$package] = [
                'version' => $version,
                'type' => $this->getDependencyType($package),
                'resolved_version' => $this->resolveVersion($version),
            ];
        }

        return $resolved;
    }

    /**
     * Get dependency type
     *
     * @param string $package Package name
     */
    protected function getDependencyType(string $package): string
    {
        if ($package === 'php') {
            return 'platform';
        }

        if (str_starts_with($package, 'ext-')) {
            return 'extension';
        }

        return 'library';
    }

    /**
     * Resolve version constraint to specific version
     *
     * @param string $constraint Version constraint
     */
    protected function resolveVersion(string $constraint): string
    {
        // Simplified: a full version would query packagist or composer.lock.
        return $constraint;
    }

    /**
     * Check if all dependencies are satisfied
     *
     * @param string $packagePath Package path
     */
    public function areDependenciesSatisfied(string $packagePath): bool
    {
        $lockPath = $packagePath . '/composer.lock';
        $vendorPath = $packagePath . '/vendor';

        return File::exists($lockPath) && File::isDirectory($vendorPath);
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Component\ComponentRegistry;

/**
 * Registers Vue.js components for publishing.
 */
trait HasVueComponents
{
    protected ?ComponentRegistry $vueComponentRegistry = null;

    /** @var array<string, string> Map of component name => full path */
    protected array $vueComponents = [];

    /**
     * @param string $name Component name
     * @param string $path Component file path
     */
    public function hasVueComponent(string $name, string $path): static
    {
        $registry = $this->getVueComponentRegistry();

        $fullPath = $this->packageBasePath($path);

        $registry->registerVue($name, $fullPath);
        $this->vueComponents[$name] = $fullPath;

        return $this;
    }

    /**
     * @param array<string, string> $components [name => path]
     */
    public function hasVueComponents(array $components): static
    {
        foreach ($components as $name => $path) {
            $this->hasVueComponent($name, $path);
        }

        return $this;
    }

    /**
     * Register every .vue file in a directory.
     *
     * @param string $directory Directory path (relative to package root)
     * @param string|null $namespace Component namespace prefix
     */
    public function hasVueComponentsDirectory(string $directory, ?string $namespace = null): static
    {
        $fullPath = $this->packageBasePath($directory);

        if (File::isDirectory($fullPath)) {
            $files = glob($fullPath . '/*.vue') ?: [];

            foreach ($files as $file) {
                $name = basename($file, '.vue');
                $componentName = $namespace ? "{$namespace}/{$name}" : $name;

                $this->hasVueComponent($componentName, str_replace($this->packageBasePath(), '', $file));
            }
        }

        return $this;
    }

    /**
     * @param string $targetDir Target directory (e.g., 'js/components')
     */
    public function publishVueComponents(string $targetDir = 'js/components'): static
    {
        if (empty($this->vueComponents)) {
            return $this;
        }

        foreach ($this->vueComponents as $name => $sourcePath) {
            $fileName = str_replace('/', '-', $name) . '.vue';
            $targetPath = resource_path($targetDir . '/' . $fileName);
            $this->publishAssets($sourcePath, $targetPath, false, 'vue-components');
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getVueComponents(): array
    {
        return $this->vueComponents;
    }

    protected function getVueComponentRegistry(): ComponentRegistry
    {
        if (! $this->vueComponentRegistry) {
            $this->vueComponentRegistry = app(ComponentRegistry::class);
        }

        return $this->vueComponentRegistry;
    }

    /**
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;
}

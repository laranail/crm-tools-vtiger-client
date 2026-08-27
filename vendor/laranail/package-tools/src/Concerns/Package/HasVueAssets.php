<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;

/**
 * Publishes Vue.js assets: components, stores, composables, and utils.
 */
trait HasVueAssets
{
    /**
     * @param string $sourceDir Source directory (relative to package root)
     * @param string $targetDir Target directory (relative to resources/js)
     */
    public function publishVueComponentsDirectory(string $sourceDir = 'resources/js/components', string $targetDir = 'components'): static
    {
        $sourcePath = $this->packageBasePath($sourceDir);
        $targetPath = resource_path('js/' . trim($targetDir, '/'));

        if (File::isDirectory($sourcePath)) {
            $this->publishAssets($sourcePath, $targetPath, false, $this->shortName() . '-vue-components');
        }

        return $this;
    }

    /**
     * @param string $sourceDir Source directory
     * @param string $targetDir Target directory
     */
    public function publishVuexStores(string $sourceDir = 'resources/js/stores', string $targetDir = 'stores'): static
    {
        $sourcePath = $this->packageBasePath($sourceDir);
        $targetPath = resource_path('js/' . trim($targetDir, '/'));

        if (File::isDirectory($sourcePath)) {
            $this->publishAssets($sourcePath, $targetPath, false, $this->shortName() . '-vuex-stores');
        }

        return $this;
    }

    /**
     * @param string $sourceDir Source directory
     * @param string $targetDir Target directory
     */
    public function publishPiniaStores(string $sourceDir = 'resources/js/stores', string $targetDir = 'stores'): static
    {
        return $this->publishVuexStores($sourceDir, $targetDir);
    }

    /**
     * @param string $sourceDir Source directory
     * @param string $targetDir Target directory
     */
    public function publishVueComposables(string $sourceDir = 'resources/js/composables', string $targetDir = 'composables'): static
    {
        $sourcePath = $this->packageBasePath($sourceDir);
        $targetPath = resource_path('js/' . trim($targetDir, '/'));

        if (File::isDirectory($sourcePath)) {
            $this->publishAssets($sourcePath, $targetPath, false, $this->shortName() . '-vue-composables');
        }

        return $this;
    }

    /**
     * @param string $sourceDir Source directory
     * @param string $targetDir Target directory
     */
    public function publishVueUtils(string $sourceDir = 'resources/js/utils', string $targetDir = 'utils'): static
    {
        $sourcePath = $this->packageBasePath($sourceDir);
        $targetPath = resource_path('js/' . trim($targetDir, '/'));

        if (File::isDirectory($sourcePath)) {
            $this->publishAssets($sourcePath, $targetPath, false, $this->shortName() . '-vue-utils');
        }

        return $this;
    }

    public function publishAllVueAssets(): static
    {
        $this->publishVueComponentsDirectory()
            ->publishVuexStores()
            ->publishVueComposables()
            ->publishVueUtils();

        return $this;
    }

    /**
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;

    abstract protected function shortName(): string;
}

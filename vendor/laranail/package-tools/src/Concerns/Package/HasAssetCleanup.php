<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Asset\AssetRegistry;

/**
 * Cleans up old assets before publishing new ones.
 */
trait HasAssetCleanup
{
    protected ?AssetRegistry $assetCleanupRegistry = null;

    protected bool $enableAssetCleanup = false;

    /**
     * @param bool $enabled Enable cleanup before publishing
     */
    public function withAssetCleanup(bool $enabled = true): static
    {
        $this->enableAssetCleanup = $enabled;

        return $this;
    }

    /**
     * @param string $targetPath Target path to clean
     * @param string $tag Publish tag
     */
    public function registerAssetForCleanup(string $targetPath, string $tag): static
    {
        $registry = $this->getAssetCleanupRegistry();
        $registry->register($tag, $targetPath, true);

        return $this;
    }

    /**
     * @param string $tag Publish tag
     */
    public function cleanupAssets(string $tag): bool
    {
        $registry = $this->getAssetCleanupRegistry();

        if ($registry->has($tag)) {
            $registry->cleanup($tag);

            return true;
        }

        return false;
    }

    /**
     * Clean up all registered assets.
     *
     * @return array<string, bool> [tag => success]
     */
    public function cleanupAllAssets(): array
    {
        $registry = $this->getAssetCleanupRegistry();
        $results = [];

        foreach (array_keys($registry->getRegistered()) as $tag) {
            $results[$tag] = $this->cleanupAssets($tag);
        }

        return $results;
    }

    /**
     * @param string $sourcePath Source path
     * @param string $targetPath Target path
     * @param string $tag Publish tag
     */
    public function publishAssetsWithCleanup(string $sourcePath, string $targetPath, string $tag): static
    {
        if ($this->enableAssetCleanup) {
            $this->registerAssetForCleanup($targetPath, $tag);
            $this->cleanupAssets($tag);
        }

        // Delegate to HasAssetPublisher::publishAssets (the multi-arg form).
        $this->publishAssets($sourcePath, $targetPath, false, $tag);

        return $this;
    }

    /**
     * Check if asset cleanup is enabled
     */
    public function isAssetCleanupEnabled(): bool
    {
        return $this->enableAssetCleanup;
    }

    /**
     * Get or create the asset cleanup registry.
     */
    protected function getAssetCleanupRegistry(): AssetRegistry
    {
        if (! $this->assetCleanupRegistry) {
            $this->assetCleanupRegistry = app(AssetRegistry::class);
        }

        return $this->assetCleanupRegistry;
    }
}

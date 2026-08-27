<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;

trait ProcessAssets
{
    protected function bootPackageAssets(): static
    {
        if (! $this->package->hasAssets || ! $this->app->runningInConsole()) {
            return $this;
        }

        $vendorAssets = $this->package->basePath('/resources/dist');
        $appAssets = public_path("vendor/{$this->package->shortName()}");

        $publishTag = $this->package->getNamespacedPublishTag('assets');

        $this->publishes([$vendorAssets => $appAssets], $publishTag);

        $this->bootPackageAssetRegistry();
        $this->bootPackageDeclaredAssetGroups();

        return $this;
    }

    /**
     * Publish every entry registered in the package asset registry.
     */
    protected function bootPackageAssetRegistry(): void
    {
        foreach ($this->package->getAssetRegistry() as $entry) {
            $source = $this->package->basePath('/' . $entry['source']);
            $destination = public_path($entry['destination']);

            $tag = $entry['tag'] ?? "{$this->package->shortName()}-assets";

            $this->publishes([$source => $destination], $tag);
        }
    }

    /**
     * Record a publish tag so a later publish can honour a clean request.
     *
     * Boot never deletes. It used to: a `clean: true` entry had its destination
     * removed here, on every console command, so an unrelated `php artisan
     * route:list` wiped published assets. The intent is recorded instead and
     * acted on when publishing actually runs.
     *
     * @param array<string, string> $paths source => destination
     */
    protected function recordPublishTag(string $tag, array $paths, bool $cleanable): void
    {
        if (! $this->app->bound(PublishTagRegistry::class)) {
            return;
        }

        $this->app->make(PublishTagRegistry::class)
            ->record($tag, $this->package->shortName(), $paths, $cleanable);
    }

    /**
     * Publish every declared asset group whose source directory exists.
     */
    protected function bootPackageDeclaredAssetGroups(): void
    {
        foreach ($this->package->getDeclaredAssetGroups() as $name => $group) {
            $source = $this->package->basePath('/' . $group['source']);

            if (! File::isDirectory($source)) {
                continue;
            }

            $target = public_path($group['target']);

            $this->publishes([$source => $target], "{$this->package->shortName()}-{$name}");
        }
    }
}

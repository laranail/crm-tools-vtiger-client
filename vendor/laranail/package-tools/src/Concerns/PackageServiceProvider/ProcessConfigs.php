<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Package;

trait ProcessConfigs
{
    public function registerPackageConfigs(): self
    {
        // Flat config files: config/{name}.php → config('{name}.*') (or the
        // vendor.package prefix when namespacing is on). Unchanged.
        foreach ($this->package->configFileNames as $configFileName) {
            $vendorConfig = $this->package->basePath(Package::CONFIG_DIR . "/{$configFileName}.php");

            if (! File::isFile($vendorConfig)) {
                continue;
            }

            $configKey = $this->package->hasConfigNamespacing()
                ? $this->package->getNamespacedConfigKey($configFileName)
                : $configFileName;

            $this->mergeConfigFrom($vendorConfig, $configKey);

            // Namespaced configs publish to a NESTED path (config/vendor/package.php)
            // that Laravel does not auto-load, so a published override would never
            // reach the dotted key. Load it here and merge it OVER the vendor
            // defaults so `vendor:publish` + edit actually overrides.
            $this->mergePublishedConfigOverride($configKey);
        }

        // Folder-namespaced config files: mounted at their dotted key so
        // config('folder.file.key') resolves natively.
        foreach ($this->package->namespacedConfigFiles as $entry) {
            $this->mergeNestedConfig($entry['path'], $entry['key']);
        }

        return $this;
    }

    /**
     * Load a PUBLISHED override for a namespaced config and merge it over the
     * vendor defaults already merged under $key. Only namespaced (nested-path)
     * configs need this — flat configs are auto-loaded by Laravel at their root
     * key. Uses a recursive merge so a partially-edited published file still
     * inherits untouched defaults. No-op when config is cached.
     */
    protected function mergePublishedConfigOverride(string $key): void
    {
        if (! $this->package->hasConfigNamespacing()) {
            return;
        }

        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $published = config_path(str_replace('.', '/', $key) . '.php');

        if (! File::isFile($published)) {
            return;
        }

        $override = require $published;

        if (! is_array($override)) {
            return;
        }

        $config = $this->app->make(Repository::class);
        $config->set($key, array_replace_recursive((array) $config->get($key, []), $override));
    }

    /**
     * Merge a nested config file under a dotted key, honouring an optional
     * in-file `__namespace` override. Mirrors mergeConfigFrom()'s cache
     * guard, and keeps package values as defaults (the app's config wins).
     */
    protected function mergeNestedConfig(string $path, string $key): void
    {
        if (! File::isFile($path)) {
            return;
        }

        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $loaded = require $path;

        if (! is_array($loaded)) {
            return;
        }

        if (isset($loaded[Package::CONFIG_NAMESPACE_KEY])) {
            $declared = $loaded[Package::CONFIG_NAMESPACE_KEY];
            unset($loaded[Package::CONFIG_NAMESPACE_KEY]);

            if (is_string($declared) && $this->isValidConfigNamespace($declared)) {
                $key = $declared;
            }
        }

        $config = $this->app->make(Repository::class);
        $config->set($key, array_merge($loaded, $config->get($key, [])));
    }

    /**
     * A safe dotted namespace: alnum/dash/underscore segments, no traversal.
     */
    protected function isValidConfigNamespace(string $namespace): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/', $namespace);
    }

    protected function bootPackageConfigs(): self
    {
        if (! $this->app->runningInConsole()) {
            return $this;
        }

        // Publish nested config files preserving their folder layout, e.g.
        // config/admin/panel.php → config_path('admin/panel.php').
        foreach ($this->package->namespacedConfigFiles as $entry) {
            if (! File::isFile($entry['path'])) {
                continue;
            }

            $this->publishes(
                [$entry['path'] => config_path($entry['relative'])],
                $this->package->getNamespacedPublishTag('config'),
            );
        }

        foreach ($this->package->configFileNames as $configFileName) {
            $vendorConfig = null;

            // Prefer .php, fall back to .php.stub.
            if (File::isFile($phpConfig = $this->package->basePath(Package::CONFIG_DIR . "/{$configFileName}.php"))) {
                $vendorConfig = $phpConfig;
            } elseif (File::isFile($stubConfig = $this->package->basePath(Package::CONFIG_DIR . "/{$configFileName}.php.stub"))) {
                $vendorConfig = $stubConfig;
            }

            if ($vendorConfig === null) {
                continue;
            }

            $publishTag = $this->package->getNamespacedPublishTag('config');

            // Publish to the path Laravel loads back under the same key the
            // config was merged into. With namespacing on, the merge key is
            // dotted (vendor.package), so the override must live at the matching
            // nested path (config/vendor/package.php). A flat config/package.php
            // would load as config('package') and be invisible to the merged
            // config('vendor.package').
            $publishRelativePath = $this->package->hasConfigNamespacing()
                ? str_replace('.', '/', $this->package->getNamespacedConfigKey($configFileName))
                : $configFileName;

            $this->publishes(
                [$vendorConfig => config_path("{$publishRelativePath}.php")],
                $publishTag
            );
        }

        return $this;
    }
}

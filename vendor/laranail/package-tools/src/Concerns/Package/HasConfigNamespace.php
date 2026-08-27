<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Auto-namespaces config files by vendor/package so keys like
 * `config('vendor.package')` don't collide with other packages.
 *
 * The `vendor/package` name format is required; a bare package name is
 * not supported.
 *
 * ```php
 * $package->setName('vendor/package-name');
 * // Config:  config('vendor.package-name')
 * // Publish: vendor::package-name-config
 * ```
 */
trait HasConfigNamespace
{
    public ?string $configVendor = null;

    /**
     * When false, flat config files merge under their bare file name
     * (`config('file.*')`) instead of the `vendor.package` namespace. Lets a
     * package keep reading bare config keys after adopting this base.
     */
    public bool $configNamespacing = true;

    /** @var array<string, string> Cache for computed namespace strings */
    private array $namespaceCache = [];

    /**
     * Opt out of vendor/package config namespacing for this package.
     */
    public function withoutConfigNamespacing(): static
    {
        $this->configNamespacing = false;

        return $this;
    }

    /**
     * Get or compute a cached namespace.
     *
     * The generator runs only on a cache miss; because `??=` assigns its
     * result, a generator that throws (e.g. on a null vendor) never caches.
     *
     * @param string $key Cache key
     * @param callable(): string $generator Function to generate value if not cached
     */
    private function getCachedNamespace(string $key, callable $generator): string
    {
        return $this->namespaceCache[$key] ??= $generator();
    }

    /**
     * Get the full namespaced config key. The default config file (whose name
     * equals the package short-name) maps to the bare dotted namespace
     * (`vendor.package`); any additional file gets a per-file sub-key
     * (`vendor.package.{file}`) so multiple config files do not collide.
     */
    public function getNamespacedConfigKey(string $configFileName): string
    {
        $base = $this->getDottedNamespace();

        return $configFileName === $this->shortName()
            ? $base
            : $base . '.' . $configFileName;
    }

    /**
     * Check if config namespacing is enabled
     */
    public function hasConfigNamespacing(): bool
    {
        return $this->configVendor !== null && $this->configNamespacing;
    }

    /**
     * Get the config vendor/namespace
     */
    public function getConfigVendor(): ?string
    {
        return $this->configVendor;
    }

    /**
     * Dotted namespace 'vendor.package', for config keys, event names,
     * or other dot-separated identifiers. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function getDottedNamespace(): string
    {
        return $this->getCachedNamespace('dotted', function (): string {
            if ($this->configVendor === null) {
                throw new RuntimeException(
                    'Dotted namespace requires vendor/package format. ' .
                    'Please use $package->setName("vendor/package") instead of just "package".'
                );
            }

            return Str::of($this->configVendor)
                ->append('.', $this->name)
                ->toString();
        });
    }

    /**
     * Dashed namespace 'vendor-package', for CSS classes, cache keys,
     * or file names. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function getDashedNamespace(): string
    {
        return $this->getCachedNamespace('dashed', function (): string {
            if ($this->configVendor === null) {
                throw new RuntimeException(
                    'Dashed namespace requires vendor/package format. ' .
                    'Please use $package->setName("vendor/package") instead of just "package".'
                );
            }

            return Str::of($this->configVendor)
                ->append('-', $this->name)
                ->toString();
        });
    }

    /**
     * Laravel-style namespace 'vendor::package', for views, translations,
     * and publish tags. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function getDoubleColonNamespace(): string
    {
        return $this->getCachedNamespace('doubleColon', function (): string {
            if ($this->configVendor === null) {
                throw new RuntimeException(
                    'Double-colon namespace requires vendor/package format. ' .
                    'Please use $package->setName("vendor/package") instead of just "package".'
                );
            }

            return Str::of($this->configVendor)
                ->append('::', $this->name)
                ->toString();
        });
    }

    /**
     * Composer-style namespace 'vendor/package', for package references,
     * URLs, or path-like identifiers. Vendor is required.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function getSlashNamespace(): string
    {
        return $this->getCachedNamespace('slash', function (): string {
            if ($this->configVendor === null) {
                throw new RuntimeException(
                    'Slash namespace requires vendor/package format. ' .
                    'Please use $package->setName("vendor/package") instead of just "package".'
                );
            }

            return Str::of($this->configVendor)
                ->append('/', $this->name)
                ->toString();
        });
    }

    /**
     * Build a namespace with any separator. Vendor is required.
     *
     * @param string $separator Custom separator (can be any string)
     *
     * @throws RuntimeException If vendor is not set
     *
     * @example
     * $package->buildNamespace('|'); // 'acme|widget'
     */
    public function buildNamespace(string $separator): string
    {
        if ($this->configVendor === null) {
            throw new RuntimeException(
                'Custom namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return Str::of($this->configVendor)
            ->append($separator, $this->name)
            ->toString();
    }

    /**
     * Laravel-style publish tag with the vendor namespace, e.g.
     * 'acme::widget-config'.
     *
     * @param string $suffix Tag suffix (default: 'config')
     */
    public function getNamespacedPublishTag(string $suffix = 'config'): string
    {
        return Str::of($this->getDoubleColonNamespace())
            ->append('-', $suffix)
            ->toString();
    }

    /**
     * Clear the namespace cache.
     *
     * Use after changing the package name or vendor.
     */
    public function clearNamespaceCache(): static
    {
        $this->namespaceCache = [];

        return $this;
    }

    /**
     * Get all cached namespaces.
     *
     * @return array<string, string>
     */
    public function getCachedNamespaces(): array
    {
        return $this->namespaceCache;
    }

    /**
     * Pre-compute all namespace formats.
     *
     * Call from the service provider's register() method to avoid lazy
     * computation later.
     */
    public function warmNamespaceCache(): static
    {
        $this->getDottedNamespace();
        $this->getDashedNamespace();
        $this->getDoubleColonNamespace();
        $this->getSlashNamespace();

        return $this;
    }
}

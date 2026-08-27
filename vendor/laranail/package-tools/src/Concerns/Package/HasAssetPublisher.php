<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Exceptions\UnsafeAssetPath;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishPathGuard;

/**
 * Asset publishing with a registry, optional directory cleanup, batch
 * publishing, standard asset types, keys-map groups, typed module
 * conveniences, and a declarative group registry.
 *
 * Folds in the former HasAssetGroups (declarative groups) and
 * HasModuleAssets (typed conveniences) traits.
 */
trait HasAssetPublisher
{
    /** @var array<string, array{source: string, destination: string, clean: bool, tag: string|null}> Asset registry */
    protected array $assetRegistry = [];

    /** @var array<string, array<string>> Asset groups registry (keys-map: group => list of "group:source" keys) */
    protected array $assetGroups = [];

    /** @var array<string, array{source: string, target: string}> Declarative asset group registry (group => resolved paths) */
    protected array $declaredAssetGroups = [];

    /** @var array<string> Standard asset type mappings */
    protected array $standardAssetTypes = [
        'all' => 'public',
        'js' => 'public/js',
        'css' => 'public/css',
        'images' => 'public/images',
        'media' => 'public/media',
        'fonts' => 'public/fonts',
        'vendors' => 'public/vendors',
    ];

    /**
     * Register assets for publishing with optional cleanup
     *
     * @param string $source Source directory (relative to package root)
     * @param string $destination Destination directory (relative to public)
     * @param bool $cleanBeforePublish Clean destination directory before publishing
     * @param string|null $tag Custom publish tag
     *
     * @example
     * ```php
     * $package->publishAssets('resources/assets', 'vendor/blog');
     * ```
     */
    public function publishAssets(
        string $source,
        string $destination,
        bool $cleanBeforePublish = false,
        ?string $tag = null
    ): static {
        $this->assetRegistry[$source] = [
            'source' => $source,
            'destination' => $destination,
            'clean' => $cleanBeforePublish,
            'tag' => $tag,
        ];

        return $this;
    }

    /**
     * Publish a single FILE under the package's namespaced publish tag
     * (`vendor::package-{suffix}`). The suffix defaults to the source file's
     * name without extension. Unlike the asset registry above, this registers a
     * plain `vendor:publish` mapping applied at boot.
     *
     * @example
     * $package->publishFile('config/security.php', config_path('acme-security.php'), 'security');
     */
    public function publishFile(string $source, string $destination, ?string $suffix = null, bool $clean = false): static
    {
        return $this->publishWithNamespacedTag($source, $destination, $suffix ?? pathinfo($source, PATHINFO_FILENAME), $clean);
    }

    /**
     * Publish a DIRECTORY under the package's namespaced publish tag
     * (`vendor::package-{suffix}`). The suffix defaults to the source
     * directory's basename.
     *
     * @example
     * $package->publishDirectory('stubs', base_path('stubs/vendor/acme'), 'stubs');
     */
    public function publishDirectory(string $source, string $destination, ?string $suffix = null, bool $clean = false): static
    {
        return $this->publishWithNamespacedTag($source, $destination, $suffix ?? basename($source), $clean);
    }

    /**
     * Register a source→destination publish under the package's namespaced tag
     * for the given suffix (shared by publishFile()/publishDirectory()).
     */
    private function publishWithNamespacedTag(string $source, string $destination, string $suffix, bool $clean): static
    {
        $this->validatePublishTagName($suffix);

        return $this->publish([$source => $destination], $this->getNamespacedPublishTag($suffix), $clean);
    }

    /**
     * Publish module assets by standard type.
     *
     * Types: 'all', 'js', 'css', 'images', 'media', 'fonts', 'vendors'.
     *
     * @param array<string>|string $types Asset type(s) to publish
     * @param bool $cleanBeforePublish Clean before publishing
     *
     * @example
     * ```php
     * $package->publishModuleAssets(['js', 'css']);
     * ```
     */
    public function publishModuleAssets(
        array|string $types = 'all',
        bool $cleanBeforePublish = false
    ): static {
        $types = is_string($types) ? [$types] : $types;

        foreach ($types as $type) {
            if (! isset($this->standardAssetTypes[$type])) {
                throw new RuntimeException("Unknown asset type '{$type}'. Available types: " . implode(', ', array_keys($this->standardAssetTypes)));
            }

            $sourceDir = $this->standardAssetTypes[$type];
            $destinationDir = $this->generateAssetDestination($type);

            $this->publishAssets(
                source: $sourceDir,
                destination: $destinationDir,
                cleanBeforePublish: $cleanBeforePublish,
                tag: "assets-{$type}"
            );
        }

        return $this;
    }

    /**
     * Publish a named group of assets.
     *
     * @param string $groupName Group name
     * @param array<string, string> $assets Map of source => destination
     * @param bool $cleanBeforePublish Clean before publishing
     *
     * @example
     * ```php
     * $package->publishAssetGroup('frontend', [
     *     'resources/js' => 'vendor/blog/js',
     *     'resources/css' => 'vendor/blog/css',
     * ]);
     * ```
     */
    public function publishAssetGroup(
        string $groupName,
        array $assets,
        bool $cleanBeforePublish = false
    ): static {
        $this->assetGroups[$groupName] = [];

        foreach ($assets as $source => $destination) {
            $assetKey = "{$groupName}:{$source}";

            $this->publishAssets(
                source: $source,
                destination: $destination,
                cleanBeforePublish: $cleanBeforePublish,
                tag: "group-{$groupName}"
            );

            $this->assetGroups[$groupName][] = $assetKey;
        }

        return $this;
    }

    /**
     * Publish multiple asset groups at once
     *
     * @param array<string, array<string, string>> $groups Map of group name => assets
     * @param bool $cleanBeforePublish Clean before publishing
     *
     * @example
     * ```php
     * $package->publishAssetGroups([
     *     'frontend' => [
     *         'resources/js' => 'vendor/blog/js',
     *         'resources/css' => 'vendor/blog/css',
     *     ],
     *     'backend' => [
     *         'resources/admin/js' => 'vendor/blog/admin/js',
     *     ],
     * ]);
     * ```
     */
    public function publishAssetGroups(
        array $groups,
        bool $cleanBeforePublish = false
    ): static {
        foreach ($groups as $groupName => $assets) {
            $this->publishAssetGroup($groupName, $assets, $cleanBeforePublish);
        }

        return $this;
    }

    /**
     * Publish custom assets from a source => destination map.
     *
     * @param array<string, string> $customAssets Map of source => destination
     * @param bool $cleanBeforePublish Clean before publishing
     * @param string|null $tag Custom tag
     *
     * @example
     * ```php
     * $package->publishCustomAssets([
     *     'resources/custom/icons' => 'vendor/blog/icons',
     *     'resources/custom/themes' => 'vendor/blog/themes',
     * ]);
     * ```
     */
    public function publishCustomAssets(
        array $customAssets,
        bool $cleanBeforePublish = false,
        ?string $tag = null
    ): static {
        foreach ($customAssets as $source => $destination) {
            $this->publishAssets(
                source: $source,
                destination: $destination,
                cleanBeforePublish: $cleanBeforePublish,
                tag: $tag ?? 'custom-assets'
            );
        }

        return $this;
    }

    /**
     * Publish module JavaScript assets.
     */
    public function publishModuleJs(bool $clean = false): static
    {
        return $this->publishModuleAssets('js', $clean);
    }

    /**
     * Publish module CSS assets.
     */
    public function publishModuleCss(bool $clean = false): static
    {
        return $this->publishModuleAssets('css', $clean);
    }

    /**
     * Publish module media assets (images, videos, etc.).
     */
    public function publishModuleMedia(bool $clean = false): static
    {
        return $this->publishModuleAssets('media', $clean);
    }

    /**
     * Publish module vendor assets (third-party libraries).
     */
    public function publishModuleVendors(bool $clean = false): static
    {
        return $this->publishModuleAssets('vendors', $clean);
    }

    /**
     * Publish all module assets.
     */
    public function publishAllModuleAssets(bool $clean = false): static
    {
        return $this->publishModuleAssets('all', $clean);
    }

    /**
     * Declare a named asset group resolved to source/target paths.
     *
     * Distinct from the keys-map {@see publishAssetGroup()}: this stores a
     * resolved [source, target] pair that the service provider publishes at
     * boot. Source defaults to `public/{name}`; target to
     * `vendor/{kebab}/{name}`.
     *
     * @param string $name Group name (e.g. 'js', 'css', 'images', 'fonts')
     * @param array{source?: string, target?: string} $config Optional overrides
     *
     * @example
     * ```php
     * $package->declareAssetGroup('js');
     * $package->declareAssetGroup('admin', ['source' => 'admin/js', 'target' => 'admin']);
     * ```
     */
    public function declareAssetGroup(string $name, array $config = []): static
    {
        $source = $config['source'] ?? $name;
        $target = $config['target'] ?? $name;

        $packageName = $this->getPackageKebabName();

        $this->declaredAssetGroups[$name] = [
            'source' => "public/{$source}",
            'target' => "vendor/{$packageName}/{$target}",
        ];

        return $this;
    }

    /**
     * Declare multiple asset groups.
     *
     * @param array<string, array{source?: string, target?: string}> $groups [name => config]
     */
    public function declareAssetGroups(array $groups): static
    {
        foreach ($groups as $name => $config) {
            $this->declareAssetGroup($name, $config);
        }

        return $this;
    }

    /**
     * Declare the standard asset groups (js, css, images, fonts).
     */
    public function declareStandardAssetGroups(): static
    {
        return $this->declareAssetGroups([
            'js' => [],
            'css' => [],
            'images' => [],
            'fonts' => [],
        ]);
    }

    /**
     * Declare an asset group with explicit source and target paths.
     *
     * @param string $name Group name
     * @param string $source Source path relative to the package public directory
     * @param string $target Target path relative to the application public directory
     */
    public function declareCustomAssetGroup(string $name, string $source, string $target): static
    {
        return $this->declareAssetGroup($name, [
            'source' => $source,
            'target' => $target,
        ]);
    }

    /**
     * Get the declared asset groups.
     *
     * @return array<string, array{source: string, target: string}>
     */
    public function getDeclaredAssetGroups(): array
    {
        return $this->declaredAssetGroups;
    }

    /**
     * Check if an asset should be cleaned before publishing
     *
     * @param string $source Source path
     */
    public function shouldCleanAsset(string $source): bool
    {
        return $this->assetRegistry[$source]['clean'] ?? false;
    }

    /**
     * Clean a specific asset destination directory
     *
     * @param string $source Source path (to lookup destination)
     * @return bool True if cleaned successfully
     */
    /**
     * Delete a registered asset destination.
     *
     * Routed through {@see PublishPathGuard} rather than calling
     * `File::deleteDirectory()` directly. The old form took the registered
     * destination straight into `public_path()` with no `..` rejection, no
     * symlink check and no minimum depth — so a destination of `''` resolved to
     * `public_path('')`, the document root, and `'../..'` escaped it entirely.
     *
     * Returns false when the guard refuses, which is the same answer the caller
     * already got for "nothing to clean": in both cases nothing was deleted.
     */
    public function cleanAsset(string $source): bool
    {
        if (! isset($this->assetRegistry[$source])) {
            return false;
        }

        $destination = public_path($this->assetRegistry[$source]['destination']);

        if (! File::isDirectory($destination)) {
            return false;
        }

        try {
            $guard = PublishPathGuard::fromConfig(app('config'), app()->basePath());
        } catch (UnsafeAssetPath) {
            return false;
        }

        if (! $guard->isDeletable($destination)) {
            return false;
        }

        return $guard->delete($destination);
    }

    /**
     * Clean all registered asset destinations
     *
     * @return int Number of directories cleaned
     */
    public function cleanAllAssets(): int
    {
        $cleaned = 0;

        foreach ($this->assetRegistry as $source => $config) {
            if ($config['clean'] && $this->cleanAsset($source)) {
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Generate asset destination path based on type
     *
     * @param string $type Asset type
     * @return string Destination path
     */
    protected function generateAssetDestination(string $type): string
    {
        $packageName = $this->getPackageKebabName();

        return match ($type) {
            'all' => "vendor/{$packageName}",
            default => "vendor/{$packageName}/{$type}",
        };
    }

    /**
     * Get all registered assets
     *
     * @return array<string, array{source: string, destination: string, clean: bool, tag: string|null}>
     */
    public function getAssetRegistry(): array
    {
        return $this->assetRegistry;
    }

    /**
     * Get all asset groups
     *
     * @return array<string, array<string>>
     */
    public function getAssetGroups(): array
    {
        return $this->assetGroups;
    }

    /**
     * Get assets by group name
     *
     * @return array<string>
     */
    public function getAssetsByGroup(string $groupName): array
    {
        return $this->assetGroups[$groupName] ?? [];
    }

    /**
     * Filter assets by type
     *
     * @param string $type Asset type
     * @return array<string, array{source: string, destination: string, clean: bool, tag: string|null}>
     */
    public function filterAssetsByType(string $type): array
    {
        return array_filter($this->assetRegistry, fn (array $asset): bool => str_contains($asset['tag'] ?? '', "assets-{$type}"));
    }

    /**
     * Check if asset type exists
     */
    public function hasAssetType(string $type): bool
    {
        return isset($this->standardAssetTypes[$type]);
    }

    /**
     * Get all standard asset types
     *
     * @return array<string>
     */
    public function getStandardAssetTypes(): array
    {
        return array_keys($this->standardAssetTypes);
    }

    /**
     * Clear asset registry
     */
    public function clearAssetRegistry(): static
    {
        $this->assetRegistry = [];
        $this->assetGroups = [];
        $this->declaredAssetGroups = [];

        return $this;
    }

    /**
     * Get package name in kebab-case
     */
    abstract protected function getPackageKebabName(): string;
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresAssets;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresAuthorization;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresCommands;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresComponents;
// Domain aggregators. Package only `use`s these; each aggregator composes
// its leaf traits (Has*) from src/Package/Concerns/Package/.
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresComposer;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresConfig;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresDatabase;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresEvents;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresHelpers;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresLifecycle;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresMiddleware;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresRoutes;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresServiceProviders;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresTranslations;
use Simtabi\Laranail\Package\Tools\Concerns\Package\ConfiguresViews;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPackage;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Support\PathResolver;

class Package
{
    // 15 domain aggregators wire the leaf traits under src/Concerns/Package/.
    use ConfiguresAssets;
    use ConfiguresAuthorization;
    use ConfiguresCommands;
    use ConfiguresComponents;
    use ConfiguresComposer;
    use ConfiguresConfig;
    use ConfiguresDatabase;
    use ConfiguresEvents;
    use ConfiguresHelpers;
    use ConfiguresLifecycle;
    use ConfiguresMiddleware;
    use ConfiguresRoutes;
    use ConfiguresServiceProviders;
    use ConfiguresTranslations;
    use ConfiguresViews;

    /** @var string Config directory path relative to package root */
    public const string CONFIG_DIR = 'config';

    /**
     * Reserved key a nested config file may declare to mount itself at an
     * explicit dotted namespace (optional). Stripped before merge, so it
     * never leaks into resolved config. E.g. `'__namespace' => 'acme.blog'`.
     */
    public const string CONFIG_NAMESPACE_KEY = '__namespace';

    /** @var string Views directory path relative to package root */
    public const string VIEWS_DIR = 'resources/views';

    /** @var string Translations directory path relative to package root */
    public const string LANG_DIR = 'resources/lang';

    /** @var string Helpers directory path relative to package root */
    public const string HELPERS_DIR = 'helpers';

    /** @var string Migrations directory path relative to package root */
    public const string MIGRATIONS_DIR = 'database/migrations';

    /** @var string Seeders directory path relative to package root */
    public const string SEEDERS_DIR = 'database/seeders';

    /** @var string Factories directory path relative to package root */
    public const string FACTORIES_DIR = 'database/factories';

    /** @var string Routes directory path relative to package root */
    public const string ROUTES_DIR = 'routes';

    /** @var string Public assets directory path relative to package root */
    public const string PUBLIC_DIR = 'public';

    /** @var string Assets directory path relative to package root */
    public const string ASSETS_DIR = 'resources/assets';

    public string $name = '';

    public string $basePath = '';

    /** @var string|null Config vendor name (extracted from 'vendor/package' format) */
    public ?string $configVendor = null;

    /** @var callable|null Custom name transformation callback */
    protected $nameTransformer;

    /** @var string|null Base publish tag ID (e.g., 'laranail', 'vendor') */
    protected ?string $publishTagId = null;

    /** @var array<string> Cache of built publish tags */
    protected array $builtPublishTags = [];

    /** @var array<string> Allowed tag separators */
    protected array $allowedSeparators = ['::', ':', '-'];

    /** @var array<string, array<string, string>> Asset paths to publish [tag => [source => destination]] */
    protected array $assetPaths = [];

    /** @var array<string, array<string, string>> Publish paths [tag => [source => destination]] */
    protected array $publishPaths = [];

    /** @var array<string, bool> Tags that should be cleaned before publishing [tag => true] */
    protected array $publishPathsToClean = [];

    /** @var array<string, string> Component namespaces [namespace => prefix] */
    protected array $componentNamespaces = [];

    /**
     * Set the package name, auto-extracting the vendor for namespacing.
     *
     * Given 'vendor/package', the package name becomes 'package' and the
     * config key is namespaced under the vendor. A transformer can rewrite
     * the package name (e.g. strip a 'laravel-' prefix). The name must be
     * non-empty and contain only alphanumerics, dashes, slashes, and
     * underscores.
     *
     * @param string $name Package name in 'vendor/package' or 'package' format
     * @param callable|null $transformer Optional callback to transform package name
     *
     * @throws InvalidPackage
     */
    public function name(string $name, ?callable $transformer = null): static
    {
        // Fluent alias for setName(); this is the documented public surface.
        return $this->setName($name, $transformer);
    }

    public function setName(string $name, ?callable $transformer = null): static
    {
        $trimmedName = Str::trim($name);
        if (empty($trimmedName)) {
            throw InvalidPackage::nameIsEmpty();
        }

        // Check the character set before the vendor-format check so invalid
        // names get the more helpful "Invalid package name" exception rather
        // than the vendor-format complaint.
        if (! preg_match('/^[a-zA-Z0-9\/\-_]+$/', $trimmedName)) {
            throw InvalidPackage::nameIsInvalid($trimmedName);
        }

        $this->nameTransformer = $transformer;

        if (Str::contains($trimmedName, '/')) {
            $parts = Str::of($trimmedName)->explode('/', 2);
            $vendor = $parts[0] ?? '';
            $packageName = $parts[1] ?? '';

            if (empty(Str::trim($vendor))) {
                throw InvalidPackage::nameIsInvalid($name);
            }

            if (empty(Str::trim($packageName))) {
                throw InvalidPackage::nameIsInvalid($name);
            }

            $finalName = $transformer ? $transformer($packageName) : $packageName;

            $finalName = Str::trim($finalName);
            if (empty($finalName)) {
                throw InvalidPackage::nameIsEmpty();
            }

            if (! preg_match('/^[a-zA-Z0-9\/\-_]+$/', $finalName)) {
                throw InvalidPackage::nameIsInvalid($finalName);
            }

            $this->name = $finalName;
            $this->configVendor = $vendor;
        } else {
            // Vendor/package format is required; single names are rejected.
            throw InvalidPackage::vendorRequired($trimmedName);
        }

        // Name/vendor just mutated — drop any memoized namespaces so they
        // recompute against the new values.
        $this->clearNamespaceCache();

        return $this;
    }

    /**
     * Get the short package name.
     *
     * name() already extracts the package name from 'vendor/package' and
     * applies any transformer, so this returns the stored name directly.
     */
    public function shortName(): string
    {
        return $this->name;
    }

    public function basePath(?string $directory = null): string
    {
        if ($directory === null) {
            return $this->basePath;
        }

        return Str::finish($this->basePath, DIRECTORY_SEPARATOR) . Str::ltrim($directory, DIRECTORY_SEPARATOR);
    }

    /**
     * Set the package base path from either a path string or a file reference.
     *
     * Detects the input type: a string is treated as a path, an object or
     * class name is resolved from its file location. Works across Windows,
     * WSL, Linux, and macOS. Path strings must be non-empty; file references
     * must be valid classes or objects.
     *
     * ```php
     * $package->setPathFrom($this);                  // from the service provider
     * $package->setPathFrom(__DIR__ . '/../');       // explicit path
     * $package->setPathFrom(MyServiceProvider::class, levelsUp: 3);
     * ```
     *
     * @param string|object $source A path string, an object instance
     *                              (typically `$this`), or a class name.
     * @param int|null $levelsUp Directory levels to climb for file-based
     *                           detection. For src/Providers/MyServiceProvider.php,
     *                           3 (default) reaches the package root.
     *                           Ignored when $source is a path string.
     *
     * @throws InvalidPath|RuntimeException
     */
    public function setPathFrom(string|object $source, ?int $levelsUp = null): static
    {
        // isPathString() returns false for every object, so a true result
        // guarantees $source is a string; narrow with is_string() so the
        // analyzer can see it too.
        if (is_string($source) && $this->isPathString($source)) {
            $trimmedPath = Str::trim($source);
            if (empty($trimmedPath)) {
                throw InvalidPath::pathIsEmpty();
            }

            $this->basePath = $trimmedPath;
        } else {
            if (! class_exists(PathResolver::class)) {
                throw new RuntimeException(
                    'PathResolver class not found. ' .
                    'Please ensure the Packager package is properly installed and autoloaded.'
                );
            }

            $levelsUp ??= 3;

            $this->basePath = PathResolver::packageRootFromProvider($source, $levelsUp);
        }

        return $this;
    }

    /**
     * Determine whether the source is a path string or a file reference.
     *
     * @return bool True for a path string, false for a file reference
     */
    private function isPathString(string|object $source): bool
    {
        // Objects are always file references (service provider instances).
        if (is_object($source)) {
            return false;
        }

        $string = Str::trim($source);

        // Empty string: treat as a path so validation can reject it.
        if (empty($string)) {
            return true;
        }

        // Absolute Unix/Linux/macOS paths.
        if (Str::startsWith($string, '/')) {
            return true;
        }

        // Absolute Windows paths (C:\, D:\, ...).
        if (preg_match('/^[A-Z]:[\\\\\/]/i', $string)) {
            return true;
        }

        // Relative path patterns.
        if (Str::contains($string, '..') || Str::contains($string, './') || Str::contains($string, '.\\')) {
            return true;
        }

        // Path-like constants or functions.
        if (preg_match('/^(__DIR__|__FILE__|base_path|public_path|storage_path|app_path|resource_path)/', $string)) {
            return true;
        }

        // Forward slashes mean a path, not a class.
        if (Str::contains($string, '/')) {
            return true;
        }

        // Backslashes could be a Windows path or a namespaced class.
        // A forward slash would already have returned true above, so only
        // the Windows-path vs. namespaced-class distinction remains here.
        if (Str::contains($string, '\\')) {
            // Drive letters or a leading \\ mark a Windows path.
            if (preg_match('/^[A-Z]:|^\\\\/', $string)) {
                return true; // Windows path
            }

            // Otherwise assume a namespaced class name.
            return false;
        }

        // A trailing dot suggests a relative path like "./config".
        return Str::contains($string, '.');
    }

    // Publish tag system.

    /**
     * Set the base publish tag ID, used as a prefix for tags generated by
     * buildPublishTag().
     *
     * @param string $id Base tag ID (e.g., 'laranail', 'vendor', 'package')
     */
    public function setPublishTagId(string $id): static
    {
        $this->publishTagId = Str::lower(Str::trim($id));

        return $this;
    }

    /**
     * Get the current publish tag ID
     */
    public function getPublishTagId(): ?string
    {
        return $this->publishTagId;
    }

    /**
     * Build a publish tag by combining the base tag ID with a name and
     * separator. Components are validated, normalized, and lowercased.
     *
     * Separators: '::', ':', '-'. Names may contain alphanumerics, dashes,
     * and colons (for nested tags), no spaces.
     *
     * @param string $name Tag name (e.g., 'config', 'blog-assets')
     * @param string $separator Separator between base and name (default: '::')
     * @return string Built and validated publish tag
     *
     * @throws RuntimeException If validation fails
     *
     * ```php
     * $package->buildPublishTag('config'); // 'laranail::config'
     * ```
     */
    public function buildPublishTag(string $name, string $separator = '::'): string
    {
        $this->validatePublishTagSeparator($separator);

        $name = $this->normalizePublishTagName($name);
        $this->validatePublishTagName($name);

        $baseTag = $this->getBasePublishTag();

        $fullTag = Str::lower($baseTag . $separator . $name);

        $this->builtPublishTags[] = $fullTag;

        return $fullTag;
    }

    /**
     * Ensure the separator is one of the allowed values.
     *
     * @param string $separator Separator to validate
     *
     * @throws RuntimeException If separator is invalid
     */
    protected function validatePublishTagSeparator(string $separator): void
    {
        $separator = Str::trim($separator);

        if (empty($separator)) {
            throw new RuntimeException('Publish tag separator cannot be empty');
        }

        if (! in_array($separator, $this->allowedSeparators, true)) {
            throw new RuntimeException(
                "Invalid publish tag separator '{$separator}'. " .
                'Allowed separators are: ' . implode(', ', $this->allowedSeparators)
            );
        }
    }

    /**
     * Ensure the name contains only allowed characters.
     *
     * @param string $name Tag name to validate
     *
     * @throws RuntimeException If name is invalid
     */
    protected function validatePublishTagName(string $name): void
    {
        if ($name === '' || $name === '0') {
            throw new RuntimeException('Publish tag name cannot be empty after cleaning');
        }

        // Alphanumerics, dashes, and colons (for nested tags).
        if (! preg_match('/^[a-zA-Z0-9\-:]+$/', $name)) {
            throw new RuntimeException(
                "Invalid publish tag name '{$name}'. " .
                'Only alphanumeric characters, dashes (-), and colons (:) are allowed'
            );
        }
    }

    /**
     * Trim whitespace and strip non-alphanumeric characters from the start
     * and end of the name, leaving its internal structure intact.
     *
     * @param string $name Name to normalize
     * @return string Normalized name
     */
    protected function normalizePublishTagName(string $name): string
    {
        $name = Str::trim($name);

        // Strip leading and trailing non-alphanumerics, keep internal structure.
        // preg_replace() returns null only on engine error; fall back to the
        // trimmed name so the return stays a string.
        return preg_replace('/^[^a-zA-Z0-9]+|[^a-zA-Z0-9]+$/', '', $name) ?? $name;
    }

    /**
     * Get the base publish tag from the explicit publishTagId, falling back
     * to config (laranail.package.publishing_tag_name).
     *
     * @return string Base publish tag
     *
     * @throws RuntimeException If no base tag is configured
     */
    protected function getBasePublishTag(): string
    {
        if (! in_array($this->publishTagId, [null, '', '0'], true)) {
            return $this->publishTagId;
        }

        $baseTag = config('laranail.package.publishing_tag_name');

        if (! empty($baseTag)) {
            return $baseTag;
        }

        throw new RuntimeException(
            'You must set a publish tag ID via setPublishTagId() ' .
            'or configure laranail.package.publishing_tag_name in your config'
        );
    }

    /**
     * Get the cache of all tags built during this request.
     *
     * @return array<string>
     */
    public function getBuiltPublishTags(): array
    {
        return $this->builtPublishTags;
    }

    /**
     * Clear the publish tags cache
     */
    public function clearPublishTagsCache(): static
    {
        $this->builtPublishTags = [];

        return $this;
    }

    // Abstract method implementations required by the Configures* concerns.

    protected function getViewNamespace(): string
    {
        return $this->viewNamespace();
    }

    protected function getConfigNamespace(): string
    {
        return $this->getDottedNamespace();
    }

    protected function getPackageKebabName(): string
    {
        return Str::kebab($this->name);
    }

    /**
     * Get the package base path with an optional subdirectory.
     *
     * @param string $path Optional subdirectory path
     */
    protected function packageBasePath(string $path = ''): string
    {
        return $this->basePath($path);
    }

    /**
     * Register asset paths for the service provider to publish during boot.
     *
     * @param array<string, string> $paths Array of paths to publish [source => destination]
     * @param string $tag Publish tag
     */
    protected function publishAssetPaths(array $paths, string $tag): void
    {
        $this->assetPaths[$tag] = $paths;
    }

    /**
     * Internal multi-path publish primitive. Registers paths into
     * $this->publishPaths[$tag] for the service provider's boot pass to
     * publish.
     *
     * @param array<string, string> $paths Array of paths to publish [source => destination]
     * @param string $tag Publish tag
     */
    protected function publishes(array $paths, string $tag): void
    {
        $this->publishPaths[$tag] = $paths;
    }

    /**
     * Publish arbitrary resources to arbitrary destinations under any tag.
     *
     * Source paths may be absolute or relative to the package base path;
     * destinations may point anywhere in the Laravel app. Optionally clean
     * the destination first or transform paths through a callback.
     *
     * @param array<string, string> $paths Array of paths to publish [source => destination]
     * @param string $tag Publish tag, any format
     * @param bool $cleanBeforePublish Whether to clean destination before publishing
     * @param callable|null $transformer Optional callback to transform paths: fn(string $source, string $dest) => ['newSource' => 'newDest']
     *
     * ```php
     * $package->publish(['resources/icons' => 'public/vendor/blog/icons'], 'blog-assets');
     * ```
     */
    public function publish(array $paths, string $tag, bool $cleanBeforePublish = false, ?callable $transformer = null): static
    {
        if ($paths === []) {
            throw new InvalidArgumentException('Publish paths cannot be empty. Provide at least one source => destination mapping.');
        }

        if (empty(Str::trim($tag))) {
            throw new InvalidArgumentException('Publish tag cannot be empty.');
        }

        if ($transformer !== null) {
            $transformedPaths = [];
            foreach ($paths as $source => $destination) {
                $result = $transformer($source, $destination);
                if (is_array($result)) {
                    // Path maps are associative (source => destination); preserve
                    // string keys and let later mappings win on collision.
                    $transformedPaths = array_replace($transformedPaths, $result);
                } else {
                    throw new InvalidArgumentException('Transformer callback must return an array of [source => destination] mappings.');
                }
            }
            $paths = $transformedPaths;
        }

        // Resolve relative source paths against the package base path.
        $resolvedPaths = [];
        foreach ($paths as $source => $destination) {
            $source = (string) $source;
            if (! Str::startsWith($source, '/') && ! preg_match('/^[A-Z]:\\\\/', $source)) {
                $source = $this->basePath($source);
            }

            $resolvedPaths[$source] = $destination;
        }

        if ($cleanBeforePublish) {
            $this->publishPathsToClean[$tag] = true;
        }

        // Merge with existing paths for the same tag so repeat calls accumulate.
        // Paths are associative (source => destination); preserve keys and let
        // the newest mapping win when the same source is published twice.
        if (isset($this->publishPaths[$tag])) {
            $this->publishPaths[$tag] = array_replace($this->publishPaths[$tag], $resolvedPaths);
        } else {
            $this->publishPaths[$tag] = $resolvedPaths;
        }

        return $this;
    }

    /**
     * Register a component namespace for boot-time registration.
     *
     * @param string $namespace Component namespace
     * @param string $prefix Component prefix
     */
    protected function registerComponentNamespace(string $namespace, string $prefix): void
    {
        $this->componentNamespaces[$namespace] = $prefix;
    }

    /**
     * Get all registered component namespaces.
     *
     * @return array<string, string> [class namespace => tag prefix]
     */
    public function getComponentNamespaces(): array
    {
        return $this->componentNamespaces;
    }

    /**
     * Get all custom publish paths registered via publish().
     *
     * @return array<string, array<string, string>> Array of publish paths [tag => [source => destination]]
     */
    public function getPublishPaths(): array
    {
        return $this->publishPaths;
    }

    /**
     * Get tags that should be cleaned before publishing
     *
     * @return array<string, bool> Array of tags to clean [tag => true]
     */
    public function getPublishPathsToClean(): array
    {
        return $this->publishPathsToClean;
    }
}

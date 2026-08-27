<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use InvalidArgumentException;

/**
 * Chainable interface for package-specific operations, modelled on
 * Laravel's cache(), config(), and app() helpers.
 *
 *     package_blog()->config('posts_per_page', 10)
 *     package_blog()->route('index')
 *     package_blog('key')  // shortcut for ->config('key')
 */
class FluentPackageHelper
{
    /**
     * @param string $vendor Vendor name (e.g., 'acme')
     * @param string $package Package name (e.g., 'blog')
     * @param string $configNamespace Config namespace (e.g., 'blog' or 'acme.blog')
     * @param string $viewNamespace View namespace (e.g., 'acme/blog')
     * @param string $translationNamespace Translation namespace (e.g., 'acme/blog')
     * @param string $routePrefix Route prefix (e.g., 'packages.blog')
     */
    public function __construct(protected string $vendor, protected string $package, protected string $configNamespace, protected string $viewNamespace, protected string $translationNamespace, protected string $routePrefix) {}

    /**
     * Get configuration value
     *
     * @param string|null $key Configuration key
     * @param mixed $default Default value if key not found
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return config($this->configNamespace);
        }

        return config("{$this->configNamespace}.{$key}", $default);
    }

    /**
     * Check if package is enabled
     */
    public function enabled(): bool
    {
        return (bool) $this->config('enabled', true);
    }

    /**
     * Generate route URL
     *
     * @param string $name Route name (without prefix)
     * @param array<string, mixed> $parameters Route parameters
     */
    public function route(string $name, array $parameters = []): string
    {
        return route("{$this->routePrefix}.{$name}", $parameters);
    }

    /**
     * Get asset URL
     *
     * @param string $path Asset path
     */
    public function asset(string $path): string
    {
        return asset("vendor/{$this->package}/{$path}");
    }

    /**
     * Get view instance
     *
     * @param string $view View name (without namespace)
     * @param array<string, mixed> $data View data
     */
    public function view(string $view, array $data = []): View
    {
        // The view name is built at runtime, so it can't satisfy the
        // view() helper's view-string type. View::make() accepts a plain
        // string and returns the same View contract.
        return ViewFactory::make("{$this->viewNamespace}::{$view}", $data);
    }

    /**
     * Get translation
     *
     * @param string $key Translation key (without namespace)
     * @param array<string, mixed> $replace Replacement values
     * @param string|null $locale Locale override
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return __("{$this->translationNamespace}::{$key}", $replace, $locale);
    }

    /**
     * Get namespace for given type
     *
     * @param string $type Namespace type (view, translation, config)
     *
     * @throws InvalidArgumentException If type is unknown
     */
    public function namespace(string $type = 'view'): string
    {
        return match ($type) {
            'view' => $this->viewNamespace,
            'translation', 'trans', 'lang' => $this->translationNamespace,
            'config' => $this->configNamespace,
            default => throw new InvalidArgumentException("Unknown namespace type: {$type}"),
        };
    }

    /**
     * Get vendor name
     */
    public function vendor(): string
    {
        return $this->vendor;
    }

    /**
     * Get package name
     */
    public function package(): string
    {
        return $this->package;
    }

    /**
     * Get route prefix
     */
    public function routePrefix(): string
    {
        return $this->routePrefix;
    }

    /**
     * Get view namespace
     */
    public function viewNamespace(): string
    {
        return $this->viewNamespace;
    }

    /**
     * Get translation namespace
     */
    public function translationNamespace(): string
    {
        return $this->translationNamespace;
    }

    /**
     * Get config namespace
     */
    public function configNamespace(): string
    {
        return $this->configNamespace;
    }
}

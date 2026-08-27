<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Config;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Simtabi\Laranail\Package\Tools\Contracts\ServiceInterface;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;

/**
 * Merges, sets, gets, and forgets configuration values.
 */
class ConfigService implements ServiceInterface
{
    /** @var array<string, string> */
    protected array $mergedConfigs = [];

    public function __construct(protected Application $app) {}

    /**
     * Resolve the framework config repository as a typed contract.
     */
    protected function config(): Repository
    {
        return $this->app->make(Repository::class);
    }

    /**
     * Merge configuration from a file into a key
     *
     * @param string $path Path to config file
     * @param string $key Config key to merge into
     */
    public function merge(string $path, string $key): void
    {
        if (! file_exists($path)) {
            return;
        }

        $config = require $path;

        if (! is_array($config)) {
            return;
        }

        $existing = $this->config()->get($key, []);
        $merged = array_merge($config, $existing);

        $this->config()->set($key, $merged);
        $this->mergedConfigs[$key] = $path;
    }

    /**
     * Merge configuration into a global Laravel config key
     *
     * Used for configs that Laravel expects at root level:
     * - services.php
     * - mail.php
     * - queue.php
     * - broadcasting.php
     *
     * @param string $path Path to config file
     * @param string $globalKey Global config key (e.g., 'services', 'mail')
     */
    public function mergeGlobal(string $path, string $globalKey): void
    {
        if (! file_exists($path)) {
            return;
        }

        $config = require $path;

        if (! is_array($config)) {
            return;
        }

        $existing = $this->config()->get($globalKey, []);
        $merged = array_merge_recursive($existing, $config);

        $this->config()->set($globalKey, $merged);
    }

    /**
     * Load config files from a package directory and RETURN them as raw arrays
     * keyed by folder-derived dotted key, WITHOUT registering anything in the
     * config repository. `$baseDir` is treated as a package root: files are read
     * from `{baseDir}/config/{folder}`, keyed the way the Package builder's
     * discoversConfig() would mount them (config/admin/panel.php → 'admin.panel').
     *
     * Prefer `config('vendor.package.key')` for registered package config; reach
     * for this only when you need the raw arrays ad-hoc (inspecting/transforming
     * config files without mounting them). Data is returned as-is (an in-file
     * `__namespace` key is not processed).
     *
     * @param string $baseDir Package root directory (containing a config/ folder)
     * @param string $folder Subdirectory within config/ ('' = the whole tree)
     * @param bool $recursive Descend into sub-folders (true) or top level only (false)
     * @return array<string, array<string, mixed>> Map of dotted key => config array
     *
     * @throws InvalidPath If a matched file is unreadable or not an array
     */
    public function loadFrom(string $baseDir, string $folder = '', bool $recursive = true): array
    {
        return (new ConfigFileResolver($baseDir))->loadAll($folder, $recursive);
    }

    /**
     * Set a configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $value Value to set
     */
    public function set(string $key, mixed $value): void
    {
        $this->config()->set($key, $value);
    }

    /**
     * Get a configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key doesn't exist
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config()->get($key, $default);
    }

    /**
     * Forget/remove a configuration value
     *
     * Re-seeding the surviving keys is not enough on its own: a removed
     * top-level key is simply absent from the pruned copy, so nothing ever
     * touches it and `get()` keeps returning the old value. The pruned array has
     * to replace the repository's store — see {@see ConfigItemStore}.
     *
     * @param string $key Configuration key to remove
     */
    public function forget(string $key): void
    {
        $items = $this->config()->all();
        Arr::forget($items, $key);

        ConfigItemStore::forget($this->config(), $key, $items);
    }

    /**
     * Check if a configuration key exists
     *
     * @param string $key Configuration key
     */
    public function has(string $key): bool
    {
        return $this->config()->has($key);
    }

    /**
     * Get all merged configuration paths
     *
     * @return array<string, string> Array of key => path
     */
    public function getMergedConfigs(): array
    {
        return $this->mergedConfigs;
    }

    /**
     * {@inheritDoc}
     */
    public function isReady(): bool
    {
        return $this->app->bound('config');
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'config';
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigFileResolver;

/**
 * Loads config files from nested directories, mounting each at a dotted
 * config key derived from its folder path so `config('folder.file.key')`
 * resolves natively (e.g. config/admin/panel.php → config('admin.panel.*')).
 */
trait HasNestedConfigFiles
{
    protected ?ConfigFileResolver $configFileResolver = null;

    /**
     * Register one config file from a nested directory, mounted at a dotted
     * key derived from its folder (config/admin/panel.php → 'admin.panel').
     *
     * @param string $fileName Config file name (without .php)
     * @param string $folder Nested folder path (e.g. 'admin', 'api/v1')
     * @param string|null $key Explicit dotted key (overrides the folder-derived one)
     */
    public function hasNestedConfig(string $fileName, string $folder = '', ?string $key = null): static
    {
        $resolver = $this->getConfigFileResolver();
        $configPath = $resolver->resolveNested($fileName, $folder);

        if (! File::exists($configPath)) {
            return $this;
        }

        $folder = trim($folder, '/\\');
        $relativeNoExt = $folder === '' ? $fileName : $folder . '/' . $fileName;
        $configKey = $key ?? $resolver->folderToKey($relativeNoExt);

        $this->registerNamespacedConfig($configPath, $configKey, $relativeNoExt . '.php');

        return $this;
    }

    /**
     * Register multiple config files from the same nested directory.
     *
     * @param array<string> $files Config file names (without .php)
     * @param string $folder Nested folder path
     */
    public function hasNestedConfigs(array $files, string $folder = ''): static
    {
        foreach ($files as $fileName) {
            $this->hasNestedConfig($fileName, $folder);
        }

        return $this;
    }

    /**
     * Register every config file directly in a nested directory (one level,
     * non-recursive). The recursive sibling is discoversConfig().
     *
     * @param string $folder Folder path relative to config/
     */
    public function hasConfigDirectory(string $folder): static
    {
        $configPath = $this->packageBasePath(Package::CONFIG_DIR . '/' . trim($folder, '/'));

        if (File::isDirectory($configPath)) {
            $files = glob($configPath . '/*.php') ?: [];

            foreach ($files as $file) {
                $this->hasNestedConfig(basename($file, '.php'), $folder);
            }
        }

        return $this;
    }

    /**
     * Recursively discover the package's config tree and mount every file at
     * its folder-derived dotted key, so config('a.b.file.key') resolves.
     *
     * @param string $namespace Optional root prefix (e.g. 'acme' → 'acme.a.b.file')
     * @param string $folder Subtree to scan ('' = the whole config/ dir)
     */
    public function discoversConfig(string $namespace = '', string $folder = ''): static
    {
        $resolver = $this->getConfigFileResolver();
        $namespace = trim($namespace, '.');

        foreach ($resolver->getAllNested($folder) as $relative) {
            $relativeNoExt = (string) preg_replace('/\.php$/', '', (string) $relative);
            $folderKey = $resolver->folderToKey($relativeNoExt);
            $configKey = $namespace === '' ? $folderKey : $namespace . '.' . $folderKey;
            $configPath = $this->packageBasePath(Package::CONFIG_DIR . '/' . $relative);

            $this->registerNamespacedConfig($configPath, $configKey, $relative);
        }

        return $this;
    }

    /**
     * Load the package's own nested config files from a folder and RETURN them
     * as raw arrays keyed by folder-derived dotted key, WITHOUT registering them
     * in the config repository — the read-and-return counterpart of
     * discoversConfig(). config/admin/panel.php → ['admin.panel' => [...]].
     *
     * @param string $folder Subdirectory within config/ ('' = the whole tree)
     * @param bool $recursive Descend into sub-folders (true) or top level only (false)
     * @return array<string, array<string, mixed>> Map of dotted key => config array
     *
     * @throws InvalidPath If a matched file is unreadable or not an array
     */
    public function loadConfigData(string $folder = '', bool $recursive = true): array
    {
        return $this->getConfigFileResolver()->loadAll($folder, $recursive);
    }

    /**
     * Get or create config file resolver instance
     */
    protected function getConfigFileResolver(): ConfigFileResolver
    {
        if (! $this->configFileResolver) {
            $this->configFileResolver = new ConfigFileResolver($this->packageBasePath());
        }

        return $this->configFileResolver;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;

    abstract public function hasConfigFile($configFileName = null): static;

    abstract public function registerNamespacedConfig(string $path, string $key, string $relative): static;
}

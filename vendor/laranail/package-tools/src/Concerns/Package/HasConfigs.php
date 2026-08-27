<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasConfigs
{
    public bool $hasConfigs = false;

    /** @var list<string> */
    public array $configFileNames = [];

    /**
     * Folder-namespaced config files registered via hasNestedConfig() /
     * discoversConfig(). Each entry is mounted at its dotted key at boot, so
     * `config('folder.file.key')` resolves natively. Kept separate from the
     * flat configFileNames list (which stays backward-compatible).
     *
     * @var list<array{path: string, key: string, relative: string}>
     */
    public array $namespacedConfigFiles = [];

    /**
     * @param string|array<int, string>|null $configFileName
     */
    public function hasConfigFile($configFileName = null): static
    {
        $configFileName ??= $this->shortName();

        $names = is_array($configFileName) ? $configFileName : [$configFileName];

        // Append + de-duplicate. Multiple hasConfigFile() calls register
        // multiple config files (the test contract); a single array-form
        // call registers all entries at once.
        foreach ($names as $name) {
            if (! in_array($name, $this->configFileNames, true)) {
                $this->configFileNames[] = $name;
            }
        }

        $this->hasConfigs = true;

        return $this;
    }

    /**
     * Register a folder-namespaced config file mounted at a dotted key.
     * De-duplicates by source path.
     *
     * @param string $path Absolute path to the config file
     * @param string $key Dotted config key (e.g. 'admin.panel')
     * @param string $relative Path relative to config/ (e.g. 'admin/panel.php')
     */
    public function registerNamespacedConfig(string $path, string $key, string $relative): static
    {
        foreach ($this->namespacedConfigFiles as $existing) {
            if ($existing['path'] === $path) {
                return $this;
            }
        }

        $this->namespacedConfigFiles[] = [
            'path' => $path,
            'key' => $key,
            'relative' => str_replace('\\', '/', $relative),
        ];

        $this->hasConfigs = true;

        return $this;
    }

    /**
     * Register several namespaced config files at once. Each entry is an
     * associative array with `path`, `key` and `relative` keys.
     *
     * @param array<int, array{path: string, key: string, relative: string}> $configs
     */
    public function registerNamespacedConfigs(array $configs): static
    {
        foreach ($configs as $config) {
            $this->registerNamespacedConfig($config['path'], $config['key'], $config['relative']);
        }

        return $this;
    }
}

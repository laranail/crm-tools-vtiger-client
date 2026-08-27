<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Config handling: deep merge into global config keys (e.g. services.php),
 * safe mode to prevent accidental overwrites, validation, batch operations.
 */
trait HasAdvancedConfig
{
    /** @var array<string, array{source: string, target: string, deep: bool}> Merge queue */
    protected array $configMerges = [];

    /** @var bool Safe mode prevents overwriting existing config values */
    protected bool $configSafeMode = true;

    /**
     * Merge package config into a global Laravel config key, e.g. inject into
     * `services.php`, `auth.php`, `filesystems.php`.
     *
     * @param string $sourceKey Source config key (from package config)
     * @param string $targetKey Target global config key to merge into
     * @param bool $deep Use deep merge (default: true)
     *
     * @example $package->mergeConfigInto('auth.guards', 'auth.guards');
     */
    public function mergeConfigInto(
        string $sourceKey,
        string $targetKey,
        bool $deep = true
    ): static {
        $this->configMerges[$sourceKey] = [
            'source' => $sourceKey,
            'target' => $targetKey,
            'deep' => $deep,
        ];

        return $this;
    }

    /**
     * Process all queued config merges. Call from the service provider's
     * register() after config files are loaded.
     */
    public function processConfigMerges(): void
    {
        foreach ($this->configMerges as $merge) {
            $this->executeMerge(
                $merge['source'],
                $merge['target'],
                $merge['deep']
            );
        }
    }

    /**
     * @param string $sourceKey Source key from package config
     * @param string $targetKey Target global config key
     * @param bool $deep Use deep merge
     *
     * @throws RuntimeException If merge validation fails
     */
    protected function executeMerge(string $sourceKey, string $targetKey, bool $deep): void
    {
        $namespace = $this->getConfigNamespace();
        $fullSourceKey = $namespace . '.' . $sourceKey;

        $sourceData = Config::get($fullSourceKey);

        if ($sourceData === null) {
            return;
        }

        $targetData = Config::get($targetKey, []);

        if ($this->configSafeMode && ! empty($targetData)) {
            $this->validateSafeMerge($targetData, $sourceData, $targetKey);
        }

        $merged = $deep
            ? $this->deepMerge($targetData, $sourceData)
            : array_merge((array) $targetData, (array) $sourceData);

        Config::set($targetKey, $merged);
    }

    /**
     * Recursively merge two arrays. Unlike array_merge_recursive, this
     * preserves integer keys and replaces scalars instead of nesting them.
     *
     * @param array<array-key, mixed> $target Target array
     * @param array<array-key, mixed> $source Source array
     * @return array<array-key, mixed> Merged array
     */
    protected function deepMerge(array $target, array $source): array
    {
        foreach ($source as $key => $value) {
            if (is_array($value) && isset($target[$key]) && is_array($target[$key])) {
                $target[$key] = $this->deepMerge($target[$key], $value);
            } else {
                $target[$key] = $value;
            }
        }

        return $target;
    }

    /**
     * Throw if the merge would overwrite existing keys while safe mode is on.
     *
     * @param array<array-key, mixed> $target Target config
     * @param array<array-key, mixed> $source Source config
     * @param string $targetKey Target key path (for error messages)
     *
     * @throws RuntimeException If keys would be overwritten
     */
    protected function validateSafeMerge(array $target, array $source, string $targetKey): void
    {
        $conflicts = $this->findConflicts($target, $source);

        if (! empty($conflicts)) {
            throw new RuntimeException(
                "Config merge conflict: The following keys in '{$targetKey}' would be overwritten: " .
                implode(', ', $conflicts) . '. ' .
                'Disable safe mode with disableConfigSafeMode() if this is intentional.'
            );
        }
    }

    /**
     * Find conflicting keys between target and source
     *
     * @param array<array-key, mixed> $target Target array
     * @param array<array-key, mixed> $source Source array
     * @param string $prefix Key prefix for nested paths
     * @return list<string> Array of conflicting key paths
     */
    protected function findConflicts(array $target, array $source, string $prefix = ''): array
    {
        $conflicts = [];

        foreach ($source as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (! isset($target[$key])) {
                continue;
            }

            if (is_array($value) && is_array($target[$key])) {
                $nestedConflicts = $this->findConflicts($target[$key], $value, $fullKey);
                $conflicts = array_merge($conflicts, $nestedConflicts);
            } else {
                $conflicts[] = $fullKey;
            }
        }

        return $conflicts;
    }

    /**
     * Disable safe mode, allowing existing config values to be overwritten.
     */
    public function disableConfigSafeMode(): static
    {
        $this->configSafeMode = false;

        return $this;
    }

    /**
     * Enable safe mode (the default), preventing overwrites of existing config.
     */
    public function enableConfigSafeMode(): static
    {
        $this->configSafeMode = true;

        return $this;
    }

    /**
     * Check if config safe mode is enabled
     */
    public function isConfigSafeMode(): bool
    {
        return $this->configSafeMode;
    }

    /**
     * Get all queued config merges
     *
     * @return array<string, array{source: string, target: string, deep: bool}>
     */
    public function getQueuedConfigMerges(): array
    {
        return $this->configMerges;
    }

    /**
     * Clear all queued config merges
     */
    public function clearConfigMerges(): static
    {
        $this->configMerges = [];

        return $this;
    }

    /**
     * @param string $key Key to validate
     *
     * @throws RuntimeException If key is invalid
     */
    protected function validateConfigKey(string $key): void
    {
        if (in_array(trim($key), ['', '0'], true)) {
            throw new RuntimeException('Config key cannot be empty');
        }

        if (preg_match('/[^a-zA-Z0-9._-]/', $key)) {
            throw new RuntimeException(
                "Invalid config key '{$key}'. " .
                'Only alphanumeric characters, dots (.), dashes (-), and underscores (_) are allowed'
            );
        }
    }

    /**
     * Config namespace, implemented by the Package class.
     */
    abstract protected function getConfigNamespace(): string;
}

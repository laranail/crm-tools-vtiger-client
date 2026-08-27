<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

use Closure;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigService;

/**
 * Fluent, chainable runtime configuration manager.
 *
 * ## How this differs from {@see ConfigService}
 *
 * They are not competing APIs; they run at different times with opposite
 * intent, and mixing them up is the only way they conflict.
 *
 * | | `ConfigService` | `ConfigManager` |
 * |---|---|---|
 * | When | boot, from a package provider | runtime, from the application |
 * | Merge | `array_merge($file, $existing)` — **app config wins** | override — **the caller wins** |
 * | Owns | a package mounting its own defaults | an app deliberately reshaping config |
 *
 * `ConfigService` is `mergeConfigFrom` semantics: a package ships defaults and
 * yields to whatever the application already set, which is what makes published
 * config work. `ConfigManager` is for the application saying "no, this value,
 * now" — so it overrides by design.
 *
 * Runtime-only: nothing is written to disk.
 */
interface ConfigManagerInterface
{
    /** Base path that relative file operations resolve against. */
    public function setBasePath(string $path): static;

    /** Record every operation for later inspection via {@see getLog()}. */
    public function withLogging(bool $enabled = true): static;

    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function set(string $key, mixed $value): static;

    /** Semantic alias of {@see set()} for overriding an existing value. */
    public function override(string $key, mixed $value): static;

    /** Remove a key so both `get()` and `has()` miss afterwards. */
    public function remove(string $key): static;

    /** Alias of {@see remove()}. */
    public function forget(string $key): static;

    /**
     * Deep-merge values into an existing key (true recursive merge, not
     * `array_merge_recursive`, which turns duplicate string keys into arrays).
     *
     * @param array<string, mixed> $values
     */
    public function merge(string $key, array $values): static;

    public function setIfMissing(string $key, mixed $value): static;

    /** @param array<string, mixed> $values */
    public function setMany(array $values): static;

    /** @param array<string, mixed> $values */
    public function overrideMany(array $values): static;

    /**
     * Load a config file and override each of its values under `$configKey`.
     *
     * @throws InvalidPath when the file is missing or does not return an array
     */
    public function loadAndOverride(string $configKey, string $filePath): static;

    public function loadPackageConfig(string $configKey, string $folder = 'config/packages'): static;

    /** @param array<int, string> $configKeys */
    public function loadPackageConfigs(array $configKeys, string $folder = 'config/packages'): static;

    /**
     * Read a config file's raw array from the base path's `config/` directory.
     * Lenient: an absent file yields an empty array.
     *
     * @return array<string, mixed>
     */
    public function loadConfigFile(string $file): array;

    /**
     * Copy one section of a source array over a config path.
     *
     * @param array<string, mixed> $source
     */
    public function overrideSection(array $source, string $sectionKey, string $targetPath): static;

    public function push(string $key, mixed $value): static;

    public function prepend(string $key, mixed $value): static;

    /**
     * @param bool|Closure(): bool $condition
     * @param Closure(static): void $callback
     */
    public function when(bool|Closure $condition, Closure $callback): static;

    /**
     * @param bool|Closure(): bool $condition
     * @param Closure(static): void $callback
     */
    public function unless(bool|Closure $condition, Closure $callback): static;

    /**
     * @param string|array<int, string> $environments
     * @param Closure(static): void $callback
     */
    public function inEnvironment(string|array $environments, Closure $callback): static;

    /** @param Closure(static, mixed): void $callback */
    public function whenHas(string $key, Closure $callback): static;

    /** @param Closure(mixed): mixed $transformer */
    public function transform(string $key, Closure $transformer): static;

    /** @param Closure(mixed, array-key): mixed $callback */
    public function each(string $key, Closure $callback): static;

    /**
     * @return array<int, array{operation: string, key: string, value?: mixed}>
     */
    public function getLog(): array;

    public function clearLog(): static;

    /** @return array<string, mixed> */
    public function all(): array;

    /** Dump a key (or everything) and continue the chain. */
    public function dump(string $key = ''): static;

    /** Dump a key (or everything) and halt. */
    public function dd(string $key = ''): never;
}

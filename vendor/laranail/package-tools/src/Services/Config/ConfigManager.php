<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Config;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Simtabi\Laranail\Package\Tools\Contracts\ConfigManagerInterface;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;

/**
 * Fluent, chainable runtime configuration manager.
 *
 * Every mutator returns `$this`, so operations compose:
 *
 * ```php
 * $config
 *     ->setBasePath(base_path('platform/modules/core'))
 *     ->override('horizon.path', '/')
 *     ->merge('app', ['providers' => [MyProvider::class]])
 *     ->when(app()->isLocal(), fn ($c) => $c->override('app.debug', true))
 *     ->remove('services.unused');
 * ```
 *
 * See {@see ConfigManagerInterface} for how this differs from
 * {@see ConfigService} — briefly, that one is a package mounting its defaults at
 * boot and yielding to the app, this one is the app overriding at runtime.
 *
 * All access flows through the injected {@see Repository} — no facade/container
 * mixing — and {@see merge()} uses {@see ConfigMerger} for a true deep merge
 * rather than `array_merge_recursive`, which folds duplicate string keys into
 * arrays instead of overwriting them. Runtime-only: nothing is written to disk.
 */
class ConfigManager implements ConfigManagerInterface
{
    /** Sentinel distinguishing "no value" from a legitimate null in the log. */
    private const string NO_VALUE = "\0__no_value__\0";

    protected string $basePath = '';

    /**
     * @var array<int, array{operation: string, key: string, value?: mixed}>
     */
    protected array $operationLog = [];

    protected bool $logging = false;

    public function __construct(
        protected readonly Repository $config,
        protected readonly Application $app,
        protected readonly ConfigMerger $merger = new ConfigMerger,
    ) {}

    // --- Fluent setters -----------------------------------------------------

    public function setBasePath(string $path): static
    {
        $this->basePath = rtrim($path, '/\\');

        return $this;
    }

    public function withLogging(bool $enabled = true): static
    {
        $this->logging = $enabled;

        return $this;
    }

    // --- Core operations ----------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->config->has($key);
    }

    public function set(string $key, mixed $value): static
    {
        $this->config->set($key, $value);
        $this->log('set', $key, $value);

        return $this;
    }

    /** Semantic alias of {@see set()} for overriding an existing value. */
    public function override(string $key, mixed $value): static
    {
        return $this->set($key, $value);
    }

    /**
     * Remove a config key so that both `get()` and `has()` miss afterwards.
     *
     * The obvious implementation does not work. `Repository::set()` only ever
     * adds or overwrites, so re-seeding a pruned copy of `all()` leaves a
     * removed top-level key exactly where it was; and `offsetUnset()` is
     * literally `set($key, null)`, which leaves `has()` reporting true. Passing
     * the whole array to `set()` — as the code this replaces did — is worse
     * still: it re-seeds every surviving key and silently does nothing at all
     * for the one you asked to remove.
     *
     * So the pruned array is written back over the repository's own item store
     * directly. When that is not possible — a custom `Repository` with a
     * different shape — it degrades to nulling the key, which is what the
     * framework itself offers.
     */
    public function remove(string $key): static
    {
        $all = $this->config->all();
        Arr::forget($all, $key);

        ConfigItemStore::forget($this->config, $key, $all);

        $this->log('remove', $key);

        return $this;
    }

    /** Alias of {@see remove()}. */
    public function forget(string $key): static
    {
        return $this->remove($key);
    }

    /**
     * Deep-merge values into an existing config key (true recursive merge).
     *
     * @param array<string, mixed> $values
     */
    public function merge(string $key, array $values): static
    {
        /** @var array<int|string, mixed> $existing */
        $existing = Arr::wrap($this->config->get($key, []));
        $this->config->set($key, $this->merger->deepMerge($existing, $values));
        $this->log('merge', $key, $values);

        return $this;
    }

    public function setIfMissing(string $key, mixed $value): static
    {
        if (! $this->config->has($key)) {
            $this->config->set($key, $value);
            $this->log('setIfMissing', $key, $value);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setMany(array $values): static
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function overrideMany(array $values): static
    {
        return $this->setMany($values);
    }

    // --- File-based operations ---------------------------------------------

    /**
     * Load a config file and override each of its values under `$configKey`.
     *
     * @throws InvalidPath when the file is missing or does not return an array
     */
    public function loadAndOverride(string $configKey, string $filePath): static
    {
        $fullPath = $this->resolvePath($filePath);

        if (! File::exists($fullPath)) {
            throw InvalidPath::configFileNotReadable($fullPath);
        }

        $values = File::getRequire($fullPath);

        if (! is_array($values)) {
            throw InvalidPath::configFileNotArray($fullPath);
        }

        foreach ($values as $key => $value) {
            $this->set("{$configKey}.{$key}", $value);
        }

        $this->log('loadAndOverride', $configKey, ['file' => $fullPath]);

        return $this;
    }

    public function loadPackageConfig(string $configKey, string $folder = 'config/packages'): static
    {
        return $this->loadAndOverride($configKey, "{$folder}/{$configKey}.php");
    }

    /**
     * @param array<int, string> $configKeys
     */
    public function loadPackageConfigs(array $configKeys, string $folder = 'config/packages'): static
    {
        foreach ($configKeys as $configKey) {
            $this->loadPackageConfig($configKey, $folder);
        }

        return $this;
    }

    /**
     * Read a config file's raw array from the base path's `config/` directory.
     *
     * Delegates to {@see ConfigFileResolver}, the package's one place for
     * resolving and reading a config file, but stays lenient where the resolver
     * throws: an absent file yields an empty array.
     *
     * @return array<string, mixed>
     */
    public function loadConfigFile(string $file): array
    {
        if ($this->basePath === '') {
            return [];
        }

        $file = Str::endsWith($file, '.php') ? Str::beforeLast($file, '.php') : $file;

        $resolver = new ConfigFileResolver($this->basePath);

        if (! $resolver->exists($file)) {
            return [];
        }

        return $resolver->load($file);
    }

    // --- Section operations -------------------------------------------------

    /**
     * @param array<string, mixed> $source
     */
    public function overrideSection(array $source, string $sectionKey, string $targetPath): static
    {
        $section = Arr::get($source, $sectionKey);

        if (! is_array($section)) {
            return $this;
        }

        foreach ($section as $key => $value) {
            $this->set("{$targetPath}.{$key}", $value);
        }

        $this->log('overrideSection', $targetPath, ['source' => $sectionKey]);

        return $this;
    }

    public function push(string $key, mixed $value): static
    {
        $existing = Arr::wrap($this->config->get($key, []));
        $existing[] = $value;

        $this->config->set($key, $existing);
        $this->log('push', $key, $value);

        return $this;
    }

    public function prepend(string $key, mixed $value): static
    {
        $existing = Arr::wrap($this->config->get($key, []));

        $this->config->set($key, Arr::prepend($existing, $value));
        $this->log('prepend', $key, $value);

        return $this;
    }

    // --- Conditional operations --------------------------------------------

    /**
     * @param bool|Closure(): bool $condition
     * @param Closure(static): void $callback
     */
    public function when(bool|Closure $condition, Closure $callback): static
    {
        if (value($condition)) {
            $callback($this);
        }

        return $this;
    }

    /**
     * @param bool|Closure(): bool $condition
     * @param Closure(static): void $callback
     */
    public function unless(bool|Closure $condition, Closure $callback): static
    {
        if (! value($condition)) {
            $callback($this);
        }

        return $this;
    }

    /**
     * @param string|array<int, string> $environments
     * @param Closure(static): void $callback
     */
    public function inEnvironment(string|array $environments, Closure $callback): static
    {
        if (in_array($this->app->environment(), Arr::wrap($environments), true)) {
            $callback($this);
        }

        return $this;
    }

    /**
     * @param Closure(static, mixed): void $callback
     */
    public function whenHas(string $key, Closure $callback): static
    {
        if ($this->config->has($key)) {
            $callback($this, $this->config->get($key));
        }

        return $this;
    }

    // --- Transform operations ----------------------------------------------

    /**
     * @param Closure(mixed): mixed $transformer
     */
    public function transform(string $key, Closure $transformer): static
    {
        $this->config->set($key, $transformer($this->config->get($key)));
        $this->log('transform', $key);

        return $this;
    }

    /**
     * @param Closure(mixed, array-key): mixed $callback
     */
    public function each(string $key, Closure $callback): static
    {
        $value = $this->config->get($key, []);

        if (! is_array($value)) {
            return $this;
        }

        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = $callback($v, $k);
        }

        $this->config->set($key, $result);
        $this->log('each', $key);

        return $this;
    }

    // --- Utilities ----------------------------------------------------------

    /**
     * @return array<int, array{operation: string, key: string, value?: mixed}>
     */
    public function getLog(): array
    {
        return $this->operationLog;
    }

    public function clearLog(): static
    {
        $this->operationLog = [];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config->all();
    }

    public function dump(string $key = ''): static
    {
        dump($key === '' ? $this->all() : $this->get($key));

        return $this;
    }

    public function dd(string $key = ''): never
    {
        dd($key === '' ? $this->all() : $this->get($key));
    }

    // --- Protected helpers --------------------------------------------------

    /**
     * Resolve a path as absolute, or relative to the configured base path.
     */
    protected function resolvePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || Str::isMatch('/^[A-Za-z]:/', $path)) {
            return $path;
        }

        return $this->basePath !== '' ? "{$this->basePath}/{$path}" : $path;
    }

    /**
     * Record an operation when logging is enabled. A genuine null value IS
     * logged; the value key is omitted only when no value was supplied.
     */
    protected function log(string $operation, string $key, mixed $value = self::NO_VALUE): void
    {
        if (! $this->logging) {
            return;
        }

        $entry = ['operation' => $operation, 'key' => $key];

        if ($value !== self::NO_VALUE) {
            $entry['value'] = $value;
        }

        $this->operationLog[] = $entry;
    }
}

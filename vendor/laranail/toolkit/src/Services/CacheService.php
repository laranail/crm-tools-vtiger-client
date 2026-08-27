<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Compilers\BladeCompiler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;
use Throwable;

/**
 * The toolkit's single cache entry point — one surface for both application
 * data caching and framework cache maintenance.
 *
 * **Data** (get/put/remember/many/increment/decrement + fluent {@see tags()}):
 * a resilient (log-and-fall-back), key-namespaced, optionally-tagged wrapper
 * over the framework store. Namespacing is applied consistently across reads,
 * writes and forgets, so a `put()`/`get()`/`forget()` round-trip always aligns
 * (the empty default namespace is an identity, so behaviour is unchanged unless
 * a namespace is configured).
 *
 * **Maintenance** (`clear…`/`cache…`/`optimize`): clears and rebuilds Laravel's own
 * caches (config, routes, compiled views, bootstrap, events, logs) plus
 * config-gated third-party dirs, dispatching {@see CacheEvents}
 * (clearing → cleared/failed) around every operation and never throwing —
 * failures are logged and, for the orchestrators, collected into a
 * {@see CacheOptimizationResult}.
 */
class CacheService implements CacheRepositoryInterface
{
    /**
     * Tag group applied by the fluent {@see tags()} helper (separate from the
     * constructor default tags, which feed only the {@see self::cache()} path).
     *
     * @var list<string>
     */
    protected array $tagGroup = [];

    private LoggerInterface $logger;

    /**
     * @param array<int, string> $defaultTags
     */
    public function __construct(
        protected int $defaultExpiration,
        protected array $defaultTags,
        ?LoggerInterface $logger = null,
        protected string $namespace = '',
        private ?Filesystem $files = null,
        private ?Application $app = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    // ---------------------------------------------------------------------
    // Application data cache
    // ---------------------------------------------------------------------

    /**
     * Cache data with configurable options (resilient: never throws).
     *
     * @param array<int, string>|null $tags
     */
    public function cache(string $key, mixed $data, ?int $minutes = null, ?array $tags = null): mixed
    {
        $minutes ??= $this->defaultExpiration;
        $tags ??= $this->defaultTags;

        $seconds = $minutes * 60;
        $namespacedKey = $this->namespacedKey($key);

        try {
            if (Cache::getStore() instanceof TaggableStore && !empty($tags)) {
                try {
                    Cache::tags($tags)->put($namespacedKey, $data, $seconds);
                } catch (\Exception) {
                    // Fallback to regular cache if tags fail.
                    Cache::put($namespacedKey, $data, $seconds);
                }
            } else {
                Cache::put($namespacedKey, $data, $seconds);
            }
        } catch (Throwable $e) {
            $this->logger->error('Cache write failed', ['key' => $key, 'error' => $e->getMessage()]);
        }

        return $data;
    }

    /**
     * Retrieve cached data. Namespace-aware and, when a fluent tag scope is
     * active, tag-aware — so it reads back what {@see put()}/{@see Cache()}
     * wrote under the same namespace/tags.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $namespacedKey = $this->namespacedKey($key);

        if ($this->tagGroup !== []) {
            return $this->store()->get($namespacedKey, $default);
        }

        return Cache::get($namespacedKey, $default);
    }

    /**
     * Forget cached data. Namespace-aware (and tag-aware under a fluent tag
     * scope) so it removes exactly what the matching write stored.
     */
    public function forget(string $key): void
    {
        $namespacedKey = $this->namespacedKey($key);

        if ($this->tagGroup !== []) {
            $this->store()->forget($namespacedKey);

            return;
        }

        Cache::forget($namespacedKey);
    }

    /**
     * Remember a value: return the cached entry or compute, store and return it.
     *
     * On any cache backend failure the callback result is returned directly so
     * callers never see an exception bubble out of a cache call.
     */
    public function remember(string $key, callable $callback, ?int $minutes = null): mixed
    {
        $minutes ??= $this->defaultExpiration;
        $namespacedKey = $this->namespacedKey($key);

        try {
            return $this->store()->remember($namespacedKey, $minutes * 60, Closure::fromCallable($callback));
        } catch (Throwable $e) {
            $this->logger->error('Cache remember failed', ['key' => $key, 'error' => $e->getMessage()]);

            return $callback();
        }
    }

    /**
     * Remember a value forever (until manually forgotten).
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        $namespacedKey = $this->namespacedKey($key);

        try {
            return $this->store()->rememberForever($namespacedKey, Closure::fromCallable($callback));
        } catch (Throwable $e) {
            $this->logger->error('Cache remember forever failed', ['key' => $key, 'error' => $e->getMessage()]);

            return $callback();
        }
    }

    /**
     * Store a value, returning whether the write succeeded.
     */
    public function put(string $key, mixed $value, ?int $minutes = null): bool
    {
        $minutes ??= $this->defaultExpiration;
        $namespacedKey = $this->namespacedKey($key);

        try {
            return $this->store()->put($namespacedKey, $value, $minutes * 60);
        } catch (Throwable $e) {
            $this->logger->error('Cache put failed', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get multiple values at once, keyed by their original (un-namespaced) keys.
     *
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function many(array $keys, mixed $default = null): array
    {
        try {
            $store = $this->store();

            $output = [];
            foreach ($keys as $key) {
                $output[$key] = $store->get($this->namespacedKey($key), $default);
            }

            return $output;
        } catch (Throwable $e) {
            $this->logger->error('Cache many failed', ['keys' => $keys, 'error' => $e->getMessage()]);

            return array_fill_keys($keys, $default);
        }
    }

    /**
     * Increment a numeric cache value, or false on failure.
     */
    public function increment(string $key, int $value = 1): int|false
    {
        $namespacedKey = $this->namespacedKey($key);

        try {
            $result = $this->store()->increment($namespacedKey, $value);

            return is_int($result) ? $result : false;
        } catch (Throwable $e) {
            $this->logger->error('Cache increment failed', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Decrement a numeric cache value, or false on failure.
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        $namespacedKey = $this->namespacedKey($key);

        try {
            $result = $this->store()->decrement($namespacedKey, $value);

            return is_int($result) ? $result : false;
        } catch (Throwable $e) {
            $this->logger->error('Cache decrement failed', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Return a clone scoped to the given tags for grouped invalidation.
     *
     * @param array<int, string> $tags
     */
    public function tags(array $tags): static
    {
        $clone = clone $this;
        $clone->tagGroup = array_values($tags);

        return $clone;
    }

    /**
     * A stable cache key derived from the current request's method, URL and
     * input — for request-scoped response caching. Returns a hash; pass it to
     * {@see put()}/{@see get()} (which apply namespacing).
     *
     * ## This key identifies a *request*, not a *requester*
     *
     * Nothing here distinguishes two users issuing the same request, so
     * caching a personalised response under it serves one user's response to
     * the next. That is the realistic leak, and it is the caller's to close:
     * pass whatever the response actually varies by via `$vary` — the
     * authenticated user id, the locale, the tenant.
     *
     * ```php
     * $key = $cache->keyFromRequest(['user' => $request->user()?->getAuthIdentifier()]);
     * ```
     *
     * ## Why the hash changed
     *
     * This was `md5(serialize($input) . '|' . $url)`. MD5 chosen-prefix
     * collisions are cheap, and request input is attacker-controlled and
     * length-prefixed by `serialize()`, so collision blocks survive into the
     * digest intact — two distinct requests can be made to share an entry. The
     * attack needs the victim's request to carry the other half of the pair,
     * which is a real constraint, but SHA-256 costs nothing here and removes
     * the question.
     *
     * The method was also part of the key by omission: a GET and a POST to one
     * URL with identical input hashed the same. That collision needed no
     * cryptography at all.
     *
     * `serialize()` stays — it is unambiguous over nested input and its output
     * is never unserialized — but the input is sorted first, so `?a=1&b=2` and
     * `?b=2&a=1` stop caching the same response twice.
     *
     * @param array<string, mixed> $vary extra dimensions the response varies by
     */
    public function keyFromRequest(array $vary = []): string
    {
        $request = request();

        // `input()` rather than `all()`: `all()` merges uploaded files, and an
        // UploadedFile in the digest is both unserialisable and keyed by a
        // per-request temp path. Laravel types it `mixed`, and a key-less call
        // does return the merged query+body array, but the narrowing is real
        // rather than a silenced cast.
        $input = $request->input();
        $input = is_array($input) ? $input : ['value' => $input];
        $this->sortRecursive($input);

        try {
            $url = (string) url()->current();
        } catch (Throwable) {
            $url = ToolkitConfig::string('app.url');
        }

        ksort($vary);

        return hash('sha256', serialize([
            'method' => $request->getMethod(),
            'url' => $url,
            'input' => $input,
            'vary' => $vary,
        ]));
    }

    /**
     * Sort an input tree by key so that argument order does not change the key.
     *
     * Lists are left alone — `?tags[]=a&tags[]=b` is not the same request as
     * `?tags[]=b&tags[]=a`, and sorting it would merge two responses.
     *
     * @param array<array-key, mixed> $input
     */
    private function sortRecursive(array &$input): void
    {
        foreach ($input as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }

        unset($value);

        if (!array_is_list($input)) {
            ksort($input);
        }
    }

    // ---------------------------------------------------------------------
    // Framework cache maintenance
    // ---------------------------------------------------------------------

    /** Flush the entire framework cache store. */
    public function clearFrameworkCache(): static
    {
        return $this->guard('framework', function (): void {
            if (Cache::flush() === false) {
                throw new \RuntimeException('Cache store flush returned false.');
            }
        });
    }

    /** Delete the cached config file (`config:clear`). */
    public function clearConfig(): static
    {
        return $this->guard('config', fn () => $this->deleteIfExists($this->application()->getCachedConfigPath()));
    }

    /** Delete the cached routes file (`route:clear`). */
    public function clearRoutes(): static
    {
        return $this->guard('routes', fn () => $this->deleteIfExists($this->application()->getCachedRoutesPath()));
    }

    /** Delete compiled Blade views (`view:clear`). */
    public function clearCompiledViews(): static
    {
        return $this->guard('views', fn () => $this->clearCompiledViewsNow());
    }

    /** Delete all PHP files in the bootstrap cache dir. */
    public function clearBootstrap(): static
    {
        return $this->guard('bootstrap', function (): void {
            foreach ($this->globFiles($this->application()->bootstrapPath('cache/*.php')) as $file) {
                $this->files()->delete($file);
            }
        });
    }

    /** Delete the cached events file (`event:clear`). */
    public function clearEvents(): static
    {
        return $this->guard('events', fn () => $this->deleteIfExists($this->application()->getCachedEventsPath()));
    }

    /** Delete all `*.log` files under the log directory. */
    public function clearLogs(): static
    {
        return $this->guard('logs', function (): void {
            $logPath = storage_path('logs');
            if (!$this->files()->isDirectory($logPath)) {
                return;
            }
            foreach ($this->globFiles($logPath . '/*.log') as $file) {
                $this->files()->delete($file);
            }
        });
    }

    /**
     * Clear a third-party cache directory/file named by a config key
     * (skips silently when the key is unset or the path is absent).
     */
    public function clearThirdPartyCache(string $configKey): static
    {
        return $this->guard('third_party:' . $configKey, fn () => $this->deleteThirdParty($configKey));
    }

    /** Clear the HTMLPurifier cache directory (config `purifier.cachePath`). */
    public function clearPurifier(): static
    {
        return $this->guard('purifier', fn () => $this->deleteThirdParty('purifier.cachePath'));
    }

    /** Clear the Debugbar storage directory (config `debugbar.storage.path`). */
    public function clearDebugbar(): static
    {
        return $this->guard('debugbar', fn () => $this->deleteThirdParty('debugbar.storage.path'));
    }

    /** Rebuild the cached config file (Closures are stripped before export). */
    public function cacheConfig(): static
    {
        return $this->guard('config_cache', fn () => $this->cacheConfigNow());
    }

    /** Recompile every Blade view under the configured view paths. */
    public function cacheViews(): static
    {
        return $this->guard('view_cache', fn () => $this->cacheViewsNow());
    }

    /**
     * Clear framework + third-party caches and logs in one pass (each step is
     * event-wrapped and resilient).
     */
    public function purgeAll(): static
    {
        return $this
            ->clearFrameworkCache()
            ->clearCompiledViews()
            ->clearBootstrap()
            ->clearRoutes()
            ->clearConfig()
            ->clearEvents()
            ->clearLogs()
            ->clearPurifier()
            ->clearDebugbar();
    }

    /**
     * Clear then rebuild config + views and flush the store, collecting a
     * per-step result. Dispatches an overall clearing → cleared/failed event.
     */
    public function optimize(): CacheOptimizationResult
    {
        return $this->orchestrate('optimize', [
            'config_cleared' => fn () => $this->deleteIfExists($this->application()->getCachedConfigPath()),
            'routes_cleared' => fn () => $this->deleteIfExists($this->application()->getCachedRoutesPath()),
            'views_cleared' => $this->clearCompiledViewsNow(...),
            'config_cached' => $this->cacheConfigNow(...),
            'views_compiled' => $this->cacheViewsNow(...),
            'framework_cache_cleared' => fn () => Cache::flush(),
        ]);
    }

    /**
     * Clear the config/routes/views optimization caches, collecting a per-step
     * result. Dispatches an overall clearing → cleared/failed event.
     */
    public function clearOptimization(): CacheOptimizationResult
    {
        return $this->orchestrate('clear_optimization', [
            'config_cleared' => fn () => $this->deleteIfExists($this->application()->getCachedConfigPath()),
            'routes_cleared' => fn () => $this->deleteIfExists($this->application()->getCachedRoutesPath()),
            'views_cleared' => $this->clearCompiledViewsNow(...),
        ]);
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Apply the configured namespace prefix to a key (identity when unset).
     */
    protected function namespacedKey(string $key): string
    {
        return $this->namespace !== '' ? $this->namespace . ':' . $key : $key;
    }

    /**
     * Resolve the cache repository, applying the fluent tag scope when the store
     * is taggable and tags are present.
     */
    protected function store(): Repository
    {
        if ($this->tagGroup !== [] && Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($this->tagGroup);
        }

        return Cache::store();
    }

    /**
     * Wrap a maintenance operation in the clearing → cleared/failed lifecycle,
     * logging and swallowing any failure so the surface never throws.
     */
    private function guard(string $operation, callable $op): static
    {
        event(CacheEvents::clearing(['operation' => $operation]));

        try {
            $op();
            event(CacheEvents::cleared(['operation' => $operation]));
        } catch (Throwable $e) {
            $this->logger->error('Cache maintenance failed', ['operation' => $operation, 'error' => $e->getMessage()]);
            event(CacheEvents::failed($e->getMessage(), ['operation' => $operation]));
        }

        return $this;
    }

    /**
     * Run an ordered map of label → step, recording completions and errors into
     * a {@see CacheOptimizationResult} and firing one overall lifecycle event.
     *
     * @param array<string, callable():mixed> $steps
     */
    private function orchestrate(string $operation, array $steps): CacheOptimizationResult
    {
        event(CacheEvents::clearing(['operation' => $operation]));

        $completed = [];
        $errors = [];

        foreach ($steps as $label => $step) {
            try {
                $step();
                $completed[] = $label;
            } catch (Throwable $e) {
                $errors[$label] = $e->getMessage();
                $this->logger->error('Cache optimization step failed', ['operation' => $operation, 'step' => $label, 'error' => $e->getMessage()]);
            }
        }

        $result = new CacheOptimizationResult($errors === [], $completed, $errors);

        event($result->successful()
            ? CacheEvents::cleared(['operation' => $operation, 'steps' => $completed])
            : CacheEvents::failed($operation . ' incomplete', ['operation' => $operation, 'errors' => $errors]));

        return $result;
    }

    /** Delete a path if it exists (no-op otherwise). */
    private function deleteIfExists(string $path): void
    {
        if ($this->files()->exists($path)) {
            $this->files()->delete($path);
        }
    }

    /**
     * Clear a third-party cache path read from a config key.
     *
     * The config key is caller-supplied and `clearThirdPartyCache()` is public,
     * so this method previously handed *any* configured path to a recursive
     * delete: `filesystems.disks.local.root`, `view.compiled`, or a
     * mistyped key pointing anywhere at all. Nothing checked containment, and
     * nothing checked for a symlink.
     *
     * Every legitimate caller — `purifier.cachePath`, `debugbar.storage.path` —
     * names a directory inside `storage/`, so that is the boundary. A path
     * outside it is refused rather than cleared, because a cache directory the
     * application does not own is not this method's to empty.
     */
    private function deleteThirdParty(string $configKey): void
    {
        $path = ToolkitConfig::string($configKey);
        if ($path === '') {
            return;
        }

        if (!$this->isClearableCachePath($path)) {
            $this->logger->warning('Refused to clear a third-party cache path outside storage/.', [
                'config_key' => $configKey,
                'path' => $path,
            ]);

            return;
        }

        if ($this->files()->isDirectory($path)) {
            $this->files()->deleteDirectory($path, preserve: true);

            return;
        }

        $this->deleteIfExists($path);
    }

    /**
     * Whether a configured cache path may be emptied.
     *
     * Resolved with `realpath()` and compared with a trailing separator, so
     * `storage2/` does not pass as being inside `storage/`. A symlink is
     * refused outright: following one would empty a directory the guard never
     * approved, and no legitimate cache path needs to be one.
     */
    private function isClearableCachePath(string $path): bool
    {
        if (str_contains($path, "\0") || is_link($path)) {
            return false;
        }

        $real = realpath($path);
        $root = realpath(storage_path());

        if ($real === false || $root === false) {
            return false;
        }

        // The root itself is never clearable — only things under it.
        return $real !== $root && str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    /** The compiled-view clear body, reused by {@see cacheViews()}/orchestrators. */
    private function clearCompiledViewsNow(): void
    {
        $compiled = ToolkitConfig::string('view.compiled');
        if ($compiled === '') {
            return;
        }
        foreach ($this->globFiles($compiled . '/*.php') as $view) {
            $this->files()->delete($view);
        }
    }

    /**
     * The config-rebuild body, reused by the optimize orchestrator.
     *
     * Caches the live (runtime) config with top-level Closures stripped. To
     * avoid ever leaving an unloadable cache that would brick the next boot (a
     * config value that is an object without `__set_state` exports to invalid
     * PHP), it writes to a fresh temp file, requires it to prove it loads, and
     * only then swaps it into place — rolling back and throwing otherwise.
     */
    private function cacheConfigNow(): void
    {
        $path = $this->application()->getCachedConfigPath();

        /** @var array<string, mixed> $config */
        $config = $this->application()->make('config')->all();
        array_walk_recursive($config, static function (mixed &$value): void {
            if ($value instanceof Closure) {
                $value = null;
            }
        });

        $temp = $path . '.' . uniqid('tmp', true);
        $this->files()->put($temp, '<?php return ' . var_export($config, true) . ';' . PHP_EOL);

        try {
            $loaded = require $temp;
            if (!is_array($loaded)) {
                throw new \RuntimeException('Cached config did not evaluate to an array.');
            }
        } catch (Throwable $e) {
            $this->deleteIfExists($temp);

            throw new \RuntimeException('Configuration is not cacheable: ' . $e->getMessage(), 0, $e);
        }

        $this->deleteIfExists($path);
        $this->files()->move($temp, $path);
    }

    /** The view-compile body, reused by the optimize orchestrator. */
    private function cacheViewsNow(): void
    {
        $this->clearCompiledViewsNow();

        $compiler = $this->application()->make(BladeCompiler::class);
        foreach (ToolkitConfig::array('view.paths') as $path) {
            if (!is_string($path) || !$this->files()->isDirectory($path)) {
                continue;
            }
            foreach ($this->files()->allFiles($path) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $compiler->compile($file->getRealPath());
                }
            }
        }
    }

    /**
     * Glob a pattern, always returning a list (never `false`).
     *
     * @return list<string>
     */
    private function globFiles(string $pattern): array
    {
        $matches = $this->files()->glob($pattern);

        return is_array($matches) ? array_values(array_filter($matches, is_string(...))) : [];
    }

    private function files(): Filesystem
    {
        return $this->files ??= app(Filesystem::class);
    }

    private function application(): Application
    {
        return $this->app ??= app();
    }
}

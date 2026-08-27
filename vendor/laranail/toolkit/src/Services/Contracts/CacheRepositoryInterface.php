<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;
use Simtabi\Laranail\Toolkit\Services\CacheOptimizationResult;
use Simtabi\Laranail\Toolkit\Services\CacheService;

/**
 * Public surface of the toolkit's {@see CacheService}.
 *
 * Named to avoid colliding with Illuminate's
 * `Illuminate\Contracts\Cache\Repository`: this is the toolkit's own resilient
 * (log-and-fall-back), namespaced, optionally-tagged cache helper, not a drop-in
 * for the framework repository. Bound interface→{@see CacheService}.
 *
 * Two cohesive halves: the application data cache (get/put/remember/…) and the
 * framework cache maintenance surface (`clear…`/`cache…`/`optimize`), the latter
 * dispatching {@see CacheEvents}
 * around every operation.
 */
interface CacheRepositoryInterface
{
    /**
     * Cache data with configurable options.
     *
     * @param array<int, string>|null $tags
     */
    public function cache(string $key, mixed $data, ?int $minutes = null, ?array $tags = null): mixed;

    /** Retrieve cached data (facade-direct, no namespacing). */
    public function get(string $key, mixed $default = null): mixed;

    /** Forget cached data (facade-direct, no namespacing). */
    public function forget(string $key): void;

    /**
     * Remember a value: return the cached entry or compute, store and return it.
     */
    public function remember(string $key, callable $callback, ?int $minutes = null): mixed;

    /** Remember a value forever (until manually forgotten). */
    public function rememberForever(string $key, callable $callback): mixed;

    /** Store a value, returning whether the write succeeded. */
    public function put(string $key, mixed $value, ?int $minutes = null): bool;

    /**
     * Get multiple values at once, keyed by their original (un-namespaced) keys.
     *
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function many(array $keys, mixed $default = null): array;

    /** Increment a numeric cache value, or false on failure. */
    public function increment(string $key, int $value = 1): int|false;

    /** Decrement a numeric cache value, or false on failure. */
    public function decrement(string $key, int $value = 1): int|false;

    /**
     * Return a clone scoped to the given tags for grouped invalidation.
     *
     * @param array<int, string> $tags
     */
    public function tags(array $tags): static;

    /**
     * A stable cache key derived from the current request's method, URL and
     * input (for request-scoped response caching).
     *
     * The key identifies a request, not a requester — pass whatever the
     * response varies by (user, locale, tenant) via `$vary`, or a personalised
     * response cached under it will be served to the next caller.
     *
     * @param array<string, mixed> $vary extra dimensions the response varies by
     */
    public function keyFromRequest(array $vary = []): string;

    // --- Framework cache maintenance (resilient; dispatches CacheEvents) ---

    /** Flush the entire framework cache store. */
    public function clearFrameworkCache(): static;

    /** Delete the cached config file. */
    public function clearConfig(): static;

    /** Delete the cached routes file. */
    public function clearRoutes(): static;

    /** Delete compiled Blade views. */
    public function clearCompiledViews(): static;

    /** Delete all PHP files in the bootstrap cache dir. */
    public function clearBootstrap(): static;

    /** Delete the cached events file. */
    public function clearEvents(): static;

    /** Delete all `*.log` files under the log directory. */
    public function clearLogs(): static;

    /**
     * Clear a third-party cache directory/file named by a config key
     * (skips silently when unset/absent).
     */
    public function clearThirdPartyCache(string $configKey): static;

    /** Clear the HTMLPurifier cache directory. */
    public function clearPurifier(): static;

    /** Clear the Debugbar storage directory. */
    public function clearDebugbar(): static;

    /** Rebuild the cached config file (Closures stripped). */
    public function cacheConfig(): static;

    /** Recompile every Blade view under the configured view paths. */
    public function cacheViews(): static;

    /** Clear framework + third-party caches and logs in one pass. */
    public function purgeAll(): static;

    /** Clear then rebuild config + views and flush the store. */
    public function optimize(): CacheOptimizationResult;

    /** Clear the config/routes/views optimization caches. */
    public function clearOptimization(): CacheOptimizationResult;
}

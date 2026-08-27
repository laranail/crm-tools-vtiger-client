<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Config;

use Illuminate\Contracts\Config\Repository;
use ReflectionException;
use ReflectionProperty;

/**
 * Writes a pruned item set back over a config repository.
 *
 * Removing a config key is not expressible through the framework contract.
 * `Repository::set()` only ever adds or overwrites, so re-seeding a pruned copy
 * of `all()` leaves a removed top-level key exactly where it was, and
 * `offsetUnset()` is literally `set($key, null)`, which leaves `has()` reporting
 * true. The pruned array therefore has to replace the repository's own store.
 *
 * Kept in one place so {@see ConfigManager::remove()} and
 * {@see ConfigService::forget()} cannot drift into disagreeing about what
 * "forget" means.
 */
final class ConfigItemStore
{
    /**
     * Replace every item in the repository.
     *
     * Returns false when the repository does not expose a writable `items`
     * property — a custom `Repository` implementation with a different shape —
     * which is the caller's signal to degrade to nulling the key.
     *
     * @param array<string, mixed> $items
     */
    public static function replace(Repository $config, array $items): bool
    {
        try {
            $property = new ReflectionProperty($config, 'items');
        } catch (ReflectionException) {
            return false;
        }

        if ($property->isStatic() || $property->isReadOnly()) {
            return false;
        }

        $property->setValue($config, $items);

        return true;
    }

    /**
     * Remove a key, falling back to nulling it when the store is not writable.
     *
     * @param array<string, mixed> $pruned every item except the removed key
     */
    public static function forget(Repository $config, string $key, array $pruned): void
    {
        if (self::replace($config, $pruned)) {
            return;
        }

        // Degraded: get() will miss, has() will not.
        $config->set($pruned);
        $config->set($key);
    }
}

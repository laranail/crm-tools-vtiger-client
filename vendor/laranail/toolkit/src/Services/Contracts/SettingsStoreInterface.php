<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Simtabi\Laranail\Toolkit\Services\SettingsStore;

/**
 * Public surface of the toolkit's {@see SettingsStore}.
 *
 * A *dynamic* runtime settings store (values that change at runtime and persist
 * across requests via a JSON file on a configured disk) — deliberately separate
 * from Laravel's static, deploy-time `config()`. Keys use dot notation. Bound
 * interface→{@see SettingsStore}.
 */
interface SettingsStoreInterface
{
    /**
     * All persisted settings.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Get a setting by dot-notation key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Whether a setting exists for the given dot-notation key.
     */
    public function has(string $key): bool;

    /**
     * Set (or overwrite) a setting by dot-notation key and persist.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Remove a setting by dot-notation key and persist.
     */
    public function forget(string $key): void;
}

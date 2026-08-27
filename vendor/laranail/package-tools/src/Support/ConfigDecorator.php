<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use Closure;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigMerger;

/**
 * Fluent wrapper over Laravel's config repository, handed to the closure a
 * package registers via `$package->configDecorator(fn (ConfigDecorator $c) => …)`.
 * Runs at boot (failure-safe), so it may read runtime data (settings, the
 * DB) that isn't available during the register phase.
 */
final class ConfigDecorator
{
    public function set(string $key, mixed $value): self
    {
        config()->set($key, $value);

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return config($key, $default);
    }

    public function has(string $key): bool
    {
        return config()->has($key);
    }

    /**
     * Alias of {@see self::has()}.
     */
    public function exists(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Alias of {@see self::has()}.
     */
    public function check(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Unset a config key (Laravel's config repository has no hard delete, so
     * this nulls the value).
     */
    public function forget(string $key): self
    {
        config()->set($key);

        return $this;
    }

    /**
     * Alias of {@see self::forget()}.
     */
    public function remove(string $key): self
    {
        return $this->forget($key);
    }

    /**
     * Alias of {@see self::forget()}.
     */
    public function delete(string $key): self
    {
        return $this->forget($key);
    }

    /**
     * Deep-merge `$values` into the config at `$key` (the given values win
     * over what is already there).
     *
     * @param array<int|string, mixed> $values
     */
    public function merge(string $key, array $values): self
    {
        $existing = config($key, []);

        config()->set($key, (new ConfigMerger)->deepMerge(
            is_array($existing) ? $existing : [],
            $values,
        ));

        return $this;
    }

    /**
     * Run `$callback($this)` only when `$condition` is truthy. The callback's
     * return value is ignored, so the natural chained form
     * `fn ($c) => $c->set(...)` (where `set()` returns the decorator) is fine.
     *
     * @param Closure(self): mixed $callback
     */
    public function when(bool $condition, Closure $callback): self
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Validate current config values against `$rules` (dot-notation keys),
     * throwing a ValidationException on failure.
     *
     * @param array<string, mixed> $rules
     */
    public function validate(array $rules): self
    {
        Validator::make(config()->all(), $rules)->validate();

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Database\Seeder;

/**
 * In-memory store of per-package seeder bundles, keyed by an opaque label
 * (typically the consumer package's name). Entries are typed SeederBundle
 * value objects; execution order across bundles follows priority (lower
 * first), then registration order.
 */
final class SeederRegistry
{
    /** @var array<string, SeederBundle> */
    private array $bundles = [];

    /**
     * Register (or replace) the bundle for `$key`. The array form keeps
     * the historical signature; pass a SeederBundle for typed options.
     *
     * @param list<class-string<Seeder>> $seeders
     * @param array<string, mixed> $options legacy string-keyed options
     */
    public function register(string $key, array $seeders, ?string $namespace = null, array $options = []): self
    {
        return $this->registerBundle(SeederBundle::fromOptions($key, $seeders, $namespace, $options));
    }

    public function registerBundle(SeederBundle $bundle): self
    {
        // Replacement wins (last registration is authoritative) but is never
        // silent — two packages colliding on a key is almost always a bug.
        if (isset($this->bundles[$bundle->key()])) {
            trigger_error(
                "Package seeder bundle '{$bundle->key()}' was replaced by a later registration; " .
                'use distinct keys per package to seed both.',
                E_USER_WARNING,
            );
        }

        $this->bundles[$bundle->key()] = $bundle;

        return $this;
    }

    public function forget(string $key): self
    {
        unset($this->bundles[$key]);

        return $this;
    }

    public function clear(): self
    {
        $this->bundles = [];

        return $this;
    }

    public function get(string $key): ?SeederBundle
    {
        return $this->bundles[$key] ?? null;
    }

    /**
     * @return array<string, SeederBundle>
     */
    public function all(): array
    {
        return $this->bundles;
    }

    public function isEmpty(): bool
    {
        return $this->bundles === [];
    }

    public function count(): int
    {
        return count($this->bundles);
    }
}

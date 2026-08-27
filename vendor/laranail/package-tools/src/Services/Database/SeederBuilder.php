<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;

/**
 * Fluent builder for configuring and running seeders.
 *
 * Standalone counterpart to the `Package` builder's
 * `hasPackageSeeders()` / `discoverPackageSeedersIn()` integration — use
 * this when you want to discover/filter/execute seeders imperatively.
 *
 * ```php
 * $stats = app(SeederBuilder::class)
 *     ->from(database_path('seeders'))
 *     ->only(['UserSeeder', 'RoleSeeder'])
 *     ->execute();
 * ```
 */
final class SeederBuilder
{
    /** @var list<string> */
    private array $paths = [];

    /** @var list<class-string<Seeder>> */
    private array $classes = [];

    /** @var list<string> */
    private array $only = [];

    /** @var list<string> */
    private array $except = [];

    private ?string $namespace = null;

    private bool $disableForeignKeyChecks = true;

    private bool $fireEvents = false;

    public function __construct(
        private readonly SeederRegistry $registry,
        private readonly SeederExecutor $executor,
        private readonly SeederPathDiscoverer $discoverer,
    ) {}

    /**
     * @param string|list<string> $paths
     */
    public function from(string|array $paths): self
    {
        $this->paths = array_merge($this->paths, array_values((array) $paths));

        return $this;
    }

    /**
     * @param list<class-string<Seeder>> $classes
     */
    public function classes(array $classes): self
    {
        $this->classes = array_merge($this->classes, array_values($classes));

        return $this;
    }

    /**
     * Restrict to seeders whose FQCN or short name matches one of `$names`.
     *
     * @param list<string> $names
     */
    public function only(array $names): self
    {
        $this->only = array_values($names);

        return $this;
    }

    /**
     * Exclude seeders whose FQCN or short name matches one of `$names`.
     *
     * @param list<string> $names
     */
    public function except(array $names): self
    {
        $this->except = array_values($names);

        return $this;
    }

    public function namespace(?string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function withoutForeignKeyChecks(bool $without = true): self
    {
        $this->disableForeignKeyChecks = $without;

        return $this;
    }

    public function withForeignKeyChecks(): self
    {
        $this->disableForeignKeyChecks = false;

        return $this;
    }

    public function fireEvents(bool $fire = true): self
    {
        $this->fireEvents = $fire;

        return $this;
    }

    /**
     * Resolve the final, filtered, de-duplicated list of seeder classes.
     *
     * @return list<class-string<Seeder>>
     */
    public function discover(): array
    {
        $resolved = $this->classes;

        foreach ($this->paths as $path) {
            $resolved = array_merge($resolved, $this->discoverer->discover($path));
        }

        $resolved = array_values(array_unique($resolved));

        return array_values(array_filter($resolved, $this->passesFilters(...)));
    }

    /**
     * Register the resolved seeders into the shared registry (so they run
     * when the host `DatabaseSeeder` resolves). Does not execute.
     */
    public function register(?string $key = null): self
    {
        $seeders = $this->discover();

        if ($seeders !== []) {
            $this->registry->register(
                $key ?? $this->namespace ?? 'builder:' . md5(implode('|', $seeders)),
                $seeders,
                $this->namespace,
                $this->options(),
            );
        }

        return $this;
    }

    /**
     * Resolve, then execute immediately, returning typed stats.
     */
    public function execute(): SeederExecutionStats
    {
        $seeders = $this->discover();

        if ($seeders === []) {
            return SeederExecutionStats::empty($this->namespace);
        }

        $registry = (new SeederRegistry)->register(
            $this->namespace ?? 'builder',
            $seeders,
            $this->namespace,
            $this->options(),
        );

        return $this->executor->run($registry);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'disable_foreign_key_checks' => $this->disableForeignKeyChecks,
            'fire_events' => $this->fireEvents,
        ];
    }

    private function passesFilters(string $class): bool
    {
        $short = class_basename($class);

        if ($this->only !== [] && ! $this->matches($class, $short, $this->only)) {
            return false;
        }

        if ($this->except !== [] && $this->matches($class, $short, $this->except)) {
            return false;
        }

        return true;
    }

    /**
     * @param list<string> $names
     */
    private function matches(string $class, string $short, array $names): bool
    {
        foreach ($names as $name) {
            $name = Str::of($name)->replaceLast('.php', '')->toString();
            if ($name === $class || $name === $short) {
                return true;
            }
        }

        return false;
    }
}

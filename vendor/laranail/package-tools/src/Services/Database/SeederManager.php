<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Console\OutputStyle;
use Illuminate\Database\Seeder;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;

/**
 * Standalone seeding entry point — lets any consumer register and run
 * package seeders WITHOUT using the `Package` builder. Resolved from the
 * container (or via the `PackageSeeder` facade).
 *
 * ```php
 * PackageSeeder::autoSeed('Acme\\Blog', [BlogSeeder::class]); // runs on db:seed
 * $stats = PackageSeeder::seeders()->from($path)->execute();  // run now
 * ```
 *
 * The db:seed resolver hook is attached once by PackageToolsServiceProvider
 * (not lazily here), so bundles registered at any point in the process —
 * before or after the first resolution — are all picked up.
 */
final readonly class SeederManager
{
    public function __construct(
        private SeederRegistry $registry,
        private SeederExecutor $executor,
        private SeederPathDiscoverer $discoverer,
        private SeederAutorun $autorun,
    ) {}

    /**
     * Register a bundle of seeders to run with the host app's
     * `php artisan db:seed` (root-seeder resolution).
     *
     * @param list<class-string<Seeder>> $seeders
     * @param array<string, mixed> $options
     */
    public function autoSeed(string $key, array $seeders, ?string $namespace = null, array $options = []): self
    {
        $this->registry->register($key, $seeders, $namespace, $options);

        return $this;
    }

    /**
     * Get a fresh fluent builder bound to the shared registry/executor.
     */
    public function seeders(): SeederBuilder
    {
        return new SeederBuilder($this->registry, $this->executor, $this->discoverer);
    }

    /**
     * Execute everything currently registered, marking every bundle in the
     * per-process ledger so a later db:seed doesn't run them again.
     */
    public function run(): SeederExecutionStats
    {
        $keys = array_keys($this->registry->all());

        if ($keys !== []) {
            $this->autorun->markExecuted(...$keys);
        }

        return $this->executor->run($this->registry);
    }

    /**
     * Execute the autorun-flagged bundles that have not yet run this
     * process (the same path the post-migration listener uses).
     */
    public function runAutorun(?OutputStyle $output = null): SeederExecutionStats
    {
        return $this->autorun->runPending($output);
    }

    /**
     * The shared run-state coordinator (per-process executed-key ledger).
     */
    public function autorunState(): SeederAutorun
    {
        return $this->autorun;
    }

    /**
     * Clear the per-process executed-key ledger — multi-tenant seeding
     * loops and tests that intentionally re-run bundles.
     */
    public function resetRunState(): void
    {
        $this->autorun->reset();
    }

    public function registry(): SeederRegistry
    {
        return $this->registry;
    }
}

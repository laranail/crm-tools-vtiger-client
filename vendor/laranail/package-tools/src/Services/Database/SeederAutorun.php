<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationsEnded;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

/**
 * Coordinates opt-in post-migration seeding and owns the per-process
 * "already executed" ledger shared by every trigger (the migration
 * listener, the db:seed resolver hook, and manual runs) so a bundle never
 * seeds twice in one process.
 *
 * Autorun NEVER fires unless a bundle opted in via
 * `AutoSeederDefinition::autorunAfterMigrations()` (or `autorunNow()`),
 * and even then only after every safety gate passes:
 *
 *  1. global kill-switch  `package-tools.seeders.autorun.enabled`
 *  2. console-only        (never on web requests / queue workers)
 *  3. unit tests          skipped unless `...autorun.in_tests` is true
 *  4. production          skipped unless `...autorun.in_production` is true,
 *                         UNLESS the bundle declared an explicit environment
 *                         list (which replaces this gate for that bundle)
 *  5. per-process ledger  each bundle key runs at most once per process
 *
 * A failing autorun bundle never aborts the migrate command — failures are
 * reported to the console and the log, then swallowed.
 */
final class SeederAutorun
{
    /** @var array<string, true> */
    private array $executedKeys = [];

    public function __construct(
        private readonly Application $app,
        private readonly SeederRegistry $registry,
        private readonly SeederExecutor $executor,
    ) {}

    /**
     * Listener for `Illuminate\Database\Events\MigrationsEnded` — fires once
     * per Migrator batch, including nested `$command->call('migrate')`.
     */
    public function handleMigrationsEnded(MigrationsEnded $event): void
    {
        if ($event->method !== 'up') {
            return;
        }

        if (! empty($event->options['pretend'])) {
            return;
        }

        if (! $this->passesGlobalGates()) {
            return;
        }

        try {
            $this->runPending();
        } catch (Throwable $e) {
            // Autorun must never break `php artisan migrate`.
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /**
     * Execute every autorun-flagged bundle that has not yet run in this
     * process and passes its environment gate. Returns merged stats.
     */
    public function runPending(?OutputStyle $output = null): SeederExecutionStats
    {
        $pending = [];

        foreach ($this->registry->all() as $key => $bundle) {
            if (! $bundle->isAutorun()) {
                continue;
            }
            if ($this->hasExecuted($key)) {
                continue;
            }
            if (! $this->passesEnvironmentGate($bundle)) {
                continue;
            }

            $pending[$key] = $bundle;
        }

        if ($pending === []) {
            return SeederExecutionStats::empty();
        }

        $scoped = new SeederRegistry;
        foreach ($pending as $bundle) {
            $scoped->registerBundle($bundle);
        }

        $this->markExecuted(...array_keys($pending));

        $formatter = $this->consoleFormatter($output);
        $formatter?->writeInfo(sprintf(
            'Running %d package seeder bundle%s after migrations…',
            count($pending),
            count($pending) === 1 ? '' : 's',
        ));

        try {
            $stats = $this->executor->run($scoped);
        } catch (Throwable $e) {
            $formatter?->writeError("Package autorun seeding failed: {$e->getMessage()}");

            // Per-seeder failures already surface through the executor; this
            // catch is the batch-level safety net (an executor-level throw)
            // that previously vanished silently.
            $this->app->make(PackageActionReporter::class)->seederFailed(
                'autorun',
                null,
                $e,
                context: ['bundles' => array_keys($pending), 'trigger' => 'autorun'],
                mode: SeederExecutionMode::Inline,
            );

            return SeederExecutionStats::empty();
        }

        if ($stats->hasFailures()) {
            $formatter?->writeWarning($stats->getSummary());
        } elseif (! $stats->isEmpty()) {
            $formatter?->writeSuccess($stats->getSummary());
        }

        return $stats;
    }

    public function markExecuted(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->executedKeys[$key] = true;
        }
    }

    public function hasExecuted(string $key): bool
    {
        return isset($this->executedKeys[$key]);
    }

    /**
     * @return list<string>
     */
    public function executedKeys(): array
    {
        return array_keys($this->executedKeys);
    }

    /**
     * Clear the per-process ledger (multi-tenant seeding loops, tests).
     */
    public function reset(): void
    {
        $this->executedKeys = [];
    }

    private function passesGlobalGates(): bool
    {
        if (! config('laranail.package-tools.seeders.autorun.enabled', true)) {
            return false;
        }

        if (! $this->app->runningInConsole()) {
            return false;
        }
        if (! $this->app->runningUnitTests()) {
            return true;
        }

        return (bool) config('laranail.package-tools.seeders.autorun.in_tests', false);
    }

    /**
     * An explicit per-bundle environment list replaces the production
     * default gate; without one, production requires the config opt-in.
     */
    private function passesEnvironmentGate(SeederBundle $bundle): bool
    {
        $environments = $bundle->autorunEnvironments();

        if ($environments !== []) {
            return $this->app->environment($environments);
        }

        if ($this->app->environment('production') && ! config('laranail.package-tools.seeders.autorun.in_production', false)) {
            $this->consoleFormatter(null)?->writeWarning(sprintf(
                "Skipping autorun seeders for '%s' in production (enable via package-tools.seeders.autorun.in_production).",
                $bundle->key(),
            ));

            return false;
        }

        return true;
    }

    private function consoleFormatter(?OutputStyle $output): ?SeederConsoleFormatterInterface
    {
        if (! $this->app->runningInConsole()) {
            return null;
        }

        try {
            $formatter = $this->app->make(SeederConsoleFormatterInterface::class);
            $formatter->setOutput($output ?? new OutputStyle(new ArrayInput([]), new ConsoleOutput));

            return $formatter;
        } catch (Throwable) {
            return null;
        }
    }
}

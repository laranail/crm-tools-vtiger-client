<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Illuminate\Contracts\Bus\Dispatcher;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Enums\SeederRunStatus;
use Simtabi\Laranail\Package\Tools\Jobs\RunSeederBundleJob;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederBundle;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRunTracker;

/**
 * `php artisan laranail::package-tools.seed` — the explicit trigger for
 * registered package seeder bundles, and what the scheduler invokes for
 * bundles with a cadence.
 *
 * Execution mode comes from each bundle's own definition
 * (runsInBackground() dispatches a job; otherwise inline); --sync and
 * --queued are manual overrides. --status renders the run tracker.
 */
final class PackageSeedCommand extends Command
{
    protected $signature = 'laranail::package-tools.seed
        {--key=* : Run only these bundle keys}
        {--package= : Run only bundles whose namespace matches}
        {--sync : Force inline execution for every selected bundle}
        {--queued : Force queued execution for every selected bundle}
        {--scheduled : Set by the scheduler; marks runs as scheduled provenance}
        {--force : Allow execution in production}
        {--status : Show tracked run status instead of executing}';

    protected $description = 'Run (or inspect) the registered package seeder bundles (laranail/package-tools).';

    public function handle(
        SeederRegistry $registry,
        SeederExecutor $executor,
        SeederAutorun $autorun,
        SeederRunTracker $tracker,
        Dispatcher $bus,
    ): int {
        $bundles = $this->selectBundles($registry);

        if ($this->option('status')) {
            return $this->renderStatus($bundles, $tracker);
        }

        if ($bundles === []) {
            $this->warn('No matching package seeder bundles are registered.');

            return self::SUCCESS;
        }

        if ($this->getLaravel()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to seed in production without --force.');

            return self::FAILURE;
        }

        $failedSeeders = 0;

        foreach ($bundles as $key => $bundle) {
            if ($this->resolveQueued($bundle)) {
                $mode = $this->option('scheduled') ? SeederExecutionMode::Scheduled : SeederExecutionMode::Queued;
                $job = new RunSeederBundleJob($key, $mode);

                // Bundle-level queue settings override the config defaults
                // the job constructor applied.
                if ($bundle->queue() !== null) {
                    $job->onQueue($bundle->queue());
                }
                if ($bundle->connection() !== null) {
                    $job->onConnection($bundle->connection());
                }

                $bus->dispatch($job);
                $this->info("Dispatched '{$key}' to the queue.");

                continue;
            }

            $scoped = new SeederRegistry;
            $scoped->registerBundle($bundle);
            $autorun->markExecuted($key);

            $stats = $executor->run($scoped, $this->provenance());
            $failedSeeders += $stats->failed;
            $this->line($stats->getSummary());
        }

        return $failedSeeders > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, SeederBundle>
     */
    private function selectBundles(SeederRegistry $registry): array
    {
        $keys = array_filter((array) $this->option('key'));
        $namespace = $this->option('package');

        $selected = [];
        foreach ($registry->all() as $key => $bundle) {
            if ($keys !== [] && ! in_array($key, $keys, true)) {
                continue;
            }
            if (is_string($namespace) && $namespace !== '' && $bundle->namespace() !== $namespace) {
                continue;
            }

            $selected[$key] = $bundle;
        }

        return $selected;
    }

    private function resolveQueued(SeederBundle $bundle): bool
    {
        if ($this->option('sync')) {
            return false;
        }

        if ($this->option('queued')) {
            return true;
        }

        return $bundle->isBackground();
    }

    private function provenance(): SeederExecutionMode
    {
        return $this->option('scheduled')
            ? SeederExecutionMode::Scheduled
            : SeederExecutionMode::Inline;
    }

    /**
     * @param array<string, SeederBundle> $bundles
     */
    private function renderStatus(array $bundles, SeederRunTracker $tracker): int
    {
        $rows = [];
        foreach (array_keys($bundles) as $key) {
            $state = $tracker->get($key);

            $rows[] = $state === null
                ? [$key, SeederRunStatus::Pending->value, '-', '-', '-']
                : [
                    $key,
                    $state['status']->value,
                    "{$state['processed']}/{$state['total']}" . ($state['failed'] > 0 ? " ({$state['failed']} failed)" : ''),
                    $state['started_at'] ?? '-',
                    $state['finished_at'] ?? ($state['message'] ?? '-'),
                ];
        }

        if ($rows === []) {
            $this->warn('No matching package seeder bundles are registered.');

            return self::SUCCESS;
        }

        $this->table(['Bundle', 'Status', 'Progress', 'Started', 'Finished / note'], $rows);

        return self::SUCCESS;
    }
}

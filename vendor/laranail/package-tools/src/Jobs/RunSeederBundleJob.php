<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederBundle;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;
use Simtabi\Laranail\Package\Tools\Support\RuntimeConfigurator;
use Throwable;

/**
 * Executes ONE seeder bundle on a queue worker. The payload carries only
 * the bundle KEY (definitions can hold closures, which don't serialize) —
 * the worker's own provider boot re-registers every bundle, and handle()
 * re-resolves it from the shared registry. A key that no longer resolves
 * warns and no-ops instead of crashing the worker.
 */
final class RunSeederBundleJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly string $bundleKey,
        public readonly SeederExecutionMode $mode = SeederExecutionMode::Queued,
    ) {
        $this->tries = (int) config('laranail.package-tools.seeders.queue.tries', 1);
        $this->timeout = (int) config('laranail.package-tools.seeders.queue.timeout', 300);

        $queue = config('laranail.package-tools.seeders.queue.name');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }

        $connection = config('laranail.package-tools.seeders.queue.connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }
    }

    public function handle(SeederRegistry $registry, SeederExecutor $executor, SeederAutorun $autorun): void
    {
        $reporter = app(PackageActionReporter::class);
        $bundle = $registry->get($this->bundleKey);

        if (! $bundle instanceof SeederBundle) {
            Log::warning("Package seeder bundle '{$this->bundleKey}' is not registered in this process; skipping.", [
                'job' => self::class,
            ]);

            $reporter->cancelled(
                PackageActionType::Job,
                $this->bundleKey,
                null,
                "Seeder bundle '{$this->bundleKey}' is not registered in this process; skipping.",
                $this->jobContext(),
                $this->mode,
            );

            return;
        }

        $reporter->started(PackageActionType::Job, $this->bundleKey, null, $this->jobContext(), $this->mode);
        $start = microtime(true);

        try {
            RuntimeConfigurator::forQueueJob()->timeout($this->timeout)->apply();
        } catch (Throwable) {
            // Runtime hardening is best-effort.
        }

        $scoped = new SeederRegistry;
        $scoped->registerBundle($bundle);

        $autorun->markExecuted($this->bundleKey);
        $stats = $executor->run($scoped, $this->mode);

        $durationMs = (microtime(true) - $start) * 1000;

        if ($stats->failed === 0) {
            $reporter->success(PackageActionType::Job, $this->bundleKey, null, $durationMs, $this->jobContext(['seeders' => $stats->success]), $this->mode);

            return;
        }

        // The job itself did not throw, but seeders inside it failed (each
        // already emitted a Seeder/Failed); surface a job-level failure too.
        $reporter->fail(
            PackageActionType::Job,
            $this->bundleKey,
            null,
            "{$stats->failed} seeder(s) failed in bundle '{$this->bundleKey}'.",
            FailureReason::Failed,
            context: $this->jobContext(['failed' => $stats->failed]),
            mode: $this->mode,
        );
    }

    /**
     * The framework's failure hook — fires when handle() throws or the job
     * exhausts its attempts / times out. Resolve the reporter fresh (never a
     * serialized property) and classify timeout/max-attempts as TimedOut.
     */
    public function failed(Throwable $e): void
    {
        app(PackageActionReporter::class)->jobFailed(
            $this->bundleKey,
            $e,
            FailureReason::fromThrowable($e),
            $this->jobContext(),
            $this->mode,
        );
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function jobContext(array $extra = []): array
    {
        return ['bundleKey' => $this->bundleKey, 'job' => self::class, ...$extra];
    }
}

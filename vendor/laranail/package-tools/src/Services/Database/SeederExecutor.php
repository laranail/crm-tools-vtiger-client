<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingCompleted;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingStarted;
use Simtabi\Laranail\Package\Tools\Events\SeederExecuted;
use Simtabi\Laranail\Package\Tools\Events\SeederExecuting;
use Simtabi\Laranail\Package\Tools\Events\SeederFailed;
use Simtabi\Laranail\Package\Tools\Events\SeedingFinished;
use Simtabi\Laranail\Package\Tools\Events\SeedingStarted;
use Simtabi\Laranail\Package\Tools\Exceptions\SeederException;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;
use Simtabi\Laranail\Package\Tools\Support\ForeignKeyCheckGuard;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;
use Throwable;

/**
 * Executes the bundles held in a `SeederRegistry` in priority order (lower
 * first, ties keep registration order), with per-bundle FK-toggle, event
 * emission, and constructor parameters — one package's options never leak
 * into another's run. Optional tree-structured console output when a
 * `SeederConsoleFormatter` is supplied.
 *
 * Returns a typed {@see SeederExecutionStats} value object.
 */
final readonly class SeederExecutor
{
    public function __construct(
        private Application $app,
        private ?ForeignKeyCheckGuard $fkGuard = null,
        private ?SeederConsoleFormatterInterface $formatter = null,
    ) {}

    /**
     * Execute every bundle registered in `$registry`. `$mode` records how
     * the run was initiated (inline/queued/scheduled) — carried by the
     * PackageSeeding* events and the run tracker.
     */
    public function run(SeederRegistry $registry, SeederExecutionMode $mode = SeederExecutionMode::Inline): SeederExecutionStats
    {
        if ($registry->isEmpty()) {
            return SeederExecutionStats::empty();
        }

        $bundles = $this->sortedBundles($registry);
        $anyFireEvents = array_any($bundles, static fn (SeederBundle $bundle): bool => $bundle->shouldFireEvents());
        $groups = array_values(array_map(
            static fn (SeederBundle $bundle): string => $bundle->namespace() ?? 'Default',
            $bundles,
        ));

        $this->formatter?->initializeSession();

        if ($anyFireEvents) {
            Event::dispatch(new SeedingStarted($groups));
        }

        $success = 0;
        $failed = 0;
        $totalTime = 0.0;
        $errors = [];

        $lastIndex = count($bundles) - 1;

        foreach ($bundles as $index => $bundle) {
            $execute = fn (): SeederExecutionStats => $this->runBundle($bundle, $index === $lastIndex, $mode);

            $lock = $this->acquireOverlapLock($bundle);
            if ($lock === false) {
                $this->formatter?->writeWarning(
                    "Skipping '{$bundle->key()}': another run holds its overlap lock.",
                );

                $this->reporter()->cancelled(
                    PackageActionType::Seeder,
                    $bundle->key(),
                    $bundle->namespace() ?? 'Default',
                    "Skipped '{$bundle->key()}': another run holds its overlap lock.",
                    ['bundleKey' => $bundle->key()],
                    $mode,
                );

                continue;
            }

            try {
                $stats = $bundle->disablesForeignKeyChecks()
                    ? ($this->fkGuard ?? new ForeignKeyCheckGuard)->run($execute)
                    : $execute();
            } finally {
                $lock?->release();
            }

            $success += $stats->success;
            $failed += $stats->failed;
            $totalTime += $stats->totalTime;
            $errors = [...$errors, ...$stats->errors];
        }

        $this->formatter?->displaySummary();

        if ($anyFireEvents) {
            Event::dispatch(new SeedingFinished($groups, $success, $failed));
        }

        return new SeederExecutionStats(
            total: $success + $failed,
            success: $success,
            failed: $failed,
            totalTime: $totalTime,
            errors: $errors,
        );
    }

    /**
     * Priority ascending; the sort is stable (php >= 8.0), so equal
     * priorities keep registration order.
     *
     * @return list<SeederBundle>
     */
    private function sortedBundles(SeederRegistry $registry): array
    {
        $bundles = array_values($registry->all());

        usort($bundles, static fn (SeederBundle $a, SeederBundle $b): int => $a->priorityValue() <=> $b->priorityValue());

        return $bundles;
    }

    private function runBundle(SeederBundle $bundle, bool $isLastBundle, SeederExecutionMode $mode): SeederExecutionStats
    {
        $group = $bundle->namespace() ?? 'Default';
        $seederClasses = $bundle->seeders();
        $fireEvents = $bundle->shouldFireEvents();
        $notify = $this->shouldNotify($bundle);
        $parameters = $bundle->parametersValue();
        $tracker = $this->tracker();
        $bundleStart = microtime(true);

        $tracker?->start($bundle->key(), count($seederClasses));

        if ($notify) {
            Event::dispatch(new PackageSeedingStarted($group, $bundle->key(), count($seederClasses), $mode));
        }

        // Cross-type lifecycle (the unified layer): fires independently of
        // the per-bundle notify opt-out, behind the global events.lifecycle
        // gate, so a bundle that silenced its detail events is still visible
        // to a cross-type observer.
        $this->reporter()->started(
            PackageActionType::Seeder,
            $bundle->key(),
            $group,
            ['bundleKey' => $bundle->key(), 'seeders' => count($seederClasses)],
            $mode,
        );

        $this->formatter?->displayGroupHeader($group, count($seederClasses), $isLastBundle);

        $success = 0;
        $failed = 0;
        $totalTime = 0.0;
        $errors = [];

        $lastIndex = count($seederClasses) - 1;
        $aborted = false;

        foreach ($seederClasses as $index => $class) {
            $isLast = $index === $lastIndex;

            if ($aborted) {
                $this->formatter?->displaySeederSkipped($class, 'previous seeder in bundle failed', $isLast);

                $this->reporter()->interrupted(
                    PackageActionType::Seeder,
                    $class,
                    $group,
                    "Skipped '{$class}': a previous seeder in bundle '{$bundle->key()}' failed.",
                    ['bundleKey' => $bundle->key(), 'seeder' => $class],
                    $mode,
                );

                continue;
            }

            $start = microtime(true);

            if ($fireEvents) {
                Event::dispatch(new SeederExecuting($class, $group));
            }
            $this->formatter?->displaySeederStart($class, $isLast);

            try {
                $seeder = $this->resolveSeeder($class, $parameters);
                // Invoke through `__invoke()` rather than `->run()`.
                // Laravel's base `Seeder` dispatches via the invoke wrapper.
                $seeder();

                $durationMs = (microtime(true) - $start) * 1000;
                $totalTime += $durationMs;
                $success++;
                $tracker?->advance($bundle->key());

                if ($fireEvents) {
                    Event::dispatch(new SeederExecuted($class, $durationMs, $group));
                }
                $this->formatter?->displaySeederSuccess($class, $durationMs / 1000, $isLast);
            } catch (Throwable $e) {
                $durationMs = (microtime(true) - $start) * 1000;
                $totalTime += $durationMs;
                $failed++;
                $tracker?->advance($bundle->key(), failed: true);

                $wrapped = $e instanceof SeederException ? $e : SeederException::executionFailed($class, $e);
                $errors[] = [
                    'class' => $class,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                    'package' => $group,
                ];

                if ($fireEvents) {
                    Event::dispatch(new SeederFailed($class, $e, $group));
                }
                if ($notify) {
                    Event::dispatch(new PackageSeedingFailed(
                        $group,
                        $bundle->key(),
                        $class,
                        $e::class,
                        $e->getMessage(),
                        $mode,
                    ));
                }
                $this->formatter?->displaySeederError($class, $wrapped, $durationMs / 1000, $isLast);

                // Unified failure layer — always logs (at error) and, behind
                // the events.failures gate, dispatches PackageActionFailed.
                // The former inline Log::error lived here; the reporter now
                // owns that log line.
                $this->reporter()->seederFailed(
                    $class,
                    $group,
                    $e,
                    context: ['bundleKey' => $bundle->key(), 'seeder' => $class],
                    mode: $mode,
                );

                if ($bundle->shouldStopOnFailure()) {
                    $aborted = true;
                }
            }
        }

        $stats = new SeederExecutionStats(
            total: $success + $failed,
            success: $success,
            failed: $failed,
            totalTime: $totalTime,
            errors: $errors,
            group: $group,
        );

        $tracker?->complete($bundle->key());

        $bundleDurationMs = (microtime(true) - $bundleStart) * 1000;

        if ($notify) {
            Event::dispatch(new PackageSeedingCompleted(
                $group,
                $bundle->key(),
                $stats,
                $bundleDurationMs,
                $mode,
            ));
        }

        // Unified success layer — only when the bundle completed cleanly;
        // any per-seeder failure already emitted PackageActionFailed above.
        if ($stats->failed === 0) {
            $this->reporter()->success(
                PackageActionType::Seeder,
                $bundle->key(),
                $group,
                $bundleDurationMs,
                ['bundleKey' => $bundle->key(), 'seeders' => $stats->success],
                $mode,
            );
        }

        return $stats;
    }

    /**
     * The shared action reporter, resolved lazily from the container so the
     * `final readonly` executor needs no constructor change (every existing
     * `new SeederExecutor($app)` call-site keeps compiling).
     */
    private function reporter(): PackageActionReporter
    {
        return $this->app->make(PackageActionReporter::class);
    }

    /**
     * Bundle-level PackageSeeding* events fire by default; suppressed by
     * the bundle's notifiesOnCompletion(false) or the global kill-switch.
     */
    private function shouldNotify(SeederBundle $bundle): bool
    {
        return $bundle->shouldNotify()
            && (bool) config('laranail.package-tools.seeders.events.enabled', true);
    }

    private function tracker(): ?SeederRunTracker
    {
        try {
            return $this->app->bound('cache') ? $this->app->make(SeederRunTracker::class) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Cache lock guarding a bundle against concurrent execution when it
     * opted in via withoutOverlapping(). Returns the held lock, null when
     * no lock applies, or false when another run holds it.
     */
    private function acquireOverlapLock(SeederBundle $bundle): Lock|null|false
    {
        $minutes = $bundle->withoutOverlappingMinutes();

        if ($minutes === null) {
            return null;
        }

        try {
            $lock = Cache::lock('package-tools:seeding:' . $bundle->key(), $minutes * 60);

            return $lock->get() ? $lock : false;
        } catch (Throwable) {
            // A cache without lock support must not block seeding.
            return null;
        }
    }

    /**
     * Resolve a seeder. Hand `$parameters` to the constructor when the
     * seeder accepts them; otherwise fall back to a parameterless `make()`.
     *
     * @param class-string<Seeder> $class
     * @param array<string, mixed> $parameters
     */
    private function resolveSeeder(string $class, array $parameters): Seeder
    {
        if (! class_exists($class)) {
            throw SeederException::classNotFound($class);
        }

        if (! is_subclass_of($class, Seeder::class)) {
            throw SeederException::invalidClass($class);
        }

        $reflection = new ReflectionClass($class);
        $ctor = $reflection->getConstructor();

        $seeder = $ctor === null || $ctor->getNumberOfParameters() === 0
            ? $this->app->make($class)
            : $this->app->make($class, $parameters);

        // Match Laravel's own `db:seed` behavior: without a container the
        // base Seeder's __invoke() bypasses method injection (run(FooService
        // $svc) fatals) and $this->call() is unusable.
        return $seeder->setContainer($this->app);
    }
}

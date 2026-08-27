<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Event;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageActionStarted;
use Simtabi\Laranail\Package\Tools\Events\PackageActionSucceeded;
use Simtabi\Laranail\Package\Tools\Facades\PackageActions;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRunTracker;
use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Throwable;

/**
 * The single choke point for the package-action lifecycle. Every start /
 * success / failure across migrations, seeders, jobs, schedules and installs
 * flows through here so it is logged once, dispatched once (behind its
 * config gate), and — for failures carrying a `bundleKey` — recorded in the
 * shared {@see SeederRunTracker}.
 *
 * Reachable anywhere via the {@see PackageActions}
 * facade (a container singleton), so consumer code can report and observe
 * actions without touching a service provider. Failures are ALWAYS logged
 * (at error) regardless of the dispatch gate; the high-frequency start /
 * success stream logs at debug and dispatches only when the lifecycle gate
 * is on. Reporting NEVER rethrows.
 */
final class PackageActionReporter
{
    private ?PackageLogger $logger = null;

    public function __construct(private readonly Application $app) {}

    /**
     * Return a reporter that writes its log lines to a specific package's
     * own logfile (via `$package->log()`), instead of the default channel.
     * Immutable — the shared singleton is never mutated.
     */
    public function forPackage(?PackageLogger $logger): self
    {
        $clone = clone $this;
        $clone->logger = $logger;

        return $clone;
    }

    // ── choke points (take a pre-built event) ────────────────────────────

    /**
     * Record + (gated) dispatch a started event. Returns it for chaining.
     */
    public function starting(PackageActionStarted $event): PackageActionStarted
    {
        // Never rethrow (class contract): a throwing lifecycle listener must
        // not, e.g., abort an already-applied migration whose Started fired.
        try {
            $this->write(LogLevel::DEBUG, $this->line($event->type, 'started', $event->action), $event->type, $event->toArray());

            if ($this->lifecycleDispatchEnabled()) {
                Event::dispatch($event);
            }
        } catch (Throwable) {
            // Lifecycle reporting must never break the caller.
        }

        return $event;
    }

    /**
     * Record + (gated) dispatch a succeeded event. Returns it for chaining.
     */
    public function succeeded(PackageActionSucceeded $event): PackageActionSucceeded
    {
        // Never rethrow (class contract): the work has already succeeded by
        // the time this fires, so a throwing listener must not undo/abort it.
        try {
            $this->write(LogLevel::DEBUG, $this->line($event->type, 'succeeded', $event->action), $event->type, $event->toArray());

            if ($this->lifecycleDispatchEnabled()) {
                Event::dispatch($event);
            }
        } catch (Throwable) {
            // Lifecycle reporting must never break the caller.
        }

        return $event;
    }

    /**
     * The failure choke point: always logs at error, records the failure in
     * the seeder tracker when a `bundleKey` is present, and dispatches only
     * when the failures gate allows. Never rethrows. Returns the event.
     */
    public function report(PackageActionFailed $event): PackageActionFailed
    {
        try {
            $this->write(
                LogLevel::ERROR,
                sprintf('%s %s: %s', $event->type->label(), $event->reason->label(), $event->action),
                $event->type,
                $event->toArray(),
            );

            $this->recordTrackerFailure($event);

            if ($this->failureDispatchEnabled()) {
                Event::dispatch($event);
            }
        } catch (Throwable) {
            // Reporting a failure must never itself break the caller.
        }

        return $event;
    }

    // ── ergonomic builders ───────────────────────────────────────────────

    /**
     * @param array<string, mixed> $context
     */
    public function started(
        PackageActionType $type,
        string $action,
        ?string $packageName = null,
        array $context = [],
        ?SeederExecutionMode $mode = null,
    ): PackageActionStarted {
        return $this->starting(new PackageActionStarted($type, $action, $packageName, $context, $mode));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function success(
        PackageActionType $type,
        string $action,
        ?string $packageName = null,
        ?float $durationMs = null,
        array $context = [],
        ?SeederExecutionMode $mode = null,
    ): PackageActionSucceeded {
        return $this->succeeded(new PackageActionSucceeded($type, $action, $packageName, $durationMs, $context, $mode));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function fail(
        PackageActionType $type,
        string $action,
        ?string $packageName,
        string $message,
        FailureReason $reason = FailureReason::Failed,
        ?string $exceptionClass = null,
        array $context = [],
        ?SeederExecutionMode $mode = null,
    ): PackageActionFailed {
        return $this->report(new PackageActionFailed($type, $reason, $action, $packageName, $exceptionClass, $message, $context, $mode));
    }

    /**
     * Report a failure from a throwable, computing the reason via
     * {@see FailureReason::fromThrowable()} unless one is supplied.
     *
     * @param array<string, mixed> $context
     */
    public function fromThrowable(
        PackageActionType $type,
        string $action,
        ?string $packageName,
        Throwable $e,
        ?FailureReason $reason = null,
        array $context = [],
        ?SeederExecutionMode $mode = null,
    ): PackageActionFailed {
        return $this->report(PackageActionFailed::fromThrowable(
            $type,
            $reason ?? FailureReason::fromThrowable($e),
            $action,
            $packageName,
            $e,
            $context,
            $mode,
        ));
    }

    /** @param array<string, mixed> $context */
    public function interrupted(PackageActionType $type, string $action, ?string $packageName, string $message, array $context = [], ?SeederExecutionMode $mode = null): PackageActionFailed
    {
        return $this->fail($type, $action, $packageName, $message, FailureReason::Interrupted, null, $context, $mode);
    }

    /** @param array<string, mixed> $context */
    public function cancelled(PackageActionType $type, string $action, ?string $packageName, string $message, array $context = [], ?SeederExecutionMode $mode = null): PackageActionFailed
    {
        return $this->fail($type, $action, $packageName, $message, FailureReason::Cancelled, null, $context, $mode);
    }

    /** @param array<string, mixed> $context */
    public function timedOut(PackageActionType $type, string $action, ?string $packageName, string $message, array $context = [], ?SeederExecutionMode $mode = null): PackageActionFailed
    {
        return $this->fail($type, $action, $packageName, $message, FailureReason::TimedOut, null, $context, $mode);
    }

    /** @param array<string, mixed> $context */
    public function unknown(PackageActionType $type, string $action, ?string $packageName, string $message, array $context = [], ?SeederExecutionMode $mode = null): PackageActionFailed
    {
        return $this->fail($type, $action, $packageName, $message, FailureReason::Unknown, null, $context, $mode);
    }

    /**
     * Wrap a callable so its start → (success | failure) lifecycle is emitted
     * automatically. Re-throws whatever the work throws (after reporting it),
     * so control flow is unchanged.
     *
     * @template T
     *
     * @param Closure(): T $work
     * @param array<string, mixed> $context
     * @return T
     */
    public function track(
        PackageActionType $type,
        string $action,
        ?string $packageName,
        Closure $work,
        array $context = [],
        ?SeederExecutionMode $mode = null,
    ): mixed {
        $this->started($type, $action, $packageName, $context, $mode);
        $start = microtime(true);

        try {
            $result = $work();
        } catch (Throwable $e) {
            $this->fromThrowable($type, $action, $packageName, $e, null, $context, $mode);

            throw $e;
        }

        $this->success($type, $action, $packageName, (microtime(true) - $start) * 1000, $context, $mode);

        return $result;
    }

    // ── type shortcuts ───────────────────────────────────────────────────

    /** @param array<string, mixed> $context */
    public function migrationStarting(string $migration, string $direction = 'up', array $context = []): PackageActionStarted
    {
        return $this->started(PackageActionType::Migration, $migration, null, ['direction' => $direction, ...$context]);
    }

    /** @param array<string, mixed> $context */
    public function migrationSucceeded(string $migration, string $direction = 'up', ?float $durationMs = null, array $context = []): PackageActionSucceeded
    {
        return $this->success(PackageActionType::Migration, $migration, null, $durationMs, ['direction' => $direction, ...$context]);
    }

    /** @param array<string, mixed> $context */
    public function migrationFailed(string $migration, string $direction, Throwable $e, array $context = []): PackageActionFailed
    {
        return $this->fromThrowable(PackageActionType::Migration, $migration, null, $e, null, ['direction' => $direction, ...$context]);
    }

    /** @param array<string, mixed> $context */
    public function seederFailed(string $action, ?string $packageName, Throwable $e, ?FailureReason $reason = null, array $context = [], ?SeederExecutionMode $mode = null): PackageActionFailed
    {
        return $this->fromThrowable(PackageActionType::Seeder, $action, $packageName, $e, $reason, $context, $mode);
    }

    /** @param array<string, mixed> $context */
    public function jobFailed(string $action, Throwable $e, ?FailureReason $reason = null, array $context = [], ?SeederExecutionMode $mode = null): PackageActionFailed
    {
        return $this->fromThrowable(PackageActionType::Job, $action, null, $e, $reason, $context, $mode);
    }

    // ── internals ────────────────────────────────────────────────────────

    private function recordTrackerFailure(PackageActionFailed $event): void
    {
        $bundleKey = $event->context['bundleKey'] ?? null;

        if (! is_string($bundleKey) || $bundleKey === '') {
            return;
        }

        try {
            if ($this->app->bound('cache')) {
                $this->app->make(SeederRunTracker::class)->fail($bundleKey, $event->message);
            }
        } catch (Throwable) {
            // Tracker writes are best-effort — never break reporting.
        }
    }

    private function lifecycleDispatchEnabled(): bool
    {
        return (bool) config('laranail.package-tools.events.lifecycle.enabled', true);
    }

    private function failureDispatchEnabled(): bool
    {
        return (bool) config('laranail.package-tools.events.failures.enabled', true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, PackageActionType $type, array $context): void
    {
        try {
            if ($this->logger instanceof PackageLogger) {
                $this->logger->log($level, $message, $type->label(), $context);

                return;
            }

            Log::log($level, $message, $context);
        } catch (Throwable) {
            // Logging must never break the host application.
        }
    }

    private function line(PackageActionType $type, string $phase, string $action): string
    {
        return sprintf('%s %s: %s', $type->label(), $phase, $action);
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Resilience;

use Closure;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Throwable;

/**
 * The failure-handling runner. One shape for every failure it handles —
 * classify → report (guarded) → crash-on-critical → record-and-continue-on-
 * degradable — with **no environment check anywhere** (rule 1). Behaviour is
 * decided by the failure's {@see BootCriticality}, not by dev/prod.
 *
 *  - Critical: continuing is unsafe → wrap, report, and rethrow. Fails fast,
 *    the same in every environment.
 *  - Degradable: continuing is safe → wrap, report through the central
 *    handler, record the degraded state, and continue. Never swallowed to a
 *    logfile — "loud" means the monitoring pipeline (rule 3).
 *
 * Unclassified callers default to Critical (rule 4, fail closed). Reporting
 * is guarded (rule 8): a broken monitoring path falls back to a last-resort
 * local write and never escalates a degradable failure into a crash.
 */
final class FailurePolicy
{
    /**
     * Run boot wiring, handling any failure per its criticality.
     *
     * @template T
     *
     * @param Closure(): T $work
     * @return T|null
     */
    public static function run(Closure $work, string $name, BootCriticality $criticality = BootCriticality::Critical): mixed
    {
        try {
            return $work();
        } catch (Throwable $e) {
            self::handle($e, $name, $criticality);

            return null;
        }
    }

    /**
     * Handle an already-caught failure per its criticality (for call sites
     * that catch their own exception, e.g. schedule resolution).
     */
    public static function handle(Throwable $original, string $name, BootCriticality $criticality): void
    {
        $wrapped = $original instanceof PackageBootException
            ? $original
            : PackageBootException::from($name, $criticality, $original);

        self::report($wrapped);

        if ($criticality === BootCriticality::Critical) {
            throw $wrapped; // fail fast, everywhere (rule 1)
        }

        self::bootReport()?->recordDegraded($name, $criticality->name, $original::class);
    }

    /**
     * A suspicious-but-non-fatal condition (rule 14): logged at warning level
     * to the descriptive bar, then tolerated. Not a failure — it does not go
     * through the runner and does not touch the boot degraded state.
     *
     * @param array<string, mixed> $context
     */
    public static function warn(string $subject, array $context = []): void
    {
        try {
            logger()->warning("tolerated anomaly [{$subject}]", ['subject' => $subject, ...$context]);
        } catch (Throwable) {
            // Warning logging must never itself break the caller.
        }
    }

    /**
     * Report through the central error handler (rule 3), guarded (rule 8): a
     * failure in the reporting path falls back to a last-resort local write
     * and never escalates.
     */
    private static function report(PackageBootException $wrapped): void
    {
        try {
            report($wrapped);
        } catch (Throwable $reportingFailure) {
            error_log((string) $wrapped);
            error_log((string) $reportingFailure);
        }
    }

    private static function bootReport(): ?BootReport
    {
        try {
            if (function_exists('app') && app()->bound(BootReport::class)) {
                return app(BootReport::class);
            }
        } catch (Throwable) {
            // best-effort: recording degraded state must never throw
        }

        return null;
    }
}

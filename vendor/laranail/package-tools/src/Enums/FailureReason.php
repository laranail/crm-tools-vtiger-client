<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Throwable;

/**
 * Why a package action ended without success — the taxonomy carried by
 * {@see PackageActionFailed}.
 *
 *  - Failed:      the work threw and could not complete.
 *  - Interrupted: aborted mid-run by a preceding failure (e.g. a
 *                 stop-on-failure bundle skipping the rest).
 *  - Cancelled:   never started — a lock was held, or the target vanished.
 *  - TimedOut:    the runtime budget (queue timeout / max attempts) ran out.
 *  - Unknown:     an unclassifiable end state.
 */
enum FailureReason: string
{
    case Failed = 'failed';
    case Interrupted = 'interrupted';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
    case Unknown = 'unknown';

    /**
     * Best-effort classification of a throwable into a reason. Queue
     * timeout / max-attempt exhaustion maps to TimedOut; everything else is
     * a plain Failed. This is the single place a throwable becomes a reason.
     */
    public static function fromThrowable(Throwable $e): self
    {
        return match (true) {
            $e instanceof TimeoutExceededException,
            $e instanceof MaxAttemptsExceededException => self::TimedOut,
            default => self::Failed,
        };
    }

    /**
     * Human-facing label for console / log output.
     */
    public function label(): string
    {
        return match ($this) {
            self::Failed => 'Failed',
            self::Interrupted => 'Interrupted',
            self::Cancelled => 'Cancelled',
            self::TimedOut => 'Timed out',
            self::Unknown => 'Unknown',
        };
    }
}

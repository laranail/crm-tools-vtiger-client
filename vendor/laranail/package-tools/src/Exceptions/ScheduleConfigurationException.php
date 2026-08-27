<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Exceptions;

use RuntimeException;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\HandlesScheduleFailures;
use Throwable;

/**
 * Thrown when a package's declarative schedule configuration cannot be
 * applied to the real `Illuminate\Console\Scheduling\Schedule` — a bad
 * cadence, an unknown scheduler method, or a raw `schedulesUsing()`
 * callback that failed.
 *
 * Carries structured `$context` for logging. Whether it surfaces (strict)
 * or is logged-and-skipped (lenient) is decided by
 * {@see HandlesScheduleFailures}.
 */
final class ScheduleConfigurationException extends RuntimeException
{
    /** @var array<string, mixed> */
    public array $context = [];

    /**
     * A scheduled-command definition failed to apply (typically an unknown
     * cadence/scheduler method surfacing from the deferred-call replay).
     */
    public static function commandFailed(string $command, Throwable $previous): self
    {
        $e = new self(
            "Package scheduled command '{$command}' could not be scheduled: {$previous->getMessage()}",
            5001,
            $previous,
        );
        $e->context = [
            'command' => $command,
            'reason' => $previous->getMessage(),
            'exception' => $previous::class,
        ];

        return $e;
    }

    /**
     * A raw `schedulesUsing()` callback threw while registering entries.
     */
    public static function callbackFailed(Throwable $previous): self
    {
        $e = new self(
            "A package schedule callback failed: {$previous->getMessage()}",
            5002,
            $previous,
        );
        $e->context = [
            'reason' => $previous->getMessage(),
            'exception' => $previous::class,
        ];

        return $e;
    }

    /**
     * A scheduled-seeder bundle's cadence could not be applied.
     */
    public static function seederFailed(string $key, Throwable $previous): self
    {
        $e = new self(
            "Package scheduled seeder '{$key}' could not be scheduled: {$previous->getMessage()}",
            5003,
            $previous,
        );
        $e->context = [
            'bundle' => $key,
            'reason' => $previous->getMessage(),
            'exception' => $previous::class,
        ];

        return $e;
    }
}

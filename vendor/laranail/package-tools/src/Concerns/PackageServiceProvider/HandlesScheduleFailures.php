<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Exceptions\ScheduleConfigurationException;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * How a package's schedule-configuration failure (a bad cadence / unknown
 * scheduler method / throwing schedulesUsing() callback) is handled while the
 * `Schedule` resolves.
 *
 * Classified **Degradable** per the failure-handling standard: skipping the
 * one bad entry leaves a safe, reduced state (every other task still
 * registers). It reports through the central handler and is recorded on the
 * boot degraded surface, then continues — the SAME in every environment (no
 * `isProduction()` / `scheduling.strict` branch). The caught exception is
 * per-entry (inside the schedule-registration loops), so continuation
 * registers the remaining tasks.
 */
trait HandlesScheduleFailures
{
    protected function handleScheduleFailure(ScheduleConfigurationException $e): void
    {
        $name = $this->scheduleActionName($e);

        // Package action-event layer (BC) — dispatch the event directly; the
        // failure policy owns the report + degraded record (no double log).
        Event::dispatch(PackageActionFailed::fromThrowable(
            PackageActionType::Schedule,
            FailureReason::Failed,
            $name,
            $this->package->name,
            $e,
            $e->context,
        ));

        FailurePolicy::handle($e, "schedule [{$name}]", BootCriticality::Degradable);
    }

    private function scheduleActionName(ScheduleConfigurationException $e): string
    {
        foreach (['command', 'bundle'] as $key) {
            $value = $e->context[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return 'schedule';
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Console\Scheduling\Schedule;
use Simtabi\Laranail\Package\Tools\Exceptions\ScheduleConfigurationException;
use Throwable;

trait ProcessScheduledCommands
{
    use HandlesScheduleFailures;

    /**
     * Apply the package's scheduled-command definitions and raw schedule
     * callbacks once the Schedule resolves (console only, after every
     * provider booted) — config gates and cadences evaluate then, never
     * earlier.
     *
     * Each definition and callback is isolated and its failures routed
     * through {@see HandlesScheduleFailures}: logged with context, then
     * rethrown (strict, the default outside production) or skipped
     * (lenient) so one package's scheduling typo can't break the whole
     * scheduler.
     */
    protected function bootPackageScheduledCommands(): self
    {
        if ($this->package->getScheduledCommands() === [] && $this->package->getScheduleCallbacks() === []) {
            return $this;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            foreach ($this->package->getScheduledCommands() as $definition) {
                if (! $definition->shouldSchedule()) {
                    continue;
                }

                try {
                    $definition->applyTo($schedule->command($definition->command()));
                } catch (Throwable $e) {
                    $this->handleScheduleFailure(
                        ScheduleConfigurationException::commandFailed($definition->command(), $e),
                    );
                }
            }

            foreach ($this->package->getScheduleCallbacks() as $callback) {
                try {
                    $callback($schedule);
                } catch (Throwable $e) {
                    $this->handleScheduleFailure(
                        ScheduleConfigurationException::callbackFailed($e),
                    );
                }
            }
        });

        return $this;
    }
}

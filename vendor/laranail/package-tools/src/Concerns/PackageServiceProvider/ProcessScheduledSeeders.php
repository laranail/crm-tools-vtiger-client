<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Console\Scheduling\Schedule;
use Simtabi\Laranail\Package\Tools\Exceptions\ScheduleConfigurationException;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;
use Simtabi\Laranail\Package\Tools\Support\Definitions\ScheduledCommandDefinition;
use Throwable;

trait ProcessScheduledSeeders
{
    use HandlesScheduleFailures;

    /**
     * Wire every seeder definition that declared a cadence onto the
     * scheduler as `laranail::package-tools.seed --key=X --scheduled`.
     * NO mode flag is passed — the command reads the bundle's own
     * runsInBackground() choice; --scheduled only marks provenance.
     * Cadence application reuses the scheduled-command machinery
     * (CronBuilder + deferred Event replay), so the full scheduler
     * vocabulary works.
     */
    protected function bootPackageScheduledSeeders(): static
    {
        $scheduled = array_filter(
            $this->package->getPackageSeederDefinitions(),
            static fn (AutoSeederDefinition $definition): bool => $definition->hasCadence(),
        );

        if ($scheduled === []) {
            return $this;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($scheduled): void {
            foreach ($scheduled as $definition) {
                if (! $definition->shouldRegister()) {
                    continue;
                }

                $cadence = $definition->cadenceValue();
                if ($cadence === null) {
                    continue;
                }

                try {
                    $carrier = ScheduledCommandDefinition::make('laranail::package-tools.seed')
                        ->cadence($cadence);

                    if ($definition->overlapExpiresAtValue() !== null) {
                        $carrier->withoutOverlapping($definition->overlapExpiresAtValue());
                    }

                    $event = $schedule->command('laranail::package-tools.seed', [
                        '--key' => [$definition->key()],
                        '--scheduled' => true,
                    ]);

                    $carrier->applyTo($event);
                } catch (Throwable $e) {
                    $this->handleScheduleFailure(
                        ScheduleConfigurationException::seederFailed($definition->key(), $e),
                    );
                }
            }
        });

        return $this;
    }
}

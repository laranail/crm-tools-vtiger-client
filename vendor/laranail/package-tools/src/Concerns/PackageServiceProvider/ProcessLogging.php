<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingCompleted;
use Simtabi\Laranail\Package\Tools\Events\PackageSeedingFailed;
use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;
use Throwable;

trait ProcessLogging
{
    /**
     * Register-phase half: bind the container alias
     * (`app("laranail.logger.{vendor}-{package}")`) so app code can
     * resolve the logger without holding the Package.
     */
    protected function registerPackageLogging(): static
    {
        try {
            $alias = 'laranail.logger.' . $this->package->log()->channelName();
            $this->app->singleton($alias, fn (): PackageLogger => $this->package->log());
        } catch (Throwable) {
            // A name-less/malformed package fails validation later in
            // registerPackage(); logging setup must not preempt that error.
        }

        return $this;
    }

    /**
     * Boot-phase half, first in the boot chain: every provider has
     * registered and all config is final — flush the lines buffered since
     * configurePackage() against the now-authoritative settings.
     */
    protected function bootPackageLogging(): static
    {
        $this->package->markLoggerReady();
        $this->logPackageSeedingOutcomes();

        return $this;
    }

    /**
     * Built-in observability: this package's seeder-bundle outcomes also
     * land in its own logfile (`->success()` / `->error()` with a Seeder
     * label), composing the events feature with $package->log().
     */
    private function logPackageSeedingOutcomes(): void
    {
        $keys = array_map(
            static fn (AutoSeederDefinition $definition): string => $definition->key(),
            $this->package->getPackageSeederDefinitions(),
        );

        if ($keys === []) {
            return;
        }

        Event::listen(PackageSeedingCompleted::class, function (PackageSeedingCompleted $event) use ($keys): void {
            if (! in_array($event->bundleKey, $keys, true)) {
                return;
            }

            $context = ['bundle' => $event->bundleKey, 'mode' => $event->mode->value, 'duration_ms' => round($event->durationMs, 2)];

            $event->stats->hasFailures()
                ? $this->package->log()->warning($event->stats->getSummary(), 'Seeder', $context)
                : $this->package->log()->success($event->stats->getSummary(), 'Seeder', $context);
        });

        Event::listen(PackageSeedingFailed::class, function (PackageSeedingFailed $event) use ($keys): void {
            if (! in_array($event->bundleKey, $keys, true)) {
                return;
            }

            $this->package->log()->error("{$event->seederClass} failed: {$event->message}", 'Seeder', [
                'bundle' => $event->bundleKey,
                'exception' => $event->exceptionClass,
                'mode' => $event->mode->value,
            ]);
        });
    }
}

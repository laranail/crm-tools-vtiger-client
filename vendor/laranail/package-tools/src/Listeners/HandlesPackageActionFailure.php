<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Listeners;

use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;

/**
 * Convenience base for a {@see PackageActionFailed} listener that wants to
 * react per action type. Subclass and override only the hooks you care
 * about; the rest are no-ops.
 *
 * Register it like any listener:
 *
 *     $package->registerEventListener(PackageActionFailed::class, MyFailureListener::class);
 *
 * The match is exhaustive over {@see PackageActionType}; static analysis
 * enforces that a future enum case is handled here (a stronger guarantee
 * than a runtime default arm).
 */
abstract class HandlesPackageActionFailure
{
    public function handle(PackageActionFailed $event): void
    {
        match ($event->type) {
            PackageActionType::Migration => $this->onMigration($event),
            PackageActionType::Seeder => $this->onSeeder($event),
            PackageActionType::Job => $this->onJob($event),
            PackageActionType::Schedule => $this->onSchedule($event),
            PackageActionType::Install => $this->onInstall($event),
            PackageActionType::Custom => $this->onCustom($event),
        };
    }

    protected function onMigration(PackageActionFailed $event): void {}

    protected function onSeeder(PackageActionFailed $event): void {}

    protected function onJob(PackageActionFailed $event): void {}

    protected function onSchedule(PackageActionFailed $event): void {}

    protected function onInstall(PackageActionFailed $event): void {}

    protected function onCustom(PackageActionFailed $event): void {}
}

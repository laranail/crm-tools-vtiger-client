<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageActionStarted;
use Simtabi\Laranail\Package\Tools\Events\PackageActionSucceeded;

/**
 * The kind of package action reported through the
 * {@see PackageActionStarted},
 * {@see PackageActionSucceeded} and
 * {@see PackageActionFailed} family —
 * the discriminator a cross-type listener switches on.
 */
enum PackageActionType: string
{
    case Migration = 'migration';
    case Seeder = 'seeder';
    case Job = 'job';
    case Schedule = 'schedule';
    case Install = 'install';
    case Custom = 'custom';

    /**
     * Human-facing label for console / log output.
     */
    public function label(): string
    {
        return match ($this) {
            self::Migration => 'Migration',
            self::Seeder => 'Seeder',
            self::Job => 'Job',
            self::Schedule => 'Schedule',
            self::Install => 'Install',
            self::Custom => 'Custom',
        };
    }
}

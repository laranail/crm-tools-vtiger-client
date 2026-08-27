<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Database domain aggregator.
 *
 * Composer traits live in `ConfiguresComposer`, not here.
 */
trait ConfiguresDatabase
{
    use HasFactoriesAndSeeders;
    use HasMigrations;
    use HasMorphMaps;
    use HasObservers;
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Composer domain aggregator.
 *
 * Runtime Composer interactions: install/update/require/remove and
 * composer.json validation. Scaffolder-time `ManagesComposer` lives in
 * package-scaffolder and is not aggregated here.
 */
trait ConfiguresComposer
{
    use HasComposerOperations;
}

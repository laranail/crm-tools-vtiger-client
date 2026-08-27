<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Service-providers domain aggregator. Wraps the single leaf trait.
 */
trait ConfiguresServiceProviders
{
    use HasServiceProviders;
}

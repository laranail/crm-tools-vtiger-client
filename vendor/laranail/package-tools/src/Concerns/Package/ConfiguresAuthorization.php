<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Authorization domain aggregator.
 */
trait ConfiguresAuthorization
{
    use HasGates;
    use HasPolicies;
}

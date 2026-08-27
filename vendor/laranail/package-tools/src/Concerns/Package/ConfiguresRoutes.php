<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Routes domain aggregator.
 *
 * Routing plus multi-level package nesting (HasNestedLevels lets a package
 * live in `platform/<vendor>/<package>` instead of one canonical layout).
 */
trait ConfiguresRoutes
{
    use HasAdvancedPaths;
    use HasNestedLevels;
    use HasRateLimiters;
    use HasRouteBindings;
    use HasRoutes;
}

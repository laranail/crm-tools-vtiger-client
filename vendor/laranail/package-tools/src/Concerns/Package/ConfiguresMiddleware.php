<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Middleware domain aggregator.
 */
trait ConfiguresMiddleware
{
    use HasEnhancedMiddleware;
    use HasMiddlewareManagement;
}

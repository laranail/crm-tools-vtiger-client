<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;

/**
 * Convenience trait exposing a fresh {@see HttpConfigurationServiceInterface}
 * resolved from the container.
 *
 * All configuration logic lives in the service; this trait is a thin accessor
 * so a consuming class can call `$this->httpConfig()->toGuzzleConfig()`.
 */
trait HasGuzzleConfig
{
    protected function httpConfig(): HttpConfigurationServiceInterface
    {
        return app(HttpConfigurationServiceInterface::class);
    }
}

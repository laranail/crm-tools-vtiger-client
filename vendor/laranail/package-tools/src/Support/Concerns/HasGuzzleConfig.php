<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Concerns;

use Simtabi\Laranail\Package\Tools\Services\Http\Contracts\HttpConfigurationServiceInterface;

/**
 * Thin accessor trait that yields the singleton HTTP configuration
 * service from the container. Allows hosting classes (service
 * providers, install commands, jobs that hit external APIs) to grab a
 * preconfigured options array without manually wiring a binding.
 *
 * Usage:
 *
 *     use Simtabi\Laranail\Package\Tools\Support\Concerns\HasGuzzleConfig;
 *
 *     final class FetchUsersJob {
 *         use HasGuzzleConfig;
 *
 *         public function handle(): void {
 *             $options = $this->httpConfig()->toGuzzleConfig();
 *             Http::withOptions($options)->get('https://api.example.com/users');
 *         }
 *     }
 */
trait HasGuzzleConfig
{
    protected function httpConfig(): HttpConfigurationServiceInterface
    {
        return app(HttpConfigurationServiceInterface::class);
    }
}

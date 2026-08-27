<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

trait RegisterChildProviders
{
    /**
     * Register the package's declared child service providers. Called in the
     * register phase; each provider's own deferral is honoured by the container.
     */
    protected function registerPackageChildProviders(): self
    {
        foreach ($this->package->childProviders as $provider) {
            $this->app->register($provider);
        }

        return $this;
    }
}

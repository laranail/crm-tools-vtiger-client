<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

trait ProcessServiceProviders
{
    protected function bootPackageServiceProviders(): self
    {
        if (! $this->package->publishableProviderName || ! $this->app->runningInConsole()) {
            return $this;
        }

        $providerName = $this->package->publishableProviderName;
        $vendorProvider = $this->package->basePath("/resources/stubs/{$providerName}.php.stub");
        $appProvider = base_path("app/Providers/{$providerName}.php");

        $publishTag = $this->package->getNamespacedPublishTag('provider');

        $this->publishes([$vendorProvider => $appProvider], $publishTag);

        return $this;
    }
}

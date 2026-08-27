<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasServiceProviders
{
    public ?string $publishableProviderName = null;

    /**
     * Child service providers to register with the application when this
     * package registers. Eager and deferred providers are both supported —
     * each provider's own deferral is honoured by the container.
     *
     * @var list<class-string>
     */
    public array $childProviders = [];

    public function publishesServiceProvider(string $providerName): static
    {
        $this->publishableProviderName = $providerName;

        return $this;
    }

    /**
     * Register one or more child service providers with the application.
     *
     * @param list<class-string> $providers
     */
    public function hasChildProviders(array $providers): static
    {
        $this->childProviders = array_merge($this->childProviders, array_values($providers));

        return $this;
    }
}

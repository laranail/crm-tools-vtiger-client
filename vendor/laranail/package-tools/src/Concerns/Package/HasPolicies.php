<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\Gate;

/**
 * declarative model-policy registration, applied in the deferred boot
 * hooks. laravel's own $policies vocabulary.
 */
trait HasPolicies
{
    /** @var array<class-string, class-string> model => policy */
    protected array $gatePolicies = [];

    /**
     * @param class-string $model
     * @param class-string $policy
     */
    public function registerPolicy(string $model, string $policy): static
    {
        $this->gatePolicies[$model] = $policy;

        return $this;
    }

    /**
     * @param array<class-string, class-string> $policies [Model::class => Policy::class]
     */
    public function registerPolicies(array $policies): static
    {
        foreach ($policies as $model => $policy) {
            $this->registerPolicy($model, $policy);
        }

        return $this;
    }

    public function bootPackagePolicies(): void
    {
        foreach ($this->gatePolicies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * @return array<class-string, class-string>
     */
    public function getPolicies(): array
    {
        return $this->gatePolicies;
    }
}

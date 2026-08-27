<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Database\Eloquent\Model;

/**
 * declarative model-observer registration, applied in the deferred boot
 * hooks.
 */
trait HasObservers
{
    /** @var array<class-string<Model>, list<class-string>> */
    protected array $modelObservers = [];

    /**
     * @param class-string<Model> $model
     * @param class-string|list<class-string> $observers
     */
    public function registerObserver(string $model, string|array $observers): static
    {
        foreach ((array) $observers as $observer) {
            $this->modelObservers[$model][] = $observer;
        }

        return $this;
    }

    /**
     * @param array<class-string<Model>, class-string|list<class-string>> $observers
     */
    public function registerObservers(array $observers): static
    {
        foreach ($observers as $model => $observer) {
            $this->registerObserver($model, $observer);
        }

        return $this;
    }

    public function bootPackageObservers(): void
    {
        foreach ($this->modelObservers as $model => $observers) {
            foreach ($observers as $observer) {
                $model::observe($observer);
            }
        }
    }

    /**
     * @return array<class-string<Model>, list<class-string>>
     */
    public function getObservers(): array
    {
        return $this->modelObservers;
    }
}

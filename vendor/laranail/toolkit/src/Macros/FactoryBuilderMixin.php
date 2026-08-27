<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds convenience methods to Eloquent's model factories via Factory::mixin().
 *
 * The returned closures are rebound to the target factory by Laravel's
 * Macroable trait, so inside them `$this` is the factory instance.
 */
class FactoryBuilderMixin
{
    /**
     * Flush the model's event listeners before continuing the factory chain.
     *
     * @return Closure(): Factory<Model>
     */
    public function withoutEvents(): Closure
    {
        return function (): Factory {
            /** @var Factory<Model> $factory */
            $factory = $this;

            $modelName = $factory->modelName();
            $modelName::flushEventListeners();

            return $factory;
        };
    }
}

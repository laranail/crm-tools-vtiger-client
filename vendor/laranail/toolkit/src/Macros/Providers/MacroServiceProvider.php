<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Macros\ArrMacros;
use Simtabi\Laranail\Toolkit\Macros\CarbonMacros;
use Simtabi\Laranail\Toolkit\Macros\CollectionMacros;
use Simtabi\Laranail\Toolkit\Macros\FactoryBuilderMixin;
use Simtabi\Laranail\Toolkit\Macros\QueryBuilderMacros;
use Simtabi\Laranail\Toolkit\Macros\RequestMacros;
use Simtabi\Laranail\Toolkit\Macros\ResponseMacros;
use Simtabi\Laranail\Toolkit\Macros\StringMacros;

/**
 * Coordinator that wires every grouped macro provider in the toolkit and
 * applies the factory mixin.
 *
 * Macros must be available globally, so this provider is registered eagerly
 * (it is not deferrable).
 */
final class MacroServiceProvider extends ServiceProvider
{
    /**
     * Grouped macro providers registered by this coordinator.
     *
     * @var list<class-string<ServiceProvider>>
     */
    private const MACRO_PROVIDERS = [
        StringMacros::class,
        CollectionMacros::class,
        ArrMacros::class,
        QueryBuilderMacros::class,
        RequestMacros::class,
        CarbonMacros::class,
        ResponseMacros::class,
    ];

    public function register(): void
    {
        foreach (self::MACRO_PROVIDERS as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        if (class_exists(Factory::class)) {
            Factory::mixin(new FactoryBuilderMixin());
        }
    }
}

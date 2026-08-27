<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Livewire\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireService;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceInterface;

/**
 * Deferred service provider for the self-contained Livewire module.
 *
 * Binds the component-registration service and flushes any collected
 * components into Livewire on boot. Self-contained so the module can later be
 * extracted into its own package; it never hard-depends on livewire/livewire.
 */
class LivewireServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(LivewireServiceInterface::class, LivewireService::class);
        $this->app->alias(LivewireServiceInterface::class, 'laranail.livewire');
    }

    public function boot(): void
    {
        $service = $this->app->make(LivewireServiceInterface::class);

        if ($service->isLivewireAvailable()) {
            $service->flush();
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            LivewireServiceInterface::class,
            'laranail.livewire',
        ];
    }
}

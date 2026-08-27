<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Livewire;

/**
 * Host-class hook for registering Livewire components.
 *
 * A host class declares its components by implementing
 * `getLivewireComponents(): array<string, string>` (alias → component class);
 * calling {@see registerLivewireComponents()} forwards each pair to the
 * {@see LivewireServiceInterface}. Resolution and flushing remain a safe no-op
 * when livewire/livewire is not installed.
 */
trait HasLivewireComponents
{
    /**
     * Register the host class's declared Livewire components.
     *
     * No-op unless the host class exposes
     * `getLivewireComponents(): array<string, string>`.
     */
    public function registerLivewireComponents(): void
    {
        if (!method_exists($this, 'getLivewireComponents')) {
            return;
        }

        /** @var array<string, string> $components */
        $components = $this->getLivewireComponents();

        if ($components === []) {
            return;
        }

        $service = app(LivewireServiceInterface::class);
        $service->registerComponents($components);
        $service->flush();
    }
}

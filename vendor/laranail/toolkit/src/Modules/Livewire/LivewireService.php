<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Livewire;

use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Livewire component-registration manager.
 *
 * Collects alias → component-class pairs and flushes them into Livewire's
 * component registry. Resolution of {@see Livewire} is guarded by
 * {@see isLivewireAvailable()} so the toolkit can ship without a hard
 * dependency on livewire/livewire — every Livewire-touching operation is a
 * safe no-op when the package is absent.
 */
class LivewireService implements LivewireServiceInterface
{
    /**
     * Collected alias → component-class map.
     *
     * @var array<string, string>
     */
    private array $components = [];

    public function registerComponent(string $alias, string $class): self
    {
        $this->components[Str::trim($alias)] = $class;

        return $this;
    }

    public function registerComponents(array $map): self
    {
        foreach ($map as $alias => $class) {
            $this->registerComponent($alias, $class);
        }

        return $this;
    }

    public function getRegisteredComponents(): array
    {
        return $this->components;
    }

    public function flush(): void
    {
        if (!$this->isLivewireAvailable()) {
            return;
        }

        foreach ($this->components as $alias => $class) {
            $this->registerWithLivewire($alias, $class);
        }
    }

    public function isLivewireAvailable(): bool
    {
        return class_exists(Livewire::class);
    }

    public function generateComponentKey(string $name): string
    {
        return Str::kebab($name);
    }

    /**
     * Bind a single alias → class pair into Livewire's component registry.
     *
     * Isolated as a seam so the flush loop can be exercised without the
     * livewire/livewire package present. Only ever reached after
     * {@see isLivewireAvailable()} has confirmed the class exists.
     *
     * @param class-string|string $class
     */
    protected function registerWithLivewire(string $alias, string $class): void
    {
        Livewire::component($alias, $class);
    }
}

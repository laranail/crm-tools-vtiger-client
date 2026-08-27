<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Livewire\Livewire;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;

trait ProcessLivewireComponents
{
    /**
     * Register the package's Livewire components, prefixing each with the
     * package namespace (e.g. vendor::component-name) unless the package
     * opted out. Registration is reactive: when livewire's provider has
     * not registered yet (dont-discover setups), the components register
     * the moment it binds — however late. Never bound = correct no-op.
     */
    protected function bootPackageLivewireComponents(): self
    {
        if (empty($this->package->livewireComponents)) {
            return $this;
        }

        if (! class_exists(Livewire::class)) {
            return $this;
        }

        $gate = $this->package->livewireGate;

        if ($gate instanceof ConfigGate && ! $gate->passes()) {
            return $this;
        }

        $register = function (): void {
            $viewNamespace = $this->package->viewNamespace();

            foreach ($this->package->livewireComponents as $name => $class) {
                $componentName = match (true) {
                    ! $this->package->livewirePrefixComponents => $name,
                    str_contains((string) $name, '::') => $name,
                    default => "{$viewNamespace}::{$name}",
                };

                Livewire::component($componentName, $class);
            }
        };

        if ($this->app->bound('livewire')) {
            $register();
        } else {
            $this->app->afterResolving('livewire', static fn () => $register());
        }

        return $this;
    }
}

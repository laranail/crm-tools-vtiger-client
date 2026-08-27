<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Livewire;

/**
 * Contract for the Livewire component-registration module.
 *
 * Collects alias → component-class pairs and flushes them into Livewire's
 * component registry. Every method is a safe no-op when livewire/livewire is
 * not installed, so the toolkit never hard-depends on Livewire.
 */
interface LivewireServiceInterface
{
    /**
     * Register a single component under an alias.
     *
     * @param string              $alias The blade tag / component alias (e.g. "my-component").
     * @param class-string|string $class The Livewire component class to bind to the alias.
     */
    public function registerComponent(string $alias, string $class): self;

    /**
     * Register a map of alias → component class in one call.
     *
     * @param array<string, class-string|string> $map
     */
    public function registerComponents(array $map): self;

    /**
     * Get the collected alias → component-class map.
     *
     * @return array<string, string>
     */
    public function getRegisteredComponents(): array;

    /**
     * Flush every collected component into Livewire's registry.
     *
     * Safe no-op when Livewire is not installed.
     */
    public function flush(): void;

    /**
     * Determine whether livewire/livewire is installed and available.
     */
    public function isLivewireAvailable(): bool;

    /**
     * Derive a stable component key (kebab-case) from a component name.
     */
    public function generateComponentKey(string $name): string;
}

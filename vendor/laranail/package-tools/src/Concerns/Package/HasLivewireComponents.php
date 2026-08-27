<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;

trait HasLivewireComponents
{
    /** @var array<string, string> Map of component name => class */
    public array $livewireComponents = [];

    /** package-level gate; a later call replaces an earlier one (last wins) */
    public ?ConfigGate $livewireGate = null;

    public bool $livewirePrefixComponents = true;

    /**
     * Register a single Livewire component
     *
     * @param string $name Component name (e.g., 'icon-browser')
     * @param string $class Component class (e.g., IconBrowser::class)
     */
    public function hasLivewireComponent(string $name, string $class): static
    {
        $this->livewireComponents[$name] = $class;

        return $this;
    }

    /**
     * Register multiple Livewire components from an array of
     * ['name' => ClassName::class], or a closure returning one. The
     * optional gate is package-level: components register only when
     * config($whenConfig, $whenConfigDefault) is truthy at boot.
     *
     * @param array<string, string>|Closure $components
     */
    public function hasLivewireComponents(
        array|Closure $components,
        ?string $whenConfig = null,
        bool $whenConfigDefault = true,
    ): static {
        if ($components instanceof Closure) {
            $components = $components();
        }

        foreach ($components as $name => $class) {
            $this->hasLivewireComponent($name, $class);
        }

        if ($whenConfig !== null) {
            $this->livewireGate = ConfigGate::make($whenConfig, $whenConfigDefault)->truthy();
        }

        return $this;
    }

    /**
     * register component names exactly as given instead of prefixing them
     * with the view namespace — required for dot-form names like
     * 'vendor-package.component'.
     */
    public function withoutLivewireNamespacePrefix(): static
    {
        $this->livewirePrefixComponents = false;

        return $this;
    }
}

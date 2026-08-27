<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Component;

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Simtabi\Laranail\Package\Tools\Contracts\RegistryInterface;

/**
 * Registers Blade, Livewire, and Vue components.
 */
class ComponentRegistry implements RegistryInterface
{
    /** @var array<string, string> */
    protected array $bladeComponents = [];

    /** @var array<string, string> */
    protected array $livewireComponents = [];

    /** @var array<string, string> */
    protected array $vueComponents = [];

    /**
     * Register a Blade component
     *
     * @param string $name Component name
     * @param string $class Component class
     */
    public function registerBlade(string $name, string $class): void
    {
        Blade::component($name, $class);
        $this->bladeComponents[$name] = $class;
    }

    /**
     * Register a Livewire component
     *
     * @param string $name Component name
     * @param string $class Component class
     */
    public function registerLivewire(string $name, string $class): void
    {
        if (class_exists(Livewire::class)) {
            // Normalize name (replace / with -)
            $normalizedName = str_replace('/', '-', $name);
            Livewire::component($normalizedName, $class);
            $this->livewireComponents[$normalizedName] = $class;
        }
    }

    /**
     * Register a Vue component path
     *
     * @param string $name Component name
     * @param string $path Component file path
     */
    public function registerVue(string $name, string $path): void
    {
        $this->vueComponents[$name] = $path;
    }

    /**
     * Validate a component class exists
     *
     * @param string $class Component class name
     */
    public function validate(string $class): bool
    {
        return class_exists($class);
    }

    /**
     * Get registered components by type
     *
     * @param string $type Component type (blade, livewire, vue)
     * @return array<string, string>
     */
    public function getByType(string $type): array
    {
        return match ($type) {
            'blade' => $this->bladeComponents,
            'livewire' => $this->livewireComponents,
            'vue' => $this->vueComponents,
            default => [],
        };
    }

    /**
     * {@inheritDoc}
     */
    public function register(string $key, mixed $value): void
    {
        // Determine type from key format
        if (str_contains($key, 'blade::')) {
            $name = str_replace('blade::', '', $key);
            $this->registerBlade($name, $value);
        } elseif (str_contains($key, 'livewire::')) {
            $name = str_replace('livewire::', '', $key);
            $this->registerLivewire($name, $value);
        } elseif (str_contains($key, 'vue::')) {
            $name = str_replace('vue::', '', $key);
            $this->registerVue($name, $value);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRegistered(): array
    {
        return [
            'blade' => $this->bladeComponents,
            'livewire' => $this->livewireComponents,
            'vue' => $this->vueComponents,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset($this->bladeComponents[$key])
            || isset($this->livewireComponents[$key])
            || isset($this->vueComponents[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bladeComponents[$key]
            ?? $this->livewireComponents[$key]
            ?? $this->vueComponents[$key]
            ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(string $key): void
    {
        unset(
            $this->bladeComponents[$key],
            $this->livewireComponents[$key],
            $this->vueComponents[$key]
        );
    }
}

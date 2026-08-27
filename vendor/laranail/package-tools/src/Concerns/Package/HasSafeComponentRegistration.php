<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Livewire\Livewire;
use Simtabi\Laranail\Package\Tools\Services\Component\ComponentRegistry;
use Throwable;

/**
 * Component registration that catches and records errors instead of throwing.
 */
trait HasSafeComponentRegistration
{
    protected ?ComponentRegistry $safeComponentRegistry = null;

    /** @var list<string> */
    protected array $componentErrors = [];

    /**
     * Register a Blade component, recording any failure.
     *
     * @param string $name Component name/alias
     * @param string $class Component class
     */
    public function safelyRegisterComponent(string $name, string $class): static
    {
        try {
            if (! class_exists($class)) {
                $this->componentErrors[] = "Component class not found: {$class}";

                return $this;
            }

            $registry = $this->getSafeComponentRegistry();
            $registry->register("blade::{$name}", $class);

        } catch (Throwable $e) {
            $this->componentErrors[] = "Failed to register component {$name}: " . $e->getMessage();
        }

        return $this;
    }

    /**
     * @param array<string, string> $components [name => class]
     */
    public function safelyRegisterComponents(array $components): static
    {
        foreach ($components as $name => $class) {
            $this->safelyRegisterComponent($name, $class);
        }

        return $this;
    }

    /**
     * Register a Livewire component, recording any failure.
     *
     * @param string $name Component alias
     * @param string $class Component class
     */
    public function safelyRegisterLivewireComponent(string $name, string $class): static
    {
        try {
            if (! class_exists($class)) {
                $this->componentErrors[] = "Livewire component class not found: {$class}";

                return $this;
            }

            if (! class_exists(Livewire::class)) {
                $this->componentErrors[] = 'Livewire not installed';

                return $this;
            }

            $registry = $this->getSafeComponentRegistry();
            $registry->register("livewire::{$name}", $class);

        } catch (Throwable $e) {
            $this->componentErrors[] = "Failed to register Livewire component {$name}: " . $e->getMessage();
        }

        return $this;
    }

    /**
     * Register components, skipping any that fail validation.
     *
     * @param array<string, string> $components [name => class]
     * @param string $type Component type ('blade', 'livewire', 'vue')
     */
    public function registerValidatedComponents(array $components, string $type = 'blade'): static
    {
        foreach ($components as $name => $class) {
            if ($this->validateComponent($name, $class, $type)) {
                $registry = $this->getSafeComponentRegistry();
                $registry->register("{$type}::{$name}", $class);
            }
        }

        return $this;
    }

    /**
     * @param string $name Component name
     * @param string $class Component class
     * @param string $type Component type
     */
    protected function validateComponent(string $name, string $class, string $type): bool
    {
        if (! class_exists($class)) {
            $this->componentErrors[] = "Component class not found: {$class}";

            return false;
        }

        if ($name === '' || $name === '0' || ! preg_match('/^[a-z0-9\-_:]+$/i', $name)) {
            $this->componentErrors[] = "Invalid component name: {$name}";

            return false;
        }

        if ($type === 'livewire' && ! class_exists(Livewire::class)) {
            $this->componentErrors[] = "Livewire not installed, cannot register: {$name}";

            return false;
        }

        return true;
    }

    /**
     * @return array<string>
     */
    public function getComponentErrors(): array
    {
        return $this->componentErrors;
    }

    public function hasComponentErrors(): bool
    {
        return ! empty($this->componentErrors);
    }

    protected function getSafeComponentRegistry(): ComponentRegistry
    {
        if (! $this->safeComponentRegistry) {
            $this->safeComponentRegistry = app(ComponentRegistry::class);
        }

        return $this->safeComponentRegistry;
    }
}

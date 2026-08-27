<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;

/**
 * Lifecycle hooks around register, boot, config load, and view load.
 */
trait HasLifecycleHooks
{
    /** @var array<Closure> Before register hooks */
    protected array $beforeRegisterHooks = [];

    /** @var array<Closure> After register hooks */
    protected array $afterRegisterHooks = [];

    /** @var array<Closure> Before boot hooks */
    protected array $beforeBootHooks = [];

    /** @var array<Closure> After boot hooks */
    protected array $afterBootHooks = [];

    /** @var array<Closure> Before config load hooks */
    protected array $beforeConfigLoadHooks = [];

    /** @var array<Closure> After config load hooks */
    protected array $afterConfigLoadHooks = [];

    /** @var array<Closure> Before view load hooks */
    protected array $beforeViewLoadHooks = [];

    /** @var array<Closure> After view load hooks */
    protected array $afterViewLoadHooks = [];

    /**
     * Register a hook to run before package registration.
     */
    public function onBeforeRegister(Closure $callback): static
    {
        $this->beforeRegisterHooks[] = $callback;

        return $this;
    }

    /**
     * Register a hook to run after package registration
     */
    public function onAfterRegister(Closure $callback): static
    {
        $this->afterRegisterHooks[] = $callback;

        return $this;
    }

    /**
     * Register a hook to run before package boot
     */
    public function onBeforeBoot(Closure $callback): static
    {
        $this->beforeBootHooks[] = $callback;

        return $this;
    }

    /**
     * Register a hook to run after package boot
     */
    public function onAfterBoot(Closure $callback): static
    {
        $this->afterBootHooks[] = $callback;

        return $this;
    }

    /**
     * Register a hook to run before config loading
     */
    public function onBeforeConfigLoad(Closure $callback): static
    {
        $this->beforeConfigLoadHooks[] = $callback;

        return $this;
    }

    /**
     * Register a hook to run after config loading
     */
    public function onAfterConfigLoad(Closure $callback): static
    {
        $this->afterConfigLoadHooks[] = $callback;

        return $this;
    }

    /**
     * Register a hook to run before view loading
     */
    public function onBeforeViewLoad(Closure $callback): static
    {
        $this->beforeViewLoadHooks[] = $callback;

        return $this;
    }

    /**
     * Register a hook to run after view loading
     */
    public function onAfterViewLoad(Closure $callback): static
    {
        $this->afterViewLoadHooks[] = $callback;

        return $this;
    }

    /**
     * Execute before register hooks
     */
    protected function executeBeforeRegisterHooks(): void
    {
        foreach ($this->beforeRegisterHooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Execute after register hooks
     */
    protected function executeAfterRegisterHooks(): void
    {
        foreach ($this->afterRegisterHooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Execute before boot hooks
     */
    protected function executeBeforeBootHooks(): void
    {
        foreach ($this->beforeBootHooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Execute after boot hooks
     */
    protected function executeAfterBootHooks(): void
    {
        foreach ($this->afterBootHooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Execute before config load hooks
     */
    protected function executeBeforeConfigLoadHooks(): void
    {
        foreach ($this->beforeConfigLoadHooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Execute after config load hooks
     */
    protected function executeAfterConfigLoadHooks(): void
    {
        foreach ($this->afterConfigLoadHooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Execute before view load hooks
     */
    protected function executeBeforeViewLoadHooks(): void
    {
        foreach ($this->beforeViewLoadHooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Execute after view load hooks
     */
    protected function executeAfterViewLoadHooks(): void
    {
        foreach ($this->afterViewLoadHooks as $hook) {
            $hook($this);
        }
    }

    /**
     * Get all registered hooks (for testing)
     *
     * @return array<string, int>
     */
    public function getRegisteredHooks(): array
    {
        return [
            'beforeRegister' => count($this->beforeRegisterHooks),
            'afterRegister' => count($this->afterRegisterHooks),
            'beforeBoot' => count($this->beforeBootHooks),
            'afterBoot' => count($this->afterBootHooks),
            'beforeConfigLoad' => count($this->beforeConfigLoadHooks),
            'afterConfigLoad' => count($this->afterConfigLoadHooks),
            'beforeViewLoad' => count($this->beforeViewLoadHooks),
            'afterViewLoad' => count($this->afterViewLoadHooks),
        ];
    }
}

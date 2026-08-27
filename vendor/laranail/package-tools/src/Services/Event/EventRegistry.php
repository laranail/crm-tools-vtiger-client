<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Event;

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Package\Tools\Contracts\RegistryInterface;

/**
 * Registers event listeners and subscribers.
 */
class EventRegistry implements RegistryInterface
{
    /** @var array<string, array<int, string|callable>> */
    protected array $listeners = [];

    /** @var array<int, string> */
    protected array $subscribers = [];

    /**
     * Register an event listener
     *
     * @param string $event Event name
     * @param string|callable $listener Listener class or callable
     */
    public function registerListener(string $event, string|callable $listener): void
    {
        Event::listen($event, $listener);

        if (! isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $listener;
    }

    /**
     * Register an event subscriber
     *
     * @param string $subscriber Subscriber class
     */
    public function registerSubscriber(string $subscriber): void
    {
        Event::subscribe($subscriber);
        $this->subscribers[] = $subscriber;
    }

    /**
     * Get listeners for an event
     *
     * @param string $event Event name
     * @return array<int, string|callable>
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /**
     * Check if event has listeners
     *
     * @param string $event Event name
     */
    public function hasListener(string $event): bool
    {
        return isset($this->listeners[$event]) && (isset($this->listeners[$event]) && $this->listeners[$event] !== []);
    }

    /**
     * {@inheritDoc}
     */
    public function register(string $key, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $listener) {
                $this->registerListener($key, $listener);
            }
        } else {
            $this->registerListener($key, $value);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRegistered(): array
    {
        return [
            'listeners' => $this->listeners,
            'subscribers' => $this->subscribers,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        if ($this->hasListener($key)) {
            return true;
        }

        return in_array($key, $this->subscribers, true);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->listeners[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(string $key): void
    {
        unset($this->listeners[$key]);

        $index = array_search($key, $this->subscribers, true);
        if ($index !== false) {
            unset($this->subscribers[$index]);
        }
    }
}

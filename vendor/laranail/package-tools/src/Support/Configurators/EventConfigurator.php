<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Configurators;

use Closure;

/**
 * Fluent event sub-builder, returned by `$package->event()`. A thin façade
 * over the package's existing event storage — it adds only the pair-form
 * normalization and the closure-subscriber path; everything else delegates.
 */
final class EventConfigurator extends PackageConfigurator
{
    /**
     * Register listeners in EITHER shape, auto-detected by key type:
     *   - pair form:  [[Event::class, Listener::class], ...]      (int keys)
     *   - map form:   [Event::class => Listener|Listener[], ...]  (string keys)
     *
     * @param array<int|string, mixed> $listeners
     */
    public function addListeners(array $listeners): self
    {
        foreach ($listeners as $key => $value) {
            if (is_int($key)) {
                // pair form: [Event, Listener]
                [$event, $listener] = $value;
                $this->package->registerEventListener((string) $event, (string) $listener);

                continue;
            }

            // map form: Event => Listener|Listener[]
            foreach ((array) $value as $listener) {
                $this->package->registerEventListener($key, (string) $listener);
            }
        }

        return $this;
    }

    public function addListener(string $event, string $listener): self
    {
        $this->package->registerEventListener($event, $listener);

        return $this;
    }

    /**
     * A class-string subscriber (Event::subscribe) or a Closure that receives
     * the dispatcher (`fn (Dispatcher $events) => $events->listen(...)`).
     */
    public function addSubscriber(string|Closure $subscriber): self
    {
        if ($subscriber instanceof Closure) {
            $this->package->registerEventSubscriberCallback($subscriber);

            return $this;
        }

        $this->package->registerEventSubscriber($subscriber);

        return $this;
    }

    /**
     * @param array<int, string|Closure> $subscribers
     */
    public function addSubscribers(array $subscribers): self
    {
        foreach ($subscribers as $subscriber) {
            $this->addSubscriber($subscriber);
        }

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Eventing\Listeners;

/**
 * Reusable event-listener base with a gating hook.
 *
 * Subclasses implement {@see handleEvent()} (their real logic) rather than
 * `handle()` directly. The provided `handle()` consults {@see shouldHandle()}
 * first and short-circuits when it returns false — letting a listener be
 * conditionally enabled (feature flags, environment, payload checks) without
 * repeating the guard in every listener.
 */
abstract class Listener
{
    /**
     * Dispatch entry point. Skips {@see handleEvent()} when {@see shouldHandle()}
     * returns false.
     */
    public function handle(object $event): void
    {
        if (!$this->shouldHandle($event)) {
            return;
        }

        $this->handleEvent($event);
    }

    /**
     * Whether this listener should run for the given event. Defaults to true;
     * override to gate handling (feature flag, environment, payload, …).
     */
    protected function shouldHandle(object $event): bool
    {
        return true;
    }

    /**
     * The listener's actual work. Only invoked when {@see shouldHandle()} passes.
     */
    abstract protected function handleEvent(object $event): void;
}

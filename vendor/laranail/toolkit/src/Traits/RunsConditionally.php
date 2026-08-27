<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Simtabi\Laranail\Toolkit\Support\ConditionalRunner;

/**
 * Convenience trait giving a host class context-aware conditional execution
 * without instantiating {@see ConditionalRunner} by hand.
 *
 * Each helper queues a single callback against the matching context predicate
 * and immediately runs it, returning the callback's result (or `null` when the
 * context does not hold). For multiple chained predicates, reach for
 * {@see conditional()} and drive the runner directly.
 */
trait RunsConditionally
{
    /**
     * A fresh runner instance for this host.
     */
    protected function conditional(): ConditionalRunner
    {
        return ConditionalRunner::make();
    }

    /**
     * Run a callback only in the console context.
     */
    protected function runInConsole(callable $callback): mixed
    {
        return $this->conditional()->whenConsole($callback)->run()[0] ?? null;
    }

    /**
     * Run a callback only outside the console context (web / API).
     */
    protected function runOutsideConsole(callable $callback): mixed
    {
        return $this->conditional()->whenNotConsole($callback)->run()[0] ?? null;
    }

    /**
     * Run a callback only for web requests.
     */
    protected function runForWeb(callable $callback): mixed
    {
        return $this->conditional()->whenWeb($callback)->run()[0] ?? null;
    }

    /**
     * Run a callback only for API requests.
     */
    protected function runForApi(callable $callback): mixed
    {
        return $this->conditional()->whenApi($callback)->run()[0] ?? null;
    }

    /**
     * Run a callback only for authenticated users (optionally on a guard).
     */
    protected function runWhenAuthenticated(callable $callback, ?string $guard = null): mixed
    {
        return $this->conditional()->whenAuthenticated($callback, $guard)->run()[0] ?? null;
    }

    /**
     * Run a callback only for guests (optionally on a guard).
     */
    protected function runWhenGuest(callable $callback, ?string $guard = null): mixed
    {
        return $this->conditional()->whenGuest($callback, $guard)->run()[0] ?? null;
    }

    /**
     * Run a callback only when the authenticated user holds the given role.
     */
    protected function runForRole(string $role, callable $callback, ?string $guard = null): mixed
    {
        return $this->conditional()->whenRole($role, $callback, $guard)->run()[0] ?? null;
    }
}

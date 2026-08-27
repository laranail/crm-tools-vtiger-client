<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Support\Traits\Conditionable;

/**
 * Context-aware conditional execution.
 *
 * Native Laravel's {@see Conditionable} answers
 * "run this when a boolean is true". `ConditionalRunner` adds the *request /
 * auth context* predicates that boolean does not carry: console vs. web vs.
 * API, authenticated vs. guest, and role checks.
 *
 * Each predicate runs its callback only when the context holds; predicates are
 * chainable, so several may be queued and each fires (or not) when {@see run()}
 * is finally called — there is no implicit short-circuit between them.
 *
 * Every probe reads native runtime state (`app()->runningInConsole()`,
 * `request()`, `auth()`); role checks call `hasRole()` on the authenticated
 * user *only if that method exists*, so the runner works with any model that
 * provides one (e.g. spatie/laravel-permission) without a hard dependency on
 * any package.
 */
final class ConditionalRunner
{
    /**
     * Queued callbacks whose context predicate evaluated true, in the order
     * they were registered.
     *
     * @var list<callable():mixed>
     */
    private array $pending = [];

    /**
     * Fluent entry point.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Run a callback when the running context is the console.
     */
    public function whenConsole(callable $callback): self
    {
        return $this->when(app()->runningInConsole(), $callback);
    }

    /**
     * Run a callback when the running context is NOT the console (web / API).
     */
    public function whenNotConsole(callable $callback): self
    {
        return $this->when(!app()->runningInConsole(), $callback);
    }

    /**
     * Run a callback for API requests.
     *
     * Mirrors the legacy semantics: a request is treated as API when its path
     * matches `api/*` or it explicitly expects a JSON response.
     */
    public function whenApi(callable $callback): self
    {
        return $this->when($this->isApiRequest(), $callback);
    }

    /**
     * Run a callback for web requests (a bound HTTP request that is not API).
     */
    public function whenWeb(callable $callback): self
    {
        return $this->when($this->isWebRequest(), $callback);
    }

    /**
     * Run a callback when a user is authenticated (optionally on a guard).
     */
    public function whenAuthenticated(callable $callback, ?string $guard = null): self
    {
        return $this->when(auth()->guard($guard)->check(), $callback);
    }

    /**
     * Run a callback when no user is authenticated (optionally on a guard).
     */
    public function whenGuest(callable $callback, ?string $guard = null): self
    {
        return $this->when(auth()->guard($guard)->guest(), $callback);
    }

    /**
     * Run a callback when the authenticated user holds the given role.
     *
     * Falls back to `false` (callback skipped) when no user is authenticated or
     * the user model does not expose a `hasRole()` method.
     */
    public function whenRole(string $role, callable $callback, ?string $guard = null): self
    {
        return $this->when($this->userHasRole($role, $guard), $callback);
    }

    /**
     * Run a callback when the authenticated user does NOT hold the given role.
     *
     * Requires an authenticated user whose model exposes `hasRole()`; a guest
     * or a model without that method is treated as "role absent".
     */
    public function whenRoleIsNot(string $role, callable $callback, ?string $guard = null): self
    {
        $user = auth()->guard($guard)->user();

        $resolvable = $user !== null && method_exists($user, 'hasRole');

        return $this->when($resolvable && !$user->hasRole($role), $callback);
    }

    /**
     * Queue a callback to run when the supplied condition is truthy.
     *
     * The general-purpose primitive the context predicates build on.
     */
    public function when(bool $condition, callable $callback): self
    {
        if ($condition) {
            $this->pending[] = $callback;
        }

        return $this;
    }

    /**
     * Execute every queued callback in registration order and return their
     * results.
     *
     * @return list<mixed>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->pending as $callback) {
            $results[] = $callback();
        }

        $this->pending = [];

        return $results;
    }

    /**
     * Determine whether the bound request should be treated as an API call.
     *
     * Request-driven (not console-driven): a bound request whose path matches
     * `api/*` or which explicitly expects JSON. Absent a bound request there is
     * nothing to classify, so the answer is false.
     */
    private function isApiRequest(): bool
    {
        if (!app()->bound('request')) {
            return false;
        }

        $request = request();

        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * Determine whether the bound request is a (non-API) web request.
     */
    private function isWebRequest(): bool
    {
        return app()->bound('request') && !$this->isApiRequest();
    }

    /**
     * Resolve whether the authenticated user holds a role, dependency-free.
     */
    private function userHasRole(string $role, ?string $guard): bool
    {
        $user = auth()->guard($guard)->user();

        if ($user === null || !method_exists($user, 'hasRole')) {
            return false;
        }

        return (bool) $user->hasRole($role);
    }
}

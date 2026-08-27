<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rewrites incoming request keys before the request reaches the route handler.
 *
 * By default every input key is converted to **snake_case** so a camelCase JSON
 * API payload maps onto Laravel's snake_case validation/fillable conventions.
 * Override {@see ApiRequestMiddleware::mutateKey()} (or
 * {@see ApiRequestMiddleware::hook()}) in a subclass to change the convention or
 * adjust the request further.
 *
 * Opt in per route/group via the `laranail-toolkit.api-request` alias — it is **not** registered
 * globally, so it never silently rewrites every request in the app.
 */
class ApiRequestMiddleware extends ApiMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->replace($this->mutateKeys($request->all()));

        $request = $this->hook($request);

        return $next($request);
    }

    /**
     * Convert each incoming request key to snake_case.
     */
    protected function mutateKey(string $key): string
    {
        return Str::snake($key);
    }

    /**
     * Hook into the request after its keys have been rewritten, before it is
     * forwarded. Override to mutate the request further.
     */
    protected function hook(Request $request): Request
    {
        return $request;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

/**
 * Session / query-string "filter key" helpers.
 *
 * A filter key is an `&`-joined list of filter tokens carried in the query
 * string (e.g. `status=active&sort=name`). The three filter-key methods are
 * pure string operations; {@see saveJavaScriptCookies()} is the stateful one —
 * it persists a request input into the session and queues a same-named cookie
 * so client-side JavaScript can read it.
 *
 * The implementation is fully injectable (session store + cookie jar resolved
 * through the container), so it is testable without facades.
 */
interface SessionServiceInterface
{
    /**
     * Whether `$value` is one of the `&`-joined tokens in `$key`.
     */
    public function existsInFilterKey(string $key, mixed $value = null): bool;

    /**
     * Join the given (non-null) values into a single `&`-joined filter key.
     */
    public function joinInFilterKey(mixed ...$value): string;

    /**
     * Remove `$value` (and the reserved `page` token) from an existing
     * `&`-joined filter key. Returns the rebuilt key, or null when nothing
     * remains.
     */
    public function removeFromFilterKey(string $oldValues, mixed $value): ?string;

    /**
     * Persist a request input value into the session and queue a same-named
     * cookie so client-side JavaScript can read it. No-op when the input is
     * absent.
     *
     * @param int $duration cookie lifetime in minutes
     */
    public function saveJavaScriptCookies(string $cookieName, int $duration = 60): void;
}

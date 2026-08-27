<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Session / query-string "filter key" helpers.
 *
 * Recovered from the legacy SessionService. The three filter-key methods are
 * pure string operations on the `&`-joined query-string layer; only
 * {@see saveJavaScriptCookies()} is stateful — and it writes through the
 * injected session store and cookie jar rather than the Session/Cookie facades,
 * so the service is testable without booting the full HTTP kernel.
 *
 * Kept distinct from CollectionFilter (which filters in-memory collections)
 * because this operates on the query-string/session layer.
 */
final readonly class SessionService implements SessionServiceInterface
{
    public function __construct(
        private Session $session,
        private CookieJar $cookie,
        private Request $request,
    ) {}

    public function existsInFilterKey(string $key, mixed $value = null): bool
    {
        return Collection::make(explode('&', $key))->contains(Cast::toString($value));
    }

    public function joinInFilterKey(mixed ...$value): string
    {
        return Collection::make($value)
            ->reject(static fn (mixed $item): bool => $item === null)
            ->implode('&');
    }

    public function removeFromFilterKey(string $oldValues, mixed $value): ?string
    {
        $remaining = Collection::make(explode('&', $oldValues))
            ->reject(static fn (string $token): bool => $token === Cast::toString($value) || $token === 'page')
            ->values();

        return $remaining->isEmpty() ? null : $remaining->implode('&');
    }

    public function saveJavaScriptCookies(string $cookieName, int $duration = 60): void
    {
        $value = $this->request->input($cookieName);

        if ($value === null) {
            return;
        }

        $this->session->put($cookieName, $value);
        $this->cookie->queue($cookieName, Cast::toString($value), $duration);
    }
}

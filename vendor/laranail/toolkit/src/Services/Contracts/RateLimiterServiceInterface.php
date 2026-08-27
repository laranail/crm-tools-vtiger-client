<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Illuminate\Cache\RateLimiter;
use Simtabi\Laranail\Toolkit\Services\RateLimiterService;

/**
 * Public surface of the toolkit's {@see RateLimiterService}.
 *
 * A thin, injectable wrapper over Illuminate's {@see RateLimiter}. Named
 * `RateLimiterService` (not a bare `RateLimiter`) to avoid colliding with the
 * framework class. Bound interface→{@see RateLimiterService}.
 */
interface RateLimiterServiceInterface
{
    /**
     * Attempt to hit the given rate limiter.
     */
    public function attempt(string $key, int $maxAttempts, int $decayMinutes): bool;

    /**
     * Get the number of attempts for the given key.
     */
    public function attempts(string $key): int;

    /**
     * Get the number of remaining attempts for the given key.
     */
    public function remaining(string $key, int $maxAttempts): int;

    /**
     * Clear the hits and lockout timer for the given key.
     */
    public function clear(string $key): void;

    /**
     * Get the number of seconds until the "key" is accessible again.
     */
    public function availableIn(string $key): int;

    /**
     * Determine if the given key has been "accessed" too many times.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Increment the counter for a given key for a given decay time.
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    /**
     * Get the underlying rate limiter instance.
     */
    public function getRateLimiter(): RateLimiter;
}

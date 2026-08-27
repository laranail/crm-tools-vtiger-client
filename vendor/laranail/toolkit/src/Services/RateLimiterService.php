<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;
use Simtabi\Laranail\Toolkit\Services\Contracts\RateLimiterServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;

class RateLimiterService implements RateLimiterServiceInterface
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $rateLimiter;

    /**
     * Create a new rate limiter utility instance.
     *
     * @return void
     */
    public function __construct(Repository $cache)
    {
        $this->rateLimiter = new RateLimiter($cache);
    }

    /**
     * Attempt to hit the given rate limiter.
     */
    public function attempt(string $key, int $maxAttempts, int $decayMinutes): bool
    {
        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->rateLimiter->hit($key, $decayMinutes * 60);

        return true;
    }

    /**
     * Get the number of attempts for the given key.
     */
    public function attempts(string $key): int
    {
        return Cast::toInt($this->rateLimiter->attempts($key));
    }

    /**
     * Get the number of remaining attempts for the given key.
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        return $this->rateLimiter->remaining($key, $maxAttempts);
    }

    /**
     * Clear the hits and lockout timer for the given key.
     */
    public function clear(string $key): void
    {
        $this->rateLimiter->clear($key);
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     */
    public function availableIn(string $key): int
    {
        return $this->rateLimiter->availableIn($key);
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->rateLimiter->tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Increment the counter for a given key for a given decay time.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        return $this->rateLimiter->hit($key, $decaySeconds);
    }

    /**
     * Get the underlying rate limiter instance.
     */
    public function getRateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }
}

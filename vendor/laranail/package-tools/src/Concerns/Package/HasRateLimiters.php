<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Simtabi\Laranail\Package\Tools\Support\Definitions\RateLimiterDefinition;

/**
 * Declarative named rate limiters. A limiter is either a raw
 * `Closure($request)` (passed straight to `RateLimiter::for()`) or a fluent
 * {@see RateLimiterDefinition} that captures the attempts/key/response
 * pattern. Limiter closures run per request, so config reads inside them are
 * already lazy — no extra sugar needed.
 */
trait HasRateLimiters
{
    /** @var array<string, Closure|RateLimiterDefinition> */
    protected array $rateLimiters = [];

    /**
     * Register a limiter. Pass a {@see RateLimiterDefinition} (registered
     * under its own `name()`), or the legacy `(string $name, Closure)` pair.
     */
    public function registerRateLimiter(string|RateLimiterDefinition $name, ?Closure $limiter = null): static
    {
        if ($name instanceof RateLimiterDefinition) {
            $this->rateLimiters[$name->name()] = $name;

            return $this;
        }

        if (! $limiter instanceof Closure) {
            throw new InvalidArgumentException(
                "registerRateLimiter('{$name}', …) needs a Closure, or pass a RateLimiterDefinition instead.",
            );
        }

        $this->rateLimiters[$name] = $limiter;

        return $this;
    }

    /**
     * Register several limiters at once. Accepts the legacy `[name => Closure]`
     * map and/or entries that are {@see RateLimiterDefinition}s (which carry
     * their own name).
     *
     * @param array<int|string, Closure|RateLimiterDefinition> $limiters
     */
    public function registerRateLimiters(array $limiters): static
    {
        foreach ($limiters as $name => $limiter) {
            if ($limiter instanceof RateLimiterDefinition) {
                $this->registerRateLimiter($limiter);

                continue;
            }

            $this->registerRateLimiter((string) $name, $limiter);
        }

        return $this;
    }

    public function bootPackageRateLimiters(): void
    {
        foreach ($this->rateLimiters as $name => $limiter) {
            if ($limiter instanceof RateLimiterDefinition) {
                RateLimiter::for($name, static fn (Request $request): Limit|array => $limiter->resolve($request));

                continue;
            }

            RateLimiter::for($name, $limiter);
        }
    }

    /**
     * @return array<string, Closure|RateLimiterDefinition>
     */
    public function getRateLimiters(): array
    {
        return $this->rateLimiters;
    }
}

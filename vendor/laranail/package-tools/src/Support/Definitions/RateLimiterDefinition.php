<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Fluent, extensible definition of a named rate limiter — the reusable shape
 * behind the boilerplate every throttling package hand-rolls: resolve an
 * attempt count (often from config/settings), build a throttle key from the
 * request, and return a `Limit`.
 *
 *     RateLimiterDefinition::make('login')
 *         ->perMinute(fn () => max((int) setting('throttle_attempts', 5), 1))
 *         ->byField('email')                                  // strtolower(email) . '|' . ip
 *
 *     RateLimiterDefinition::make('two-factor')
 *         ->perMinute(5)
 *         ->bySessionKey('login.id')
 *
 * Each window method (`perMinute`, `perHour`, …) starts a new limit; the key
 * and response methods bind to the most-recently added window, so multiple
 * `Limit`s can be composed for one name:
 *
 *     RateLimiterDefinition::make('api')
 *         ->perMinute(60)->byUser()
 *         ->perDay(10_000)->byIp();
 *
 * Register it via `$package->registerRateLimiter(RateLimiterDefinition ...)`.
 */
final class RateLimiterDefinition
{
    /**
     * @var list<array{attempts: int|Closure(Request): int, kind: string, decay: int, key: ?Closure(Request): string, response: ?Closure}>
     */
    private array $specs = [];

    /**
     * @var (Closure(Request): (Limit|array<int, Limit>))|null
     */
    private ?Closure $using = null;

    private function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    // ── windows ──────────────────────────────────────────────────────────

    /**
     * @param int|Closure(Request): int $attempts
     */
    public function perSecond(int|Closure $attempts): self
    {
        return $this->addWindow('second', 1, $attempts);
    }

    /**
     * @param int|Closure(Request): int $attempts
     */
    public function perMinute(int|Closure $attempts): self
    {
        return $this->addWindow('minute', 1, $attempts);
    }

    /**
     * @param int|Closure(Request): int $attempts
     */
    public function perMinutes(int $decayMinutes, int|Closure $attempts): self
    {
        return $this->addWindow('minutes', $decayMinutes, $attempts);
    }

    /**
     * @param int|Closure(Request): int $attempts
     */
    public function perHour(int|Closure $attempts): self
    {
        return $this->addWindow('hour', 1, $attempts);
    }

    /**
     * @param int|Closure(Request): int $attempts
     */
    public function perDay(int|Closure $attempts): self
    {
        return $this->addWindow('day', 1, $attempts);
    }

    /**
     * An unlimited window ({@see Limit::none()}) — useful to exempt a keyed
     * segment while other windows still throttle.
     */
    public function unlimited(): self
    {
        return $this->addWindow('none', 0, 0);
    }

    // ── keys (bind to the most-recent window) ────────────────────────────

    /**
     * A fixed/global key string, or a `Closure(Request): string` resolver.
     */
    public function by(string|Closure $key): self
    {
        $resolver = $key instanceof Closure ? $key : static fn (Request $request): string => $key;

        $this->specs[$this->requireWindow()]['key'] = $resolver;

        return $this;
    }

    public function byIp(): self
    {
        $this->specs[$this->requireWindow()]['key'] = static fn (Request $request): string => (string) $request->ip();

        return $this;
    }

    /**
     * Key by a (lowercased) request input field, with the client IP appended
     * by default so one bad actor can't lock out every user of that value.
     */
    public function byField(string $field, bool $withIp = true): self
    {
        $this->specs[$this->requireWindow()]['key'] = static function (Request $request) use ($field, $withIp): string {
            $value = mb_strtolower((string) $request->input($field));

            return $withIp ? $value . '|' . $request->ip() : $value;
        };

        return $this;
    }

    /**
     * Key by a session value. Guarded — returns an empty key on a sessionless
     * request instead of throwing.
     */
    public function bySessionKey(string $sessionKey): self
    {
        $this->specs[$this->requireWindow()]['key'] = static fn (Request $request): string => $request->hasSession()
            ? (string) $request->session()->get($sessionKey)
            : '';

        return $this;
    }

    public function byUser(): self
    {
        $this->specs[$this->requireWindow()]['key'] = static fn (Request $request): string => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        return $this;
    }

    /**
     * Attach a 429 response callback to the most-recent window.
     */
    public function response(Closure $callback): self
    {
        $this->specs[$this->requireWindow()]['response'] = $callback;

        return $this;
    }

    /**
     * Full escape hatch: resolve the limit(s) entirely from your own callback,
     * bypassing the specs above.
     *
     * @param Closure(Request): (Limit|array<int, Limit>) $callback
     */
    public function using(Closure $callback): self
    {
        $this->using = $callback;

        return $this;
    }

    // ── resolution (called from the boot wiring) ─────────────────────────

    /**
     * @return Limit|array<int, Limit>
     */
    public function resolve(Request $request): Limit|array
    {
        if ($this->using instanceof Closure) {
            return ($this->using)($request);
        }

        if ($this->specs === []) {
            return Limit::none();
        }

        $limits = array_map(fn (array $spec): Limit => $this->buildLimit($spec, $request), $this->specs);

        return count($limits) === 1 ? $limits[0] : array_values($limits);
    }

    // ── internals ────────────────────────────────────────────────────────

    /**
     * @param int|Closure(Request): int $attempts
     */
    private function addWindow(string $kind, int $decay, int|Closure $attempts): self
    {
        $this->specs[] = [
            'attempts' => $attempts,
            'kind' => $kind,
            'decay' => $decay,
            'key' => null,
            'response' => null,
        ];

        return $this;
    }

    private function requireWindow(): int
    {
        if ($this->specs === []) {
            throw new InvalidArgumentException(
                'Call a window method (perMinute/perHour/…) before by()/response() on a RateLimiterDefinition.',
            );
        }

        return array_key_last($this->specs);
    }

    /**
     * @param array{attempts: int|Closure(Request): int, kind: string, decay: int, key: ?Closure(Request): string, response: ?Closure} $spec
     */
    private function buildLimit(array $spec, Request $request): Limit
    {
        $attempts = $spec['attempts'];
        $max = $attempts instanceof Closure ? (int) $attempts($request) : $attempts;

        $limit = match ($spec['kind']) {
            'none' => Limit::none(),
            'second' => Limit::perSecond($max),
            'minutes' => Limit::perMinutes($spec['decay'], $max),
            'hour' => Limit::perHour($max),
            'day' => Limit::perDay($max),
            default => Limit::perMinute($max),
        };

        if ($spec['key'] instanceof Closure) {
            $limit = $limit->by(($spec['key'])($request));
        }

        if ($spec['response'] instanceof Closure) {
            return $limit->response($spec['response']);
        }

        return $limit;
    }
}

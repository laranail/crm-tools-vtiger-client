<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;

/**
 * Typed accessor for a single named guard.
 *
 * Named `AuthHelper` (not `Auth`) to avoid colliding with the framework's `Auth`
 * facade. It wraps the resolved {@see Guard} so callers get a typed `user()` /
 * `id()` / `email()` surface for one guard without re-resolving it each call.
 */
final readonly class AuthHelper
{
    private function __construct(
        private string $guard,
        private Guard $auth,
    ) {}

    /**
     * Build an accessor for the given guard.
     *
     * @throws InvalidArgumentException when no guard name is supplied
     */
    public static function for(string $guard, ?AuthFactory $factory = null): self
    {
        if (trim($guard) === '') {
            throw new InvalidArgumentException('A guard name is required.');
        }

        $factory ??= app(AuthFactory::class);

        return new self($guard, $factory->guard($guard));
    }

    /**
     * Alias of {@see for()} — build an accessor for the given (or default) guard.
     */
    public static function authHelper(?string $guard = null): self
    {
        return self::for($guard ?? ToolkitConfig::string('auth.defaults.guard', 'web'));
    }

    /**
     * The guard name this accessor is bound to.
     */
    public function guard(): string
    {
        return $this->guard;
    }

    /**
     * The underlying guard instance.
     */
    public function auth(): Guard
    {
        return $this->auth;
    }

    /**
     * The currently authenticated user for this guard, if any.
     */
    public function user(): ?Authenticatable
    {
        return $this->auth->user();
    }

    /**
     * The authenticated user's identifier, or null when unauthenticated.
     */
    public function id(): int|string|null
    {
        return $this->auth->id();
    }

    /**
     * The authenticated user's `email` attribute, or null when unavailable.
     */
    public function email(): ?string
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        $email = data_get($user, 'email');

        return $email === null ? null : Cast::toString($email);
    }

    /**
     * The authenticated user's `username` attribute, falling back to `email`,
     * or null when unavailable.
     */
    public function username(): ?string
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        $username = data_get($user, 'username') ?? data_get($user, 'email');

        return $username === null ? null : Cast::toString($username);
    }

    /**
     * Whether a user matching `$key => $value` exists in this guard's user
     * provider. Uses the provider's own `retrieveByCredentials()` lookup (which
     * builds the `where` query for the non-secret credential).
     *
     * Requires a **stateful** (session) guard: only those are gated in, so a
     * token/request guard — or any guard without a resolvable `UserProvider` —
     * returns `false`.
     */
    public function userExists(mixed $value, string $key = 'id'): bool
    {
        if (!$this->auth instanceof StatefulGuard) {
            return false;
        }

        $provider = $this->auth->getProvider();

        if (!$provider instanceof UserProvider) {
            return false;
        }

        return $provider->retrieveByCredentials([$key => $value]) !== null;
    }

    /**
     * Whether a user is authenticated for this guard.
     */
    public function check(): bool
    {
        return $this->auth->check();
    }
}

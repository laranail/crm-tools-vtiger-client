<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Toolkit\Macros\MacroableModels;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Security\Passphrase;
use Simtabi\Laranail\Toolkit\Modules\Security\Password;
use Simtabi\Laranail\Toolkit\Modules\Security\Token;
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationContextServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RateLimiterServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SchedulerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SettingsStoreInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ValidationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\ModelService;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;
use Simtabi\Laranail\Toolkit\Support\RuntimeConfigurator;

/**
 * Unified, typed entry point to the toolkit's feature modules.
 *
 * Replaces the legacy 48-method `Laranail` service-locator with a small fluent
 * object: resolve a module's service through the container (deferred providers
 * boot on demand) and chain from there — e.g. `Toolkit::avatar()->setName(...)`.
 */
class ToolkitManager
{
    /** Active guard override for the user accessors (set via {@see withGuard()}). */
    private ?string $activeGuard = null;

    public function __construct(
        private readonly Application $app,
    ) {}

    public function archiver(): ArchiverServiceInterface
    {
        return $this->app->make(ArchiverServiceInterface::class);
    }

    /**
     * Request-scoped route helpers (current name, parameters, active checks).
     */
    public function route(): RouteServiceInterface
    {
        return $this->app->make(RouteServiceInterface::class);
    }

    /**
     * View-layer validation helpers (e()-escaped error/old-input output).
     */
    public function validation(): ValidationServiceInterface
    {
        return $this->app->make(ValidationServiceInterface::class);
    }

    /**
     * Session / query-string filter-key helpers (stateful cookie/session write).
     */
    public function session(): SessionServiceInterface
    {
        return $this->app->make(SessionServiceInterface::class);
    }

    /**
     * File-name / size inspection plus path-guarded, exception-safe filesystem
     * probes (the primary, injectable file domain).
     */
    public function file(): FileServiceInterface
    {
        return $this->app->make(FileServiceInterface::class);
    }

    /**
     * Read-only system / runtime introspection (PHP, memory, composer, SAPI).
     */
    public function system(): SystemServiceInterface
    {
        return $this->app->make(SystemServiceInterface::class);
    }

    /**
     * Eloquent model helpers (resolved by its concrete class — no contract).
     */
    public function model(): ModelService
    {
        return $this->app->make(ModelService::class);
    }

    /**
     * HTTP client configuration builder (seeded from laranail.toolkit.http.*).
     */
    public function http(): HttpConfigurationServiceInterface
    {
        return $this->app->make(HttpConfigurationServiceInterface::class);
    }

    /**
     * Guard-aware authentication helpers (typed accessor over native auth()).
     */
    public function auth(): AuthenticationContextServiceInterface
    {
        return $this->app->make(AuthenticationContextServiceInterface::class);
    }

    /**
     * Swap the guard the user accessors resolve against, returning a clone so a
     * swap is scoped and never mutates the shared manager:
     * `Toolkit::withGuard('admin')->userOrFail()`. A per-call `$guard` argument
     * still wins; pass `null` to fall back to the configured default guard.
     */
    public function withGuard(?string $guard): self
    {
        $clone = clone $this;
        $clone->activeGuard = $guard;

        return $clone;
    }

    /**
     * The currently authenticated user — a guard-aware, null-safe accessor.
     *
     * Improves on a hard-coded `auth()->user()` / `App\Models\User` helper: it is
     * multi-guard (pass `$guard`, swap via {@see withGuard()}, or configure
     * `laranail.toolkit.auth.default_guard`), never assumes the model lives at a
     * fixed FQCN, and returns null when there is no authenticated user. For a
     * statically-inferred concrete type use {@see self::userAs()}; to require a
     * user use {@see self::userOrFail()}.
     */
    public function user(?string $guard = null): Authenticatable|Model|null
    {
        return $this->auth()->getUser($this->resolveGuard($guard));
    }

    /**
     * The authenticated user, statically typed to the given model class.
     *
     * `Toolkit::userAs(User::class)` is inferred by PHPStan/IDEs as `?User` — the
     * type safety of Laravel PR laravel/laravel#6582 without hard-coding the model
     * or breaking multi-guard apps.
     *
     * @template TUser of Authenticatable
     *
     * @param class-string<TUser> $model
     *
     * @return TUser|null
     */
    public function userAs(string $model, ?string $guard = null): ?Authenticatable
    {
        $user = $this->user($guard);

        return $user instanceof $model ? $user : null;
    }

    /**
     * The authenticated user, or throw — for call sites that require one.
     *
     * Throws Laravel's own {@see AuthenticationException} so the framework
     * handler does the right thing per request type: a redirect to the login
     * route for web requests and a `401` for JSON/API — the same behaviour as the
     * `auth` middleware. (The toolkit's own exception rendered a `500` on web.)
     *
     * @throws AuthenticationException when no user is authenticated on the guard
     */
    public function userOrFail(?string $guard = null): Authenticatable|Model
    {
        $resolved = $this->resolveGuard($guard);

        return $this->auth()->getUser($resolved) ?? throw new AuthenticationException(
            'Unauthenticated.',
            $resolved !== null ? [$resolved] : [],
        );
    }

    /**
     * Resolve the guard for a user accessor: an explicit `$guard` wins, then a
     * {@see withGuard()} swap, then the configured default (null = framework).
     */
    private function resolveGuard(?string $guard): ?string
    {
        return $guard ?? $this->activeGuard ?? $this->defaultAuthGuard();
    }

    /**
     * The configured default guard for the user accessors (null = framework default).
     */
    private function defaultAuthGuard(): ?string
    {
        $guard = ToolkitConfig::string('laranail.toolkit.auth.default_guard', '');

        return $guard !== '' ? $guard : null;
    }

    /**
     * Livewire component helpers (key generation, registration support).
     */
    public function livewire(): LivewireServiceInterface
    {
        return $this->app->make(LivewireServiceInterface::class);
    }

    /**
     * Unified cache surface: tag-aware data cache (get/put/remember/forget,
     * namespaced keys) plus framework cache maintenance (clear/optimize).
     */
    public function cache(): CacheRepositoryInterface
    {
        return $this->app->make(CacheRepositoryInterface::class);
    }

    /**
     * Per-model macro registry — `Builder::macro()` narrowed to one model.
     */
    public function macroableModels(): MacroableModels
    {
        return $this->app->make(MacroableModels::class);
    }

    /**
     * A fresh PHP runtime/INI configurator (memory, timeout, debug-tool toggles;
     * `apply`/`scope`/`restore`). Returns a new builder each call — it snapshots
     * INI state on construction.
     */
    public function runtime(): RuntimeConfigurator
    {
        return RuntimeConfigurator::make();
    }

    /**
     * Structured application logger (channel/level helpers over the log stack).
     */
    public function log(): LoggerServiceInterface
    {
        return $this->app->make(LoggerServiceInterface::class);
    }

    /**
     * Runtime settings store (dynamic, persisted-at-runtime key/value JSON).
     */
    public function settings(): SettingsStoreInterface
    {
        return $this->app->make(SettingsStoreInterface::class);
    }

    /**
     * Named-profile rate limiter (attempts/decay over the cache store).
     */
    public function rateLimiter(): RateLimiterServiceInterface
    {
        return $this->app->make(RateLimiterServiceInterface::class);
    }

    /**
     * Task scheduler helpers (cron/interval registration support).
     */
    public function scheduler(): SchedulerServiceInterface
    {
        return $this->app->make(SchedulerServiceInterface::class);
    }

    /**
     * A fresh CSPRNG secure-token / OTP-code builder (signed or unsigned).
     */
    public function token(): Token
    {
        return Token::unsigned();
    }

    /**
     * A fresh random-password builder (defaults to the `strong` preset).
     */
    public function password(): Password
    {
        return Password::strong();
    }

    /**
     * A fresh EFF-diceware passphrase builder (defaults to the `memorable` preset).
     */
    public function passphrase(): Passphrase
    {
        return Passphrase::memorable();
    }
}

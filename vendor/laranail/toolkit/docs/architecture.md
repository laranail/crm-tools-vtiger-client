# Architecture

The toolkit favours small, contract-bound pieces over a single god-class. The
root `ToolkitServiceProvider` wires everything; feature modules are isolated and
deferred so they can later be extracted into stand-alone packages (as the
notifications module already was — it now lives in `laranail/notifications`).

## Source layout

The layout is **flat and feature-first**: cross-cutting glue sits at the top of
`src/`, and every feature is a single folder under `src/Modules/` with its files
directly inside it (a sub-folder only for a genuinely multi-file group such as
the per-driver LLM folders).

```
src/
├── Providers/
│   ├── ToolkitServiceProvider.php          # the one entry point
│   └── BladeServiceProvider.php            # custom-only Blade directives
├── Facades/Toolkit.php                     # unified entry facade
├── ToolkitManager.php                      # Toolkit::archiver()/cache()/...
├── Commands/MakeCrud.php                   # make-crud (extends the laranail/console base)
├── Http/Controllers/CrudController.php     # abstract base controller
├── Services/                               # injectable, interface-backed services (File, System,
│                                           #   Cache, Log, SettingsStore, RateLimiter, Scheduler, ...)
├── Macros/                                 # grouped macro providers + MacroServiceProvider
├── Traits/                                 # ApiResponse, Auditable, FilePathGuard, ...
├── Support/                                # pure/static helpers: ApiResponder, Cast, Config, AuthHelper,
│   │                                       #   Environment, CollectionFilter, QueryParameters,
│                                           #   FeatureToggle, RequirementsDiagnostics, Username, ...
├── Observers/Observer.php                  # abstract model observer base
├── Enums/{LogLevel, CacheAction}
├── Rules/RejectCommonPasswords.php
├── Helpers/Helper.php                       # static PURE-function facade (arrays/strings/dates/geo/console); Concerns/InteractsWith* traits
                                             #   (file/system domains moved to injectable Services/*)
└── Modules/                                # self-contained feature modules (flat inside)
    ├── Archiver/      ArchiverService, ArchiveManager, Zip/Tar/TarGz/Extractor, Archiver facade, provider
    ├── Eventing/      Events/{Event (abstract event base), CacheEvents}, Listeners/Listener (abstract listener base)
    ├── Livewire/      LivewireServiceProvider, component registration
    ├── Security/      Token, Password, Passphrase (CSPRNG generators), SecurityData (reads config('laranail.toolkit.security'), framework-free fallback), AccessLog/{AccessLogMiddleware, AccessLog (model)}
    └── LLM/           LLMProviderInterface, Claude/, Gemini/, OpenAI/, RetriesHttpRequests, LLM facade, LLMServiceProvider
```

> The command base (`Command` + `SupportsNamespacedNames`) is **not** local — it
> comes from [`laranail/console`](https://opensource.simtabi.com/console/) `^1.0`,
> the org-canonical command base. All three toolkit commands — `MakeCrud`,
> `IdeHelperMacros`, `Tidy` — extend it and use its **full
> feature set**: the fluent `$this->consoleWriter()` (context statuses
> success/error/warning/info/note, styling, emoji) and the `$this->services`
> lifecycle (`performance`, `signals`, `interaction`, `logger`, `error`,
> `metadata`, `display`). The heavy `Tidy` command makes its destructive sweep
> **signal-safe** (`signals()->shouldKeepRunning()`), confirms through
> `interaction()->confirmAction()` (safe default in non-interactive mode), and
> captures failures via the **auto-redacting** `error()->logError()` — so
> credentials never reach a log channel. The existing security hardening
> (FilePathGuard storage confinement, `db` action gating) is untouched.

## Adding a feature / tool / module

The layout is designed so a new feature is a drop-in folder:

1. Create `src/Modules/<Name>/` with `<Name>Service.php`, `<Name>ServiceInterface.php`,
   a `<Name>Facade.php`, and a deferred `<Name>ServiceProvider.php` (plus DTOs/enums
   as needed). Keep a sub-folder only for a genuinely multi-file group.
2. Add the provider to the `CHILD_PROVIDERS` constant in
   `ToolkitServiceProvider`, which registers each one in `register()`.
3. (Optional) add a `Toolkit::<name>()` accessor on `ToolkitManager` and a Laravel
   alias in `composer.json` `extra.laravel.aliases`.

## Deferred feature modules

Each module provider:

- **is deferred** (`DeferrableProvider`) — services boot only when first resolved,
  keeping the framework boot lean;
- **binds by interface** (e.g. `ArchiverServiceInterface` → `ArchiverService`);
- **registers a string alias** (`laranail.<module>`) and a Facade.

| Module | Contract | Alias | Facade |
|--------|----------|-------|--------|
| Archiver | `ArchiverServiceInterface` | `laranail.archiver` | `Archiver` |
| LLM | `LLMProviderInterface` | `laranail.llm` | `LLM` |

Resolve any module by its contract (preferred, for testability), its own facade,
or the unified `Toolkit` facade (`Toolkit::archiver()`, …).

### The `Toolkit` facade accessors

`Facades\Toolkit` (proxying `ToolkitManager`) fronts every module **and** the
injectable core services with typed `@method` accessors. Alongside the module
accessors (`archiver()`, `cache()`,
`livewire()`) and the security generators (`token()`, `password()`,
`passphrase()`), it exposes the core services — including the **five service
accessors**:

| Accessor | Returns (contract) |
|---|---|
| `Toolkit::cache()` | `Services\Contracts\CacheRepositoryInterface` |
| `Toolkit::log()` | `Services\Contracts\LoggerServiceInterface` |
| `Toolkit::settings()` | `Services\Contracts\SettingsStoreInterface` |
| `Toolkit::rateLimiter()` | `Services\Contracts\RateLimiterServiceInterface` |
| `Toolkit::scheduler()` | `Services\Contracts\SchedulerServiceInterface` |

plus `file()`, `system()`, `session()`, `route()`,
`validation()`, `http()`, `auth()`, `model()`, the guard-aware user accessors
`user()` / `userAs()` / `userOrFail()` / `withGuard()`
([authenticated user](../auth.md)), and
`runtime()` ([runtime configurator](../runtime-configurator.md)). (The pure static
`Helper::` methods are intentionally **not** on the facade — see
[helpers](helpers.md).)

## Eager vs. deferred

Two things must register eagerly and so run in `boot()`, not deferred:

- **Macros** — `MacroServiceProvider` registers all Str/Arr/Collection/Query/
  Request/Factory macros globally.
- **Blade directives** — `BladeServiceProvider` registers custom directives.

The middleware alias `laranail-toolkit.access-log`, the `reject_common_passwords` validator, and
the `php artisan about` diagnostics are also wired in `boot()`.

## Exceptions

All toolkit exceptions extend `Exceptions\LaranailException` — a structured base
carrying context, metadata, a user-facing message and an optional HTTP status.
`Exceptions\AuthenticationException` adds auth factories (`missingGuard()`,
`invalidGuard()`, `unauthenticated()`) and a `render()` method that emits a JSON
`401` envelope for JSON requests (Laravel 11+ calls `render()` directly on the
exception, so no `Handler` subclass is needed) while deferring to the default
rendering for web requests.

Because L11+ apps own exception configuration in `bootstrap/app.php`, the toolkit
does **not** ship a competing handler. Instead the
`Exceptions\Concerns\RendersApiExceptions` trait exposes the reusable logic
(throttled Slack alerts + a JSON `405` renderer) as opt-in registrars:

```php
use Illuminate\Foundation\Configuration\Exceptions;
use Simtabi\Laranail\Toolkit\Exceptions\Concerns\RendersApiExceptions;

->withExceptions(function (Exceptions $exceptions): void {
    RendersApiExceptions::register($exceptions);
    // or just one: ::registerSlackReporter(...) / ::registerMethodNotAllowedRenderer(...)
})
```

## LLM provider selection

`Modules\LLM\LLMProviderInterface` is bound to a single driver chosen at
resolution time from `config('laranail.toolkit.llm.default_provider')` — `openai`
(default), `claude`, or `gemini`. See [LLM providers](modules/llm.md).

## Self-contained provider (no `laranail/package-tools`)

The `ToolkitServiceProvider` is built on plain `Illuminate\Support\ServiceProvider`
primitives — the toolkit **does not depend on `laranail/package-tools`**. It is the
foundational "Swiss-army" library that other laranail packages build on, so the
dependency arrow points the other way. The provider reproduces the declarative
lifecycle itself: `register()` merges each `config/*.php` under its dotted
`laranail.toolkit.*` key (with a published-override bridge that deep-merges an
edited `config/laranail/…` file back over the key), registers the eager + deferred
child providers, and wires the container bindings; `boot()` loads
views/translations/migrations, the commands, the route-middleware aliases, the
`reject_common_passwords` validator, the `php artisan about` section, the
cache-events listener, and (in console) the publish groups. Publish tags are
unchanged (`laranail::toolkit-{config,views,translations,migrations,stubs}`).

## Migration / removal record

This package was ported from the pre-1.0 `Simtabi\Laranail` monolith, keeping
only the genuine delta over Laravel 13 / PHP 8.4–8.5 natives — native-duplicative,
consolidated, or out-of-scope symbols were dropped, and the notification and
database/UUID tooling moved to `laranail/notifications` and `laranail/db-tools`.
A regression test (`tests/Regression/ApiSurfaceTest`) enforces that nothing is lost
unplanned. The `CacheService` maintenance surface (clear/rebuild/optimize) — once
deferred to native artisan and dropped as the legacy `CacheServiceInterface` — was
folded back onto the unified cache service, and the INI `RuntimeConfigurator` was
added alongside it.

Two services have since left, following the precedent captcha set. The runtime
`ConfigManager` went to [`laranail/package-tools`](https://github.com/laranail/package-tools),
which already owned the config resolver, merger, validator and pattern resolver —
config machinery belongs with the package-authoring runtime, and keeping a second
`ConfigMerger` here meant two classes with the same short name and the same
four-method API drifting apart. `PythonApiService` became
[`laranail/python`](https://github.com/laranail/python), where the HTTP client is
one transport of a bidirectional bridge rather than the whole thing. See
[UPGRADING.md](../UPGRADING.md).

[← Docs index](../README.md#documentation)

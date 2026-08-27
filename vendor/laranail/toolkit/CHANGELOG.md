# Changelog

All notable changes to `laranail/toolkit` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-15

### Changed

- **Route-middleware aliases are vendor-scoped.** The router's alias map is flat, so a second package
  registering `api.request` does not conflict — it silently replaces this one, and the damage
  surfaces as the wrong middleware running on a route nobody touched. `access.log` and
  `email.obfuscate` are names an application would plausibly pick for itself.

  | Was | Now |
  |---|---|
  | `access.log` | `laranail-toolkit.access-log` |
  | `api.request` | `laranail-toolkit.api-request` |
  | `api.response` | `laranail-toolkit.api-response` |
  | `email.obfuscate` | `laranail-toolkit.email-obfuscate` |

  A dot after the prefix rather than `::`, and that is forced rather than stylistic: Laravel resolves
  an alias with `explode(':', $name, 2)` so `throttle:60,1` can carry parameters, so
  `laranail::toolkit.api-response` would resolve as the middleware `laranail` with the parameter
  `:toolkit.api-response`. The dot form keeps `->middleware('laranail-toolkit.api-response:meta,data')`
  working.

### Fixed

- **The translation namespace was `laranail/toolkit`, with a slash.** That is a namespace, not a
  path: Laravel publishes to `lang/vendor/{namespace}`, so the files landed one directory deeper
  than the loader looks for them — every published translation override was silently ignored while
  the packaged default kept answering. It is `laranail-toolkit` now, matching the view namespace,
  and `vendor:publish --tag=laranail::toolkit-translations` writes to `lang/vendor/laranail-toolkit`.

### Removed

- **`Modules\Atlas`, `Modules\Avatar` and `Modules\Gravatar`** — extracted to
  [`laranail/atlas`](https://opensource.simtabi.com/documentation/laranail/atlas/) and
  [`laranail/avatar`](https://opensource.simtabi.com/documentation/laranail/avatar/). See
  [UPGRADING.md](UPGRADING.md); every removed FQCN is recorded in
  `tests/Fixtures/Legacy/removed-symbols.json`.

  **3,674 lines of `src/` and two dependencies leave with them** — `rinvex/countries` (~17 MB) and
  `intervention/image` had exactly one consumer each.

  Three bundled fonts go too, and two of them for licensing reasons rather than size:
  `FreeSerif.ttf` is GPL-3.0, whose font exception covers documents that *embed* the font rather
  than redistribution of the file, and `msyh.ttf` is not Microsoft YaHei despite the filename but
  Droid Sans Fallback carrying an Ascender Corporation EULA reading *"you may not copy this font
  software"*. Neither belonged in an MIT package.

- **`Helpers\Concerns\InteractsWithGeo`** and `Helper::distanceBetween()` — moved to
  `laranail/atlas`, where the result is a `Distance` carrying its own unit rather than a float whose
  unit was set by a string argument several lines earlier.

- **`Traits\HasAvatar`** — moved with the Avatar module.

### Security

- **`clearThirdPartyCache()` recursively deleted whatever a config key pointed
  at.** The method is public and takes a config *key*, so any path that key held
  was handed straight to `deleteDirectory()` — no containment check, no symlink
  check, no dry run. `filesystems.disks.local.root`, `view.compiled`, or simply a
  mistyped key would each have emptied a directory the method has no business
  touching.

  Both shipped callers — `purifier.cachePath` and `debugbar.storage.path` — name
  somewhere inside `storage/`, so that is now the boundary: a path outside it is
  refused and logged rather than cleared. The storage root itself is never
  clearable, only things under it, and a symlink is refused outright because
  following one would empty somewhere the check never approved.

### Removed

- **`Toolkit::config()` — the runtime `ConfigManager` moved to `laranail/package-tools`.**
  That package already owned the config file resolver, merger, validator and
  pattern resolver, so config machinery had two homes and two `ConfigMerger`
  classes with the same short name and the same four-method API. Worse, two
  container-resolvable services held **opposite** semantics over `config()` —
  `ConfigService::merge()` yields to the app, `ConfigManager::override()` does
  not — and provider boot order silently decided which won.

  `Services\ConfigManager`, `Services\Contracts\ConfigManagerInterface`,
  `Support\ConfigMerger` and `Exceptions\ConfigException` are gone;
  `ConfigException` had exactly one thrower, so catching it was already dead code.

- **`Toolkit::pythonApi()` — the Python client moved to `laranail/python`.** The
  HTTP client was a third of the problem; the new package is a bidirectional
  bridge with a hardened local-process transport and HMAC-signed inbound
  callbacks. `Services\PythonApiService`, its contract,
  `Services\PythonServiceDefinition` and `Exceptions\PythonApiException` are
  gone, as is the `laranail.toolkit.python` config block — env var names are
  unchanged, so an existing `.env` keeps working.

  Both are `suggest`-ed rather than required. See
  [UPGRADING.md](UPGRADING.md) for the two behaviour changes that came with the
  moves.

### Added

- **`Macros\MacroableModels`** — a macro registry keyed by model class, reachable
  as `Toolkit::macroableModels()`. `Builder::macro()` is global: register
  `whereActive` and it exists on every model's builder, including the ones where
  it makes no sense. This narrows it — a macro is registered *for a model*, and
  calling it on another fails the way an undefined method should.

  A macro's closure is bound to the model instance **and scoped to its class**,
  so `$this->someProtectedThing` reaches the real member rather than falling
  through `Model::__get()` to an attribute lookup that quietly returns null. That
  is a deliberate difference from a bare `Closure::bind($closure, $model)`, which
  keeps the closure's own scope, and it is what makes accessor-style macros work.

- **`Str::withoutBaseUrl()`** — strips the application's own base URL from a
  string, leaving a relative path. Both `config('app.url')` and `url('')` are
  stripped, because they disagree more often than you would like: `url('')` is
  the *current request's* root, so behind a proxy, on a secondary domain, or in a
  queue worker it is not the canonical app URL the content was stored with.
  Using either alone silently leaves the other's URLs absolute.

- **`FileService::filesInPath()`** — relative paths of the files under a
  directory, non-recursive by default, path-guarded and exception-safe like the
  other probes there.

### Changed — breaking

- **`Services\Contracts\FileServiceInterface` gains `filesInPath()`.** Anything
  implementing that contract directly must add the method. Consumers resolving
  it from the container are unaffected.

### Removed

- The `Captcha` module (`src/Modules/Captcha/`), its config file and the `Captcha` facade alias have
  been relocated to [`laranail/captcha`](https://github.com/laranail/captcha), which covers eleven
  providers, environment-scoped credentials, a database-backed settings store and edge bot
  management. `Toolkit::captcha()` is gone with it. See UPGRADING.md.

### Fixed

- **Documentation that still described the removed captcha module.**
  `docs/modules/captcha.md` was still shipping in full, and `architecture.md`,
  `configuration.md`, `installation.md` and `getting-started.md` all still
  referenced the module, its config file and `Toolkit::captcha()`.
  `architecture.md` also told you to register a child provider through
  `configurePackage()->hasChildProviders([...])`, a method that does not exist —
  it is the `CHILD_PROVIDERS` constant on `ToolkitServiceProvider`.

Initial public release. Folded in during the pre-stable phase:

### Added

- **Swappable auth guard** — `Toolkit::withGuard('admin')` returns a scoped clone
  whose `user()` / `userAs()` / `userOrFail()` resolve against that guard, without
  a per-call argument or mutating the shared manager. Resolution order: explicit
  per-call `$guard` → `withGuard()` swap → `config('laranail.toolkit.auth.default_guard')`
  → the framework default. See [docs/auth.md](docs/auth.md).

### Fixed

- **`Toolkit::userOrFail()`** throws Laravel's `Illuminate\Auth\AuthenticationException`,
  so an unauthenticated **web** request gets the framework's login redirect (and
  a JSON/API request a `401`) — matching the `auth` middleware — instead of a
  `500`.
- **`Toolkit::userAs()`** carries its generic through the facade `@method`, so
  `Toolkit::userAs(User::class)` is inferred as `?User` (not `?Authenticatable`).
- **`RequirementsDiagnostics` disk-space tests** are environment-robust — they
  pin the thresholds so they no longer fail on a low-free-space runner.
- Corrected the `AuthHelper::userExists()` doc (it is gated to stateful/session
  guards) and the `auth.user_model` config comment (a reserved hint, not read at
  runtime — the `userAs()` generic provides the IDE typing).

[Unreleased]: https://github.com/laranail/toolkit/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/laranail/toolkit/releases/tag/v0.1.0

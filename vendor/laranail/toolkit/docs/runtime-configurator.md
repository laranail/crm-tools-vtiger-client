# Runtime configurator

`Support\RuntimeConfigurator` is a chainable API for adjusting PHP runtime/INI
settings during heavy operations — memory limit, execution time, arbitrary INI
directives, and disabling debugging tools (Telescope, Xdebug, Clockwork, Debugbar).
Get a fresh builder via `Toolkit::runtime()` or `RuntimeConfigurator::make()`.

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;

Toolkit::runtime()
    ->memory('1G')
    ->timeout(0)
    ->disableTelescope()
    ->apply();
```

Original INI values are captured on construction, so the configurator can restore
them. `Toolkit::runtime()` returns a **new** builder each call (it snapshots INI
state), like the security generators.

## Scope a change to a callback

`scope()` applies the settings, runs the callback, and restores in a `finally` —
apply/restore are symmetric (Xdebug is re-enabled, and restore reuses the same
`set_time_limit` / `error_reporting` handling as apply):

```php
$rows = Toolkit::runtime()
    ->forBatchProcessing()               // 2G, no timeout, all debugging off
    ->scope(fn () => $importer->run());
```

## Presets

| Factory | Configures |
|---------|------------|
| `make()` | Empty builder. |
| `forQueueJob()` | 1G memory, no timeout, Telescope off. |
| `forBatchProcessing()` | 2G memory, no timeout, all debugging off. |
| `forImport()` | 1G memory, 30-minute timeout, Telescope off. |
| `forExport()` | 1G memory, 15-minute timeout, Telescope off. |

## Config-driven profiles

The presets above live in code, but you can drive the configurator entirely from
the **`laranail.toolkit.runtime`** config block — no code change needed to tune
memory, timeouts, INI directives or which debug tools to disable. Publish the
config, edit the values (or set the `LARANAIL_RUNTIME_*` env vars), and build from
it:

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;
use Simtabi\Laranail\Toolkit\Support\RuntimeConfigurator;

// Apply the `defaults` + a named profile from config, then run scoped:
RuntimeConfigurator::fromConfig('import')->scope(fn () => $importer->run());

// Or load config into an existing builder and keep chaining:
Toolkit::runtime()->usingConfig('batch')->memory('4G')->apply();
```

| Method | Notes |
|--------|-------|
| `RuntimeConfigurator::fromConfig(?string $profile = null)` | New builder seeded from config: `defaults` + `ini` + `disable_tools`, then the named `$profile` (or `runtime.default_profile`). |
| `usingConfig(?string $profile = null)` | Same, loaded into the current builder (returns `self`). |

Layering order: `defaults` → `ini` → `disable_tools` → the named `profile`
(profiles win). A `null` value in `defaults`/`ini` is **skipped**, so only the
directives you explicitly set are applied.

Set **`runtime.apply_on_boot = true`** to have the toolkit apply the
`default_profile` (or just the `defaults`) automatically at boot — a one-line way
to enforce global INI. It mutates PHP INI for every request/command, so it is
opt-in (default off); note that pairing it with a `queue`/`batch` profile
(`max_execution_time => 0`) removes the web request time limit globally — prefer
applying those per-job. An unknown profile name logs a warning and applies only
the defaults. See [configuration](configuration.md#laranailtoolkitruntime) for
the full block and the shipped profiles (`queue`, `batch`, `import`, `export`,
`uploads`).

> **php.ini-only directives.** `post_max_size`, `upload_max_filesize`,
> `max_input_time`, `max_input_vars`, `max_file_uploads`, `realpath_cache_size`
> and `realpath_cache_ttl` cannot be changed at runtime via `ini_set()` — set
> them in `php.ini`. If applied anyway (e.g. via the `uploads` profile or
> `forLargeUploads()`), they are recorded in `getFailedIniSettings()` rather than
> silently dropped.

## Builder methods

| Group | Methods (each returns `self`) |
|-------|-------------------------------|
| Memory | `memory(string)`, `memoryMb(int)`, `memoryGb(int\|float)`, `unlimitedMemory()` |
| Time | `timeout(int)`, `maxExecutionTime(int)`, `noTimeout()`, `timeoutMinutes(int)`, `timeoutHours(int)` |
| Errors | `errorReporting(int)`, `reportAllErrors()`, `reportErrorsOnly()`, `suppressErrors()`, `displayErrors(bool)` |
| Debug tools | `disableTelescope()`/`enableTelescope()`, `disableXdebug()`/`enableXdebug()`, `disableClockwork()`/`enableClockwork()`, `disableDebugbar()`/`enableDebugbar()`, `disableAllDebugging()` |
| INI / uploads | `set(string, mixed)`, `setMany(array)`, `realpathCacheSize(string)`, `realpathCacheTtl(int)`, `postMaxSize(string)`, `uploadMaxFilesize(string)`, `forLargeUploads(string = '100M')` |
| Conditional | `when(bool, callable, ?callable)`, `unless(bool, callable)`, `whenCli(callable)`, `whenWeb(callable)` |
| Logging | `withLogging(?string $channel = null)`, `withoutLogging()` |

## Lifecycle & introspection

| Method | Notes |
|--------|-------|
| `apply()` | Materialise the pending changes. Idempotent — a second call while applied is a no-op. |
| `scope(callable)` | Apply → run → restore (returns the callback result). |
| `restore()` | Restore captured originals and re-enable disabled tools. |
| `isApplied()` / `getPending()` / `getOriginal()` / `getDisabledTools()` / `get(string)` | State accessors. |
| `getFailedIniSettings()` | Directives the last `apply()` could **not** set at runtime (see the php.ini note below). |

Static helpers: `setMemory(string)`, `setTimeout(int)`, `isCli()`,
`hasTelescopeInstalled()`, `hasXdebugLoaded()`, `hasDebugbarInstalled()`,
`hasClockworkInstalled()`.

> Memory **introspection** (usage, peak, formatted sizes) is intentionally not
> duplicated here — read it from the system service:
> `Toolkit::system()->memoryUsage()`.

---

[← Docs index](../README.md#documentation)

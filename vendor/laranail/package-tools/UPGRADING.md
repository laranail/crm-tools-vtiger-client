# Upgrading

## Boot no longer deletes published assets

Booting a package used to delete the destination of every publish tag marked
`cleanBeforePublish`. It was gated only on `runningInConsole()`, and **every**
console command boots every provider — so `php artisan route:list`, `migrate`,
`queue:work` or a `tinker` session silently removed the published assets of any
package that had asked for a clean, and they stayed gone until someone
re-published.

Boot now records the request instead of acting on it. Nothing is deleted during
boot, ever.

```diff
- // PackageServiceProvider::bootPackageCustomPublishes()
- File::deleteDirectory($destination);   // on every console command
+ $registry->record($tag, $package, $paths, cleanable: true);
```

### What you need to do

**Nothing, if you never used `cleanBeforePublish` / `clean: true`.**

If you did, be aware that the clean is currently *recorded but not performed* —
`vendor:publish` has no hook for it. Until `laranail::package-tools.publish`
lands, republish over a dirty destination as you would with any other Laravel
package, or clear it yourself:

```php
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;

$entry = app(PublishTagRegistry::class)->get('your-tag');
$entry?->destinations();   // what the package publishes to
```

That is a deliberate trade: a declaration that no longer takes effect is a far
smaller problem than one that deletes files during unrelated commands.

### New API

`Services\Asset\PublishTagRegistry` (a singleton) records every publish tag a
laranail package registers, the package that owns it, and whether it asked for a
clean — `get()`, `all()`, `tags()`, `forPackage()`, `cleanable()`,
`isCleanable()`.

## 6.0 to 7.0

7.0 adopts the [failure-handling standard](docs/failure-handling.md): boot
failures are classified by **consequence (Critical vs Degradable)**, never by
environment, and are reported through the **central exception handler** (→
your monitoring), not a logfile.

### What changed

- **`FailurePolicy` API:** `rethrowing()` / `swallow()` → `run($work, $name,
  BootCriticality)` / `handle($e, $name, BootCriticality)` / `warn($subject,
  $context)`. If you called the old methods, migrate to `run()`/`handle()`.
- **`configDecorator` fails closed** (Critical by default). A decoration that
  throws now **crashes boot** unless you mark it Degradable:
  ```php
  use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
  $package->configDecorator(fn ($c) => …, BootCriticality::Degradable);
  ```
  Guard any decoration that reads runtime data it can't guarantee (e.g. an
  unmigrated database) with `rescue(...)`, or mark it Degradable.
- **Scheduling:** `scheduling.strict` / `PACKAGE_TOOLS_SCHEDULING_STRICT`
  **removed**. A bad cadence is Degradable everywhere — reported + skipped,
  other tasks still register. (`resilience.strict` / `PACKAGE_TOOLS_STRICT`
  was already removed in 6.0.)
- **Reporting:** boot failures now call `report()` (the exception handler →
  Sentry/Flare/…), so wire the throttle and — optionally — an internal
  `/health/boot` route + a CI gate. Snippets in
  [`docs/failure-handling.md`](docs/failure-handling.md).
- **`boot:health` doctor check** is always present in
  `laranail::package-tools.doctor` output.

### What did NOT change

The `PackageAction*` lifecycle events, per-package logging, the run tracker,
CLI commands, and `safelyRegisterComponent()` are unaffected — see the
"documented exemptions" in the standard.

## 5.0 to 6.0

6.0 corrects 5.0's boot-failure model. Instead of strict-in-dev /
lenient-in-prod for *all* boot wiring, failures are split by whether
swallowing leaves a **safe, working state**.

### What changed

- **Fail loud (every environment), wrapped in `PackageBootException`:**
  `useHttps` / `setLocale` closures, `registerRouteModel(fn () => …)`
  resolvers, closure `addSubscriber(...)`. These can't degrade safely —
  swallowing them boots a silently broken app — so they now always rethrow
  with a message naming the failing builder.
- **Log + skip (every environment):** `configDecorator`, boot-time seeder
  discovery, view composer/creator registration. These degrade to a valid
  state, so they stay non-fatal.
- **Removed:** `resilience.strict` config / `PACKAGE_TOOLS_STRICT` env, and
  `FailurePolicy::isStrict()` / `guard()`. Use `FailurePolicy::rethrowing()`
  (fail loud) or `FailurePolicy::swallow()` (degrade safe) if you called them
  directly.

### What you may need to do

1. **A `useHttps`/`setLocale`/route-model/subscriber closure that throws will
   now crash boot in production too** (it did in dev under 5.0). This is
   intentional — a broken one of these was silently misconfiguring your app.
   Fix the closure so it doesn't throw (guard its inputs, read config safely).
2. **If you set `PACKAGE_TOOLS_STRICT`**, remove it — it no longer exists.
3. **`configDecorator` / seeder-discovery / view-composer failures** now log
   and continue in every environment (5.0 rethrew them in dev). If you relied
   on the dev rethrow to catch these, watch the package logfile instead.

### What did NOT change

Scheduling keeps `scheduling.strict` (strict outside production, lenient in
production). Infrastructure that must never throw (logging, run tracker,
doctor checks, the `PackageActions` reporter, CLI commands, per-seeder
execution, `safelyRegisterComponent`) is unaffected.

## 4.x to 5.0

The 5.0 theme: **one resilience policy across the package — strict in
development, lenient in production.** Package-author boot wiring that
previously *always* logged-and-swallowed now **rethrows outside production**
so you catch misconfigurations before they ship.

### What changed

A failure in any of these boot-wiring sites now follows
`resilience.strict` (default: strict everywhere except production):

- `configDecorator(...)` closures
- runtime tweaks — `useHttps` / `setLocale` (closure / `*FromConfig` forms)
- route model closures — `registerRouteModel(fn () => …)`
- closure event subscribers — `event()->addSubscriber(fn ($events) => …)`
- boot-time seeder discovery (a malformed seeder file)
- safe view-composer / view-creator registration
- schedule configuration (already behaved this way; now unified)

In **development / CI / staging** these now **throw** (they are always logged
too). In **production** they are logged and skipped, exactly as before.

### What you may need to do

1. **A boot closure that legitimately tolerates missing runtime data** (e.g.
   a `configDecorator` reading a not-yet-migrated database) must guard its own
   access — wrap it in `rescue(...)`, check the condition, or otherwise handle
   the absent state. Previously such a throw was silently swallowed; now it
   surfaces in dev. This is intentional: it is a bug to catch.
2. **To restore the old always-lenient behaviour** everywhere, set
   `PACKAGE_TOOLS_STRICT=false` (or `resilience.strict => false`).
3. **To fail hard everywhere** (e.g. block CI on any misconfig), set
   `PACKAGE_TOOLS_STRICT=true`.

### What did NOT change

Infrastructure that must never throw is unaffected and stays unconditionally
safe: per-package logging, the seeder run tracker, doctor checks, the
`PackageActions` reporter, the built-in CLI commands, per-seeder **execution**
failures (`stopOnFailure()` + the action-event system), and
`safelyRegisterComponent()` (which collects errors into `getComponentErrors()`).
`scheduling.strict` still overrides strictness for scheduling specifically.

## 3.x to 4.0

The 4.0 theme: **one reusable action-lifecycle event system, plus fluent
provider builders.** It is largely additive — there is no hard API break to
remediate. Two behavioral notes:

### 1. The seeder failure log line moved (format change)

Failure logging now lives in `PackageActionReporter` (the single choke
point), not inline in the seeder executor. A failed seeder is still **always
logged at error**, but the line's message/context shape changed. If you
grep or parse package-tools failure logs, update the pattern. No code change
is required.

### 2. The migrator is decorated by default (console only, opt-out available)

To emit migration lifecycle events (Laravel provides none), package-tools
decorates the `migrator` when running in the console. It is composition-safe
— if another package already decorated the migrator, package-tools leaves it
alone and falls back to an event-based detector. Disable entirely with
`package-tools.migrations.failure_detection.enabled = false` (or
`PACKAGE_TOOLS_MIGRATION_FAILURE_DETECTION=false`).

### Everything else is additive

The new `PackageAction{Started,Succeeded,Failed}` family fires **alongside**
the existing seeder events (unchanged), gated by `events.lifecycle` /
`events.failures` (the failure *log* is never gated). The new fluent builders
(`useHttps`, `setLocale`, `paginator`, `mergesConfigDefaults`,
`configDecorator`, `registerGates`, `registerRouteGroup`,
`registerRouteModel`/`registerRouteBinding`, `event()`) are new surface — no
existing method changed signature.

## 2.x to 3.0

The 3.0 theme: **seeders never run on their own unless you opt in.**
Eight changes, three of which may need code edits.

### 1. Arbitrary seeder resolution no longer triggers package seeding

The 2.x resolver hook bound the abstract `Seeder` type, so resolving ANY
seeder — `db:seed --class=X`, a web request, even the executor's own
`make()` — ran every registered package bundle. In 3.0 only **root
seeders** trigger: `Database\Seeders\DatabaseSeeder` plus anything you
list in `package-tools.seeders.root_seeders`. If you relied on a custom
root seeder class, add its FQCN to that config key.

### 2. `loadSeedersFrom()` / `registerSeeder()` now actually register

Both were silent no-ops in 2.x (their state was never consumed). Audit
existing calls — those seeders WILL start running with `db:seed` after
the upgrade. `getSeederPaths()` / `getRegisteredSeeders()` are removed;
inspect `getPackageSeederDefinitions()` instead.

### 3. Executed seeders receive the container (behavioral)

`run()`-signature dependency injection and `$this->call()` now work in
package bundles, matching `db:seed`. Seeders that (unknowingly) relied
on NOT getting the container cannot exist — this only fixes crashes.

### 4. Opt-in autorun replaces "never automatic"

Nothing changes unless a bundle calls `autorunAfterMigrations()` /
`autorunNow()` (or the package calls `autorunSeeders()`) — then it runs
once after a successful `php artisan migrate`, console-only, gated (see
`docs/seeding.md`). Autorun is OFF in production and unit tests by
default.

### 5. Service constructor/signature changes

Only relevant if you constructed the internals directly:
`SeederManager` drops `Application` and gains `SeederAutorun`;
`SeederResolverHook::attach()` is variadic and the hook is attached once
by `PackageToolsServiceProvider`; `SeederExecutor::run()` gained an
optional `SeederExecutionMode` parameter.

### 6. Registry replacement warns

Registering two bundles under one key now raises an `E_USER_WARNING`
(replacement still wins). Use distinct keys per package.

### 7. `SeederManager::run()` marks the ledger

A manual `PackageSeeder::run()` followed by `db:seed` in the same
process no longer double-seeds. Call `PackageSeeder::resetRunState()`
if you intentionally re-run (multi-tenant loops).

### 8. New shipped config

`config/package-tools.php` merges as the `package-tools.*` namespace
(publish with `--tag=package-tools-config`). If your host app already
defines a `package-tools` config key for something else, rename one.

## 1.x to 2.0

Four behavior changes; everything else in 2.0 is additive.

### 1. Seeders no longer execute at package boot

`bootPackageSeeders()` ran every registered seeder at every app boot — DB
writes per request. In 2.0 all package seeders run at `db:seed` time via the
seeder resolver hook; the boot-time execution path is gone.

`hasPackageSeeders()` is now the definition-based API with db:seed-time
semantics:

```php
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;

// before (1.x — ran at boot)
$package->hasPackageSeeders('acme/blog', [BlogSeeder::class], 'Acme\\Blog', ['fire_events' => true]);

// after (2.0 — runs with the host's `php artisan db:seed`)
$package->hasPackageSeeders(
    AutoSeederDefinition::make('acme/blog')
        ->seeders([BlogSeeder::class])
        ->inNamespace('Acme\\Blog')
        ->options(['fire_events' => true]),
);
// or the simple shorthand
$package->hasPackageSeeders('acme/blog', [BlogSeeder::class]);
```

If you truly need seeders at boot, run them yourself in `packageBooted()`
via `PackageSeeder::seeders()->classes([...])->execute()` — an explicit,
visible decision instead of a hidden side effect.

### 2. Middleware registration is uniformly deferred

The enhanced middleware methods (`registerMiddlewareAlias(es)`,
`registerMiddlewareGroup(s)`, `addToMiddlewareGroup`,
`registerPrefixedMiddleware`) applied to the router at configure time. In
2.0 they store on the package like everything else and apply in
`bootPackageMiddleware()` — aliases, groups, and global middleware in one
boot step. Code relying on an alias existing DURING `configurePackage()`
must move that reliance to boot time.

### 3. `hasComponentNamespace()` now actually works

Pre-2.0 the registration was written to an array no boot step ever read —
the advertised API did nothing. It now registers through
`Blade::componentNamespace()` at boot. If you called it and shipped a
workaround (registering the namespace manually), remove the workaround or
you will register twice (harmless, but redundant).

### 4. Seeder registry entries are typed bundles

`SeederRegistry::get()`/`all()` return `SeederBundle` value objects instead
of shape-arrays. `register()` keeps its signature. Options are scoped per
bundle: one package's `disable_foreign_key_checks`/`fire_events`/
`parameters` no longer leak into other packages' runs (this was a bug),
and bundles execute in `priority` order (lower first, ties keep
registration order).

```php
// before
$registry->get('acme/blog')['seeders'];
// after
$registry->get('acme/blog')->seeders();
```

## 2.x point releases

2.1 (about sections), 2.2 (doctor definitions), and 2.3 (install
definitions) are additive — no code changes are required. Two behavior
changes are worth knowing about:

### Doctor checks no longer duplicate on double boots (2.2)

`DoctorService::register()` now replaces an entry when the same
(group, name) pair registers twice, instead of stacking duplicate report
rows. If your doctor output previously showed the same check more than
once (a provider booting twice), it now appears once — summary counts
shrink accordingly.

### Install publishing now actually publishes namespaced tags (2.3)

`publishConfigFile()` / `publishMigrations()` / `publishAssets()` (and
the new definition's `publishes()`) previously published only the legacy
`{short-name}-{tag}` tag — a silent no-op for every package whose
publishables are registered under the namespaced `vendor::pkg-{tag}`
form. Both tags are now attempted, so install commands that "ran but
copied nothing" will start publishing files. Remove any workaround that
called `vendor:publish --tag=vendor::pkg-config` manually after install.

---

[← Docs index](README.md#documentation)

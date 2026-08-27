# Changelog

All notable changes to `laranail/package-tools` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- **`php artisan laranail::package-tools.packages`** and the `PackageRegistry` behind it: every
  package built on `PackageServiceProvider`, what each one claimed, and whether any two claimed the
  same name.

  Laravel keeps view namespaces, translation namespaces, config keys and publish tags in flat global
  maps, so a second package claiming a key does not collide loudly — it silently replaces the first,
  and the failure surfaces far away as a missing view or the wrong file published. Nothing in the
  framework can answer that afterwards. `--collisions` exits non-zero, so it works as a CI gate.

  The report reads description, authors, licence, keywords and docs from each package's own
  `composer.json` rather than asking for them again — that file is the copy composer already forces
  an author to keep correct. `describedAs()`, `maintainedBy()`, `documentedAt()` and
  `withStability()` override it where a package wants to say something different at runtime.

  See [Package registry](docs/tools/package-registry.md).

### Changed

- **`laranail/console` is a suggestion, not a requirement.** It was reached by exactly one class out
  of roughly 270 -- `SeederConsoleFormatter`, which renders styled seeder output -- and this package
  is the base class for the whole family, so that one file put a console library into every
  application that installed anything built on `PackageServiceProvider`, whether or not it ever
  seeded anything.

  `PlainSeederConsoleFormatter` implements the same contract with no dependency, and the container
  binds whichever is available. Styled output where laranail/console is installed, plain lines where
  it is not. Verified against a real `--no-dev` install, not just a `class_exists` branch.

  **The `require` block now contains no `laranail/*` entry at all**, and a test asserts that, because
  the cost of one is paid at install time by consumers who never boot the class responsible. In a
  Laravel application every remaining requirement is already present: `laravel/framework` provides
  every `illuminate/*` and `symfony/process`.

  Nothing changes for anyone already depending on `laranail/console` directly.

- **Breaking.** The default view and translation namespaces are now the composer package name,
  `vendor/package`, rather than `vendor-package`, so a key names the package that ships it:
  `view('laranail/atlas::page')`, `__('laranail/atlas::messages.saved')`. Published files follow the
  namespace into `resources/views/vendor/laranail/atlas` and `lang/vendor/laranail/atlas`, which is
  where Laravel then reads them from -- `FileLoader::loadNamespaceOverrides()` interpolates the
  namespace into `{$path}/vendor/{$namespace}/{$locale}/{$group}.php`, and `loadViewsFrom()` does the
  same for views -- so the nesting groups a vendor's packages under one directory instead of
  scattering them across the `lang/vendor` root. A package passing an explicit namespace to
  `hasViews()` is unaffected.

### Added

- `Package::componentPrefix()`, the hyphen form of the view namespace. Blade component tags are the
  one registry that cannot take a slash: `ComponentTagCompiler` captures the name with
  `[\w\-\:\.]`, so `<x-laranail/atlas::card />` truncates at the slash and is emitted as literal
  text rather than compiled. `bootPackageViews()` registers the prefix as an alias over the paths
  `loadViewsFrom()` just resolved -- the published override directory included -- so both spellings
  find the same file and an override still wins for component tags. A custom view namespace is
  mirrored rather than ignored, so a package that opts out of the default still gets a tag-safe
  prefix.
- `tests/Feature/NamespaceSeparatorTest.php`, pinning the split against Blade's own name pattern
  rather than against a comment, so an upstream change to that pattern fails a test here.

### Changed

- **Breaking.** The package's OWN config publish tag is now
  `laranail::package-tools-config` (was the bare `package-tools-config`) — the same
  namespacing this package mints for everyone else's tags, enforced by a live-registry test.
  `vendor:publish --tag=package-tools-config` becomes
  `vendor:publish --tag=laranail::package-tools-config`.

## [0.1.0] - 2026-08-15

### Changed — breaking

- **The config key is `laranail.package-tools`,** published to `config/laranail/package-tools.php`;
  `config('package-tools.seeders.autorun.in_tests')` is now
  `config('laranail.package-tools.seeders.autorun.in_tests')`. Laravel's config repository is a flat
  map, and this package — which exists to namespace *other* packages' config — was reading a bare key
  of its own.

  The deferred-hook **event names** (`package-tools.test.event`) are unchanged; they are a different
  registry and not part of this change.

- **The three defaults that register a package's name into a shared registry are
  now vendor-scoped.** Laravel keeps view namespaces, translation namespaces and
  Artisan command names in **flat maps keyed by the name**, so a second package
  claiming the same key does not collide loudly — it silently replaces the first,
  and the damage surfaces far away as a missing view, an untranslated string, or
  a command that runs someone else's code. A bare slug like `icons`, `console` or
  `auth` is a plausible collision with a sibling package, a third-party one, or
  the consuming application's own.

  | Call | Was | Is |
  |---|---|---|
  | `hasViews()` | `view('widget::…')` | `view('acme-widget::…')` |
  | `translationNamespace()` | `acme/widget` | `acme-widget` |
  | `InstallCommand` default signature | `widget:install` | `acme::widget.install` |

  Each separator is forced by the registry that parses it, not chosen for
  consistency — do not unify them. A command name may use `::` because Symfony
  resolves an exact name before splitting on `:`. A translation namespace may
  **not** use a slash: `lang/vendor/{namespace}` is a single published
  directory, so `acme/widget` nests the published files one level deeper than
  `vendor:publish` and every consumer's override path expect. That one was a
  real bug, not only a convention.

  Passing an explicit argument still wins in both cases, so
  `hasViews('widget')` and `hasTranslations('widget')` restore the old names —
  but prefer not to, since that re-introduces exactly the collision the default
  exists to prevent. `InstallCommand`'s `$signature` parameter is unchanged.

  `InstallCommand` now uses `SupportsNamespacedNames`, without which Symfony's
  `validateName()` rejects the empty segment in `::`.

  **Upgrading:** a package that called `hasViews()` / `hasTranslations()` with no
  argument and referenced the old names must update its `view()` / `__()` calls
  and any published paths, or pass the old slug explicitly. Anything documenting
  `{package}:install` needs the new name.

### Fixed

- **External publish tags were invisible to the publish command, and `--tag=` rejected them.**
  `ServiceProvider::publishableGroups()` already returns the tag *names*; both call sites wrapped it
  in `array_keys()`, which yields `0, 1, 2 …` — and every one of those failed the `is_string()`
  guard that followed. So `--list` never printed the `(external)` rows its own comment describes,
  and `knownTags()` was always empty of them, meaning `--tag=livewire:assets` was refused as unknown
  even though the application published it.

### Added

- **`--external`** on `laranail::package-tools.publish` — publish every tag this package did not
  register. Replaces the pattern of hardcoding provider class names
  (`Livewire\LivewireServiceProvider`, `Laravel\Horizon\HorizonServiceProvider`), which published
  exactly the packages someone thought of and guarded each with `class_exists` so a missing one
  failed silently. Combines with `--all` to publish both sets.

- **`Services\Doctor\Checks\UnregisteredPublishableCheck`** — reports package directories that
  registered no publish tag. The failure is silent by construction: a module whose provider forgot
  `setPublishTagId()` works fine and simply never publishes, so the symptom arrives later as a
  missing asset. Warns rather than fails, because a module with nothing to publish is ordinary, and
  **never publishes or deletes anything** — the command this idea comes from answered the same
  question by publishing each directory under a guessed tag name.

### Security

- **Two recursive-delete paths were bypassing `PublishPathGuard`.** The guard's
  docblock has always claimed it is the one place in this package that deletes
  anything, and that it exists because a registered destination of `''` resolves
  to the document root. Both claims were false.

  `AssetRegistry::cleanup()` and `HasAssetPublisher::cleanAsset()` called
  `File::deleteDirectory()` directly — no containment check, no `..` rejection,
  no minimum depth, and no `is_link()` dispatch, so a symlinked destination was
  followed and its target emptied. `cleanAsset()` took the registered
  destination straight into `public_path()`, where `''` is the document root.

  Both now route through the guard. A target outside every configured prune root
  is **skipped and reported** rather than deleted: packages publish into
  `config/` and `database/migrations/` as well as `public/vendor/`, and silently
  removing a published config file is a worse surprise than declining to.

  `AssetRegistry::cleanup()` now returns `list<string>` — the refused targets —
  instead of `void`. It is not on `RegistryInterface`, and the one caller
  ignored the return, so nothing breaks.

### Fixed

- **`Services\Config\ConfigService::forget()` could not remove a top-level
  key.** It pruned a copy of `all()` and re-seeded the survivors — but a removed
  top-level key is simply absent from that copy, so nothing ever touched it and
  `get()` kept returning the old value. Nested keys worked, which is why it went
  unnoticed.

- **Booting a package no longer deletes published assets.**
  `PackageServiceProvider::bootPackageCustomPublishes()` and
  `ProcessAssets::bootPackageAssetRegistry()` deleted the destination of every
  publish tag marked `cleanBeforePublish` / `clean: true`. The only guard was
  `runningInConsole()`, and every console command boots every provider — so
  `php artisan route:list` removed the published assets of any package that had
  asked for a clean, and they did not come back until someone re-published.

  Boot now records the request rather than acting on it. See
  [UPGRADING.md](UPGRADING.md#boot-no-longer-deletes-published-assets) for what
  changes for a package that used the flag.

### Added

- **`Services\Config\ConfigManager`** (+ `Contracts\ConfigManagerInterface`) —
  a fluent, chainable runtime configuration manager, relocated here from
  `laranail/toolkit`. Config machinery belongs with the package-authoring
  runtime, which already owns the resolver, merger, validator and pattern
  resolver under `Services/Config/`.

  It sits alongside `ConfigService` rather than replacing it, and the boundary is
  now documented: `ConfigService` is boot-time `mergeConfigFrom` semantics where
  app config wins, `ConfigManager` is runtime override where the caller wins.
  Bound with `bind()`, not `singleton()` — it carries a base path and an
  operation log, so two callers configuring two module roots must not share one.

  Two things changed on the way over. `remove()` now makes both `get()` and
  `has()` miss for a top-level key, where before it could only null the value —
  the pruned array replaces the repository's item store via
  `Services\Config\ConfigItemStore`, degrading to nulling against a custom
  `Repository`. And `dump()` / `dd()` were added.

  See [docs/tools/config-manager.md](docs/tools/config-manager.md).

- **`Services\Config\ConfigItemStore`** — the one place that knows how to remove
  a config key, so `ConfigManager::remove()` and `ConfigService::forget()` cannot
  drift into disagreeing about what "forget" means.

- **`Services\Asset\PublishTagRegistry`** (+ `PublishTagEntry`) — a singleton
  recording every publish tag a laranail package registers, which package owns
  it, and whether it asked for its destination to be cleaned first. Laravel's own
  `ServiceProvider::$publishGroups` records tag => paths but knows nothing about
  ownership or cleaning. Repeat records for one tag merge their paths, and
  `cleanable` is sticky — one call site asking for a clean is enough.

- **`Services\Asset\PublishPathGuard`** (+ `PublishRoot`, `Exceptions\UnsafeAssetPath`)
  — every destructive asset operation now proves a path may be deleted before
  deleting it. A publish root must normalise cleanly, resolve inside the project,
  survive a non-overridable deny-list (the project root, `app`, `bootstrap`,
  `config`, `database`, `node_modules`, bare `public`, `resources`, `routes`,
  `src`, `storage`, `tests`, `vendor`), sit at least two levels below the project
  root, and still land inside its root once symlinks are followed.

  The deny-list is deliberately not configurable: config can narrow the blast
  radius, never widen it. Containment is strict descendancy with a trailing
  separator, so a root of `public/vendor` does not capture `public/vendor2`, and
  the root itself is never deletable — only its contents. An empty root list
  makes nothing deletable, which fails closed.

- **`laranail::package-tools.publish`** — publish package assets by tag,
  package, or all at once, with `--list`, `--dry-run` and `--json`.

  **`--force` overwrites and `--clean` deletes; they are separate flags.**
  Conflating them is how published assets get lost: in the implementation this
  replaces, `--force` meant "recursively delete every destination, then
  republish", and it ran for every module in the application. A destination
  outside every configured prune root is skipped and reported rather than
  deleted, because packages publish to `config/` and `database/migrations/` too.

- **`laranail::package-tools.assets-prune`** (+ `Services\Asset\OrphanScanner`,
  `OrphanReport`, `OrphanEntry`) — finds published files that nothing publishes
  any more. Every other cleanup here is destination-registry driven and so can
  only remove what something registered in the current process; an uninstalled
  package registers nothing, and its files stay forever.

  The expected set comes from **every** publish group the application exposes,
  not just laranail's, or Livewire's and Horizon's asset directories would read
  as orphans. It reports by default — `--prune` deletes, `--force` skips the
  confirmation, production refuses without `--force`, and a run exceeding
  `assets.prune.max_deletions` aborts before deleting anything. Symlinks are
  recorded as leaves and never descended.

- **`Concerns\Database\InteractsWithSeedFiles`** — a memoized Faker generator
  plus fixture-file resolution for package seeders. `fake()` **throws**
  `SeederException::missingFaker()` when `fakerphp/faker` is absent rather than
  installing it; the code this generalises ran `composer install` from inside the
  method and then called `exit(1)`, taking the process with it. Memoization is
  for reproducibility, not speed: `Factory::create()` reseeds the RNG per call.

- **`package-tools.assets.*` and `package-tools.seeders.{files_path,faker_locale}`**
  config blocks.

### Changed

- **A symlink inside a publish root is now deletable.** `PublishPathGuard`
  previously refused any path resolving outside its root, which caught symlink
  leaves too — so a stray link in `public/vendor` could never be removed, since
  every route to deleting it went through that check. `delete()` dispatches on
  `is_link()` and unlinks, which never touches the target, so the leaf is now
  exempt from resolution while its parent is still checked and an intermediate
  directory swap is still refused.

Initial public release.

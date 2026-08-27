# Installation

## Requirements

| | |
|---|---|
| PHP | `^8.4.1 \|\| ^8.5` |
| Laravel | `^13.0` |
| Extensions | `ext-fileinfo`, `ext-mbstring` (also `ext-zip` for the Zip archiver) |

The toolkit surfaces a live requirements check under `php artisan about`
(section **Laranail Toolkit**): PHP version vs. the `8.4.1` floor, the presence
of the `json` / `mbstring` / `fileinfo` extensions, and storage writability.

## Install

```bash
composer require laranail/toolkit
```

`ToolkitServiceProvider` is auto-registered through Laravel package discovery.
Migrations, views (namespace `laranail-toolkit`), and translations are loaded
automatically; you only publish assets you want to own or customize.

## Publish tags

Publish tags use the namespaced `laranail::toolkit-*` convention.

| Tag | Publishes to |
|-----|--------------|
| `laranail::toolkit-config` | all configs under the dotted namespace — `config/laranail/toolkit.php`, `…/toolkit/feature-toggles.php`, `…/toolkit/atlas.php`, `…/toolkit/security.php` (editing them overrides `config('laranail.toolkit.*')`; `security` holds the common-password / EFF-wordlist / redaction datasets read by `SecurityData`) |
| `laranail::toolkit-migrations` | `database/migrations/*` |
| `laranail::toolkit-views` | `resources/views/vendor/laranail-toolkit` |
| `laranail::toolkit-translations` | `lang/vendor/laranail-toolkit` |
| `laranail::toolkit-stubs` | `stubs/vendor/laranail-toolkit` (CRUD stubs) |

Example:

```bash
php artisan vendor:publish --tag=laranail::toolkit-config
php artisan vendor:publish --tag=laranail::toolkit-migrations
php artisan migrate
```

### Used directly from the package — no publishing

These are resolved from the package and need **no** `vendor:publish`:

- **Services** — `Services\{CacheService, SettingsStore, SchedulerService,
  RateLimiterService, LogService}` (container-bound by their contracts + concrete
  class; inject or `app(...)` them).
- **Support helpers** — `Support\{QueryParameters, CollectionFilter, Environment,
  AuthHelper}` (static utilities).
- **`reject_common_passwords`** validation rule (registered via the package).
- **`ApiResponseTrait`** (`use` it from the package namespace).
- **`AccessLog`** model (bound as `app('AccessLog')`; extend it in your app if needed).

[← Docs index](../README.md#documentation)

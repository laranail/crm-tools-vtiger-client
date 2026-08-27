# Configuration

The package's main config ships at `config/toolkit.php` and is merged under the
dotted **`laranail.toolkit.*`** namespace by the toolkit's own service provider.
Read any value with `config('laranail.toolkit.<key>')`. Publish it with:

```bash
php artisan vendor:publish --tag=laranail::toolkit-config
```

which writes `config/laranail/toolkit.php` (and the module configs to
`config/laranail/toolkit/{feature-toggles,security}.php`). Editing a
published file overrides the matching `config('laranail.toolkit.*')` value — the
provider's override bridge deep-merges the published file back over the dotted
key at register time.

Each config file lives at its own sub-key, so there are no collisions:
`toolkit` → `laranail.toolkit.*`, `feature-toggles` →
`laranail.toolkit.feature-toggles.*`.

## `laranail.toolkit.llm`

One consistent prefix: `default_provider` plus a nested block per provider
(`llm.openai`, `llm.gemini`, `llm.claude`).

| Key | Default | Notes |
|-----|---------|-------|
| `default_provider` | `openai` | `openai` \| `gemini` \| `claude` — chooses the driver bound to `LLMProviderInterface`. Env: `LLM_DEFAULT_PROVIDER`. |

## `laranail.toolkit.cache`

Defaults applied to `CacheService`.

| Key | Default | Notes |
|-----|---------|-------|
| `default_expiration` | `60` (minutes) | |
| `default_tags` | `[]` | |
| `namespace` | `''` | Optional key prefix applied to every read/write/forget. Env: `LARANAIL_CACHE_NAMESPACE`. |
| `log_events` | `false` | When `true`, the `LogCacheEvents` listener logs the cache-maintenance lifecycle (clearing/cleared/failed). Env: `LARANAIL_CACHE_LOG_EVENTS`. |

See [cache](cache.md) for the full data + maintenance surface.

## `laranail.toolkit.http`

Guzzle client defaults read by `HttpConfigurationService` (and the `HasGuzzleConfig`
trait).

| Key | Default | Env |
|-----|---------|-----|
| `persist_connection` | `true` | `GUZZLE_PERSIST_CONNECTION` |
| `request_timeout` | `60` (seconds) | `GUZZLE_REQUEST_TIMEOUT` |
| `max_retries` | `10` | `GUZZLE_MAX_RETRIES` |
| `cache_ttl` | `10` (seconds) | `GUZZLE_CACHE_TTL` |

## `laranail.toolkit.access_log`

| Key | Default | Notes |
|-----|---------|-------|
| `enabled` | `true` | Toggle persistence of the `laranail-toolkit.access-log` middleware. Env: `LARANAIL_ACCESS_LOG_ENABLED`. |
| `redact` | `null` | Request keys to redact. `null` uses the middleware's built-in deny-list. |

## `laranail.toolkit.rate_limiting`

Defaults for `RateLimiterService` and named profiles.

| Key | Default |
|-----|---------|
| `default_max_attempts` | `60` |
| `default_decay_minutes` | `1` |
| `cache_prefix` | `rate_limit:` |

Named profiles live under `defaults.<name>`, each a `{max_attempts, decay_minutes}` pair:

| Profile | `max_attempts` | `decay_minutes` |
|---------|:--:|:--:|
| `defaults.api` | `60` | `1` |
| `defaults.auth` | `5` | `15` |
| `defaults.download` | `3` | `1` |

## `laranail.toolkit.settings`

The runtime settings store (`SettingsStore`) — a JSON file of dynamic,
persisted-at-runtime values, kept separate from this static config.

| Key | Default | Notes |
|-----|---------|-------|
| `disk` | `local` | Filesystem disk for the settings file. Env: `LARANAIL_SETTINGS_DISK`. |
| `path` | `laranail/settings.json` | Path on the disk. Env: `LARANAIL_SETTINGS_PATH`. |

## `laranail.toolkit.runtime`

PHP runtime / INI settings applied by
[`Support\RuntimeConfigurator`](runtime-configurator.md#config-driven-profiles)
via `->usingConfig()` / `::fromConfig()`. Everything here is data — override any
value by env or by publishing this file, no code change.

| Key | Default | Notes |
|-----|---------|-------|
| `apply_on_boot` | `false` | Apply `default_profile` (or just `defaults`) at boot. Mutates PHP INI for every request/command — opt-in. A profile with `max_execution_time => 0` (queue/batch) removes the **web** request time limit globally, so prefer applying those per-job. Env: `LARANAIL_RUNTIME_APPLY_ON_BOOT`. |
| `default_profile` | `null` | Profile used by `apply_on_boot` and `::fromConfig()` with no argument. An unknown name logs a warning and applies defaults only. Env: `LARANAIL_RUNTIME_PROFILE`. |
| `defaults.<ini>` | `null` | Runtime-settable INI directives; `null` leaves PHP's value untouched. Keys: `memory_limit`, `max_execution_time`, `error_reporting` (integer), `display_errors`, `default_socket_timeout`. Each has a `LARANAIL_RUNTIME_*` env. |
| `ini` | `[]` | Any additional runtime-settable INI directives (`key => value`). |
| `disable_tools.<tool>` | `false` | Disable `telescope` / `xdebug` / `clockwork` / `debugbar` when applied. Env: `LARANAIL_RUNTIME_DISABLE_<TOOL>`. |
| `profiles.<name>` | see below | Named presets. A profile is a flat INI `key => value` map plus an optional `disable` list of tool names. |

Shipped profiles (mirror the built-in `RuntimeConfigurator` presets, tunable here):
`queue`, `batch`, `import`, `export`, `uploads`. Layering when a profile is
selected: `defaults` → `ini` → `disable_tools` → the profile (profile wins).

> **php.ini-only directives.** PHP cannot change `post_max_size`,
> `upload_max_filesize`, `max_input_time`, `max_input_vars`, `max_file_uploads`
> (PHP_INI_PERDIR) or `realpath_cache_size` / `realpath_cache_ttl`
> (PHP_INI_SYSTEM) at runtime via `ini_set()` — set them in `php.ini`. They are
> intentionally left out of `defaults`; if a profile (e.g. `uploads`) requests
> one, `RuntimeConfigurator` records it in `getFailedIniSettings()` instead of
> silently dropping it.

## LLM provider keys

Each provider has its own block. Keys are read when the matching driver is
resolved.

### `laranail.toolkit.llm.openai`

`api_key` (`OPENAI_API_KEY`), `max_retries` (3), `retry_delay` (2),
`default_model` (`gpt-3.5-turbo`), `default_temperature` (0.7),
`default_max_tokens` (300), `default_top_p` (1.0).

### `laranail.toolkit.llm.gemini`

`api_key` (`GEMINI_API_KEY`), `max_retries` (3), `retry_delay` (2),
`base_url` (`https://generativelanguage.googleapis.com/v1beta`),
`default_model` (`gemini-2.0-flash`), and matching temperature/tokens/top-p
defaults.

### `laranail.toolkit.llm.claude`

`api_key` (`CLAUDE_API_KEY`), `max_retries` (3), `retry_delay` (2),
`base_url` (`https://api.anthropic.com`),
`default_model` (`claude-3-5-sonnet-20241022`), `default_temperature` (1.0),
`default_max_tokens` (1024), `default_top_p` (1.0).

## Module configs

The feature modules' config files are merged centrally under the same namespace
(each at its own sub-key, published together under `laranail::toolkit-config`):

> Multi-channel notifications now live in the separate
> [`laranail/notifications`](https://opensource.simtabi.com/notifications/) package,
> with its own `config/notifications.php`.

## Feature toggles

The feature-toggles config (merged under `laranail.toolkit.feature-toggles.*`,
published with the rest of the configs under `laranail::toolkit-config` to
`config/laranail/toolkit/feature-toggles.php`) defines flags read by
`FeatureToggle`:

```php
return [
    'example_feature' => false,
];
```

Per-user and per-environment overrides are supported via
`laranail.toolkit.feature-toggles.<feature>.user.<id>` and
`laranail.toolkit.feature-toggles.<feature>.environment.<env>`.

## Security data

`config/security.php` is the single, merged source for the package's bundled
security datasets, merged under `laranail.toolkit.security` and read by
`Simtabi\Laranail\Toolkit\Modules\Security\SecurityData`:

- `passwords.common` — 560 lowercased, deduplicated common passwords
  (`RejectCommonPasswords`).
- `passphrases.wordlist` — exactly 7776 EFF CC0 words (`Passphrase`).
- `redact_keys` — default request-data redaction keys (`AccessLogMiddleware`).

`SecurityData` reads `config('laranail.toolkit.security.*')` when an app is booted
(falling back to a `__DIR__`-relative read of the package default when none is —
the Security value objects work framework-free). To customise the datasets,
publish the configs (`laranail::toolkit-config`) and edit
`config/laranail/toolkit/security.php` — the override is merged like any other
namespaced config. See the
[security helpers](security.md#merged-security-data-configsecurityphp) doc for
details.

[← Docs index](../README.md#documentation)

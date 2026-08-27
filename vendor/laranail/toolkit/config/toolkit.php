<?php

declare(strict_types=1);

return [
    /*
     * Authenticated-user accessors (Toolkit::user()/userAs()/userOrFail() and the
     * opt-in global user() helper). `default_guard` selects the guard when none is
     * passed (null = the framework's `auth.defaults.guard`). The accessors never
     * hard-code a user model, so multi-guard and non-standard model locations keep
     * working — for a statically-typed user use `Toolkit::userAs(User::class)`,
     * whose generic infers `?User` directly (no config needed). `user_model` is a
     * reserved, informational hint; it is NOT read at runtime.
     */
    'auth' => [
        'default_guard' => env('LARANAIL_TOOLKIT_AUTH_GUARD'), // null → framework default
        'user_model' => null,                                  // reserved hint; not used at runtime
    ],

    /*
     * Self-contained LLM module (Modules\LLM). One consistent prefix:
     * `laranail.toolkit.llm.*` — `default_provider` selects the driver bound to
     * LLMProviderInterface, and each provider's credentials/tuning live nested
     * under `llm.<provider>` (NOT as siblings of `llm`).
     */
    'llm' => [
        'default_provider' => env('LLM_DEFAULT_PROVIDER', 'openai'), // openai | gemini | claude

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'max_retries' => env('OPENAI_MAX_RETRIES', 3),
            'retry_delay' => env('OPENAI_RETRY_DELAY', 2),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-3.5-turbo'),
            'default_temperature' => env('OPENAI_DEFAULT_TEMPERATURE', 0.7),
            'default_max_tokens' => env('OPENAI_DEFAULT_MAX_TOKENS', 300),
            'default_top_p' => env('OPENAI_DEFAULT_TOP_P', 1.0),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'max_retries' => env('GEMINI_MAX_RETRIES', 3),
            'retry_delay' => env('GEMINI_RETRY_DELAY', 2),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.0-flash'),
            'default_temperature' => env('GEMINI_DEFAULT_TEMPERATURE', 0.7),
            'default_max_tokens' => env('GEMINI_DEFAULT_MAX_TOKENS', 300),
            'default_top_p' => env('GEMINI_DEFAULT_TOP_P', 1.0),
        ],

        'claude' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'max_retries' => env('CLAUDE_MAX_RETRIES', 3),
            'retry_delay' => env('CLAUDE_RETRY_DELAY', 2),
            'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com'),
            'default_model' => env('CLAUDE_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
            'default_temperature' => env('CLAUDE_DEFAULT_TEMPERATURE', 1.0),
            'default_max_tokens' => env('CLAUDE_DEFAULT_MAX_TOKENS', 1024),
            'default_top_p' => env('CLAUDE_DEFAULT_TOP_P', 1.0),
        ],
    ],

    'cache' => [
        'default_expiration' => 60,
        'default_tags' => [],
        // Optional key prefix applied to every read/write/forget.
        'namespace' => env('LARANAIL_CACHE_NAMESPACE', ''),
        // When true, LogCacheEvents logs the cache-maintenance lifecycle
        // (clearing/cleared/failed). Off by default to stay quiet.
        'log_events' => env('LARANAIL_CACHE_LOG_EVENTS', false),
    ],

    /*
     * Guzzle / HTTP client defaults consumed by Services\HttpConfigurationService.
     * Each value is overridable via its env key; the service reads these under
     * `laranail.toolkit.http.*` (merged from this file).
     */
    'http' => [
        'persist_connection' => env('GUZZLE_PERSIST_CONNECTION', true),
        'request_timeout' => env('GUZZLE_REQUEST_TIMEOUT', 60),
        'max_retries' => env('GUZZLE_MAX_RETRIES', 10),
        'cache_ttl' => env('GUZZLE_CACHE_TTL', 10),
    ],

    /*
     * Named Python (or any external HTTP) microservices consumed by
     * Services\PythonApiService. Each entry builds a configured client:
     * base_url + timeout + retry + JSON + SSL/CA-cert verification, with a
     * per-service health contract (path + expected key/value). `timeout` may be
     * null to inherit the shared `http.request_timeout` above. Point `ca_cert`
     * at a mkcert/Caddy CA bundle for HTTPS behind a local reverse proxy.
     */
    'access_log' => [
        // Toggle persistence of the laranail-toolkit.access-log middleware.
        'enabled' => env('LARANAIL_ACCESS_LOG_ENABLED', true),

        // Request keys whose values are redacted before being stored.
        // null = use the middleware's built-in default deny-list.
        'redact' => null,
    ],

    'rate_limiting' => [
        'default_max_attempts' => 60,
        'default_decay_minutes' => 1,
        'cache_prefix' => 'rate_limit:',
        'defaults' => [
            'api' => [
                'max_attempts' => 60,
                'decay_minutes' => 1,
            ],
            'auth' => [
                'max_attempts' => 5,
                'decay_minutes' => 15,
            ],
            'download' => [
                'max_attempts' => 3,
                'decay_minutes' => 1,
            ],
        ],
    ],

    /*
     * Runtime settings store (Services\SettingsStore) — a JSON file of dynamic,
     * persisted-at-runtime values, kept separate from this static config.
     */
    'settings' => [
        'disk' => env('LARANAIL_SETTINGS_DISK', 'local'),
        'path' => env('LARANAIL_SETTINGS_PATH', 'laranail/settings.json'),
    ],

    /*
     * PHP runtime / INI settings applied by Support\RuntimeConfigurator via
     * ->usingConfig()/::fromConfig(). Everything here is data — override any
     * value (env or by publishing this file) without touching code.
     *
     * A value of `null` in `defaults`/`ini` leaves PHP's current INI value
     * untouched; set a value to have it applied. Named `profiles` layer over the
     * defaults and mirror the built-in RuntimeConfigurator presets so you can
     * tune them from config.
     */
    'runtime' => [
        // Apply `default_profile` (or just `defaults`) automatically at boot.
        // This mutates PHP INI for EVERY request/command — opt-in only. Note a
        // profile with `max_execution_time => 0` (queue/batch) removes the web
        // request time limit globally; prefer applying those per-job instead.
        'apply_on_boot' => env('LARANAIL_RUNTIME_APPLY_ON_BOOT', false),

        // Profile applied by apply_on_boot and by ::fromConfig() with no
        // argument. null = apply only `defaults` + `ini` + `disable_tools`.
        'default_profile' => env('LARANAIL_RUNTIME_PROFILE'),

        // Common INI settings that ARE settable at runtime via ini_set(). null =
        // leave PHP's value untouched.
        //
        // php.ini-only directives are deliberately NOT listed here: PHP cannot
        // change `post_max_size`, `upload_max_filesize`, `max_input_time`,
        // `max_input_vars`, `max_file_uploads` (PHP_INI_PERDIR) or
        // `realpath_cache_size`, `realpath_cache_ttl` (PHP_INI_SYSTEM) at
        // runtime — set those in php.ini/.htaccess. If a profile requests one,
        // RuntimeConfigurator records it in getFailedIniSettings() rather than
        // silently dropping it.
        'defaults' => [
            'memory_limit' => env('LARANAIL_RUNTIME_MEMORY_LIMIT'),
            'max_execution_time' => env('LARANAIL_RUNTIME_MAX_EXECUTION_TIME'),
            'error_reporting' => env('LARANAIL_RUNTIME_ERROR_REPORTING'),
            'display_errors' => env('LARANAIL_RUNTIME_DISPLAY_ERRORS'),
            'default_socket_timeout' => env('LARANAIL_RUNTIME_DEFAULT_SOCKET_TIMEOUT'),
        ],

        // Any additional INI directives not listed above (key => value). Only
        // runtime-settable directives take effect (see the note above).
        'ini' => [],

        // Debugging tools disabled when a profile / the defaults are applied.
        'disable_tools' => [
            'telescope' => env('LARANAIL_RUNTIME_DISABLE_TELESCOPE', false),
            'xdebug' => env('LARANAIL_RUNTIME_DISABLE_XDEBUG', false),
            'clockwork' => env('LARANAIL_RUNTIME_DISABLE_CLOCKWORK', false),
            'debugbar' => env('LARANAIL_RUNTIME_DISABLE_DEBUGBAR', false),
        ],

        // Named presets. A profile is a flat map of INI key => value, plus an
        // optional `disable` list of tool names. These mirror the built-in
        // ::forQueueJob()/forBatchProcessing()/forImport()/forExport() presets.
        // (`uploads`' post/upload sizes are php.ini-only per the defaults note —
        // only its memory/timeout take effect at runtime.)
        'profiles' => [
            'queue' => ['memory_limit' => '1G', 'max_execution_time' => 0, 'disable' => ['telescope']],
            'batch' => ['memory_limit' => '2G', 'max_execution_time' => 0, 'disable' => ['telescope', 'xdebug', 'clockwork', 'debugbar']],
            'import' => ['memory_limit' => '1G', 'max_execution_time' => 1800, 'disable' => ['telescope']],
            'export' => ['memory_limit' => '1G', 'max_execution_time' => 900, 'disable' => ['telescope']],
            'uploads' => ['memory_limit' => '512M', 'post_max_size' => '100M', 'upload_max_filesize' => '100M', 'max_execution_time' => 600],
        ],
    ],
];

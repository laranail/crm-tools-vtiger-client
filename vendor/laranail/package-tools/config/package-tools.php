<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| laranail/package-tools
|--------------------------------------------------------------------------
|
| Host-app defaults for the package-tools runtime. Publish with:
|
|   php artisan vendor:publish --tag=package-tools-config
|
| Per-package overrides live under each consumer package's own config
| namespace ({vendor}.{package}.seeders.*, {vendor}.{package}.logging.*)
| and take precedence over the global defaults below.
|
*/

return [

    'logging' => [

        /*
        | Global defaults for every $package->log() logger. Per-package
        | overrides live at {vendor}.{package}.logging.* and win; a
        | host-defined logging.channels.{vendor}-{package} entry wins
        | wholesale. Keys left null defer to the package's LogDefinition
        | (or the built-in defaults: daily, 14 days, debug, line format,
        | storage/logs/{vendor}-{package}.log).
        */
        'enabled' => null,
        'channel' => null,     // delegate every package logger to one host channel
        'path' => null,
        'directory' => null,
        'driver' => null,      // 'daily' | 'single'
        'days' => null,
        'level' => null,
        'format' => null,      // 'line' | 'json'
        'permission' => null,

    ],

    'seeders' => [

        /*
        | Extra root-seeder FQCNs whose resolution triggers package seeding
        | (in addition to Database\Seeders\DatabaseSeeder). db:seed with a
        | custom --class never triggers package bundles unless listed here.
        */
        'root_seeders' => [],

        'autorun' => [
            // Global kill-switch for autorun-after-migrations bundles.
            'enabled' => env('PACKAGE_TOOLS_SEEDERS_AUTORUN', true),

            // Autorun is skipped in production unless explicitly enabled
            // (a bundle's autorunInEnvironments() list overrides this).
            'in_production' => env('PACKAGE_TOOLS_SEEDERS_AUTORUN_PRODUCTION', false),

            // Autorun is skipped while running unit tests unless enabled —
            // RefreshDatabase migrations must not seed by surprise.
            'in_tests' => false,
        ],

        /*
        | Defaults for background (queued) seeder bundles. null queue name
        | means the connection's default queue — nothing hardcoded.
        */
        'queue' => [
            'name' => null,
            'connection' => null,
            'tries' => 1,
            'timeout' => 300,
        ],

        /*
        | Kill-switch for the PackageSeedingStarted/Completed/Failed
        | events (per-bundle opt-out: notifiesOnCompletion(false)).
        */
        'events' => [
            'enabled' => true,
        ],

    ],

    'events' => [

        /*
        | The cross-type PackageAction{Started,Succeeded,Failed} lifecycle
        | family (migrations, seeders, jobs, schedules, installs). Two
        | independent gates: the high-frequency start/success stream can be
        | silenced without ever muting failure LOGGING — a failure is always
        | written to the log at error regardless of the 'failures' gate,
        | which only controls whether the PackageActionFailed event is
        | dispatched to listeners.
        */
        'lifecycle' => [
            'enabled' => env('PACKAGE_TOOLS_LIFECYCLE_EVENTS', true),
        ],
        'failures' => [
            'enabled' => env('PACKAGE_TOOLS_FAILURE_EVENTS', true),
        ],

    ],

    'migrations' => [

        /*
        | Full-fidelity migration lifecycle observability. When enabled
        | (console only), the migrator is decorated so each migration emits
        | PackageActionStarted/Succeeded/Failed with the real migration name
        | and exception — Laravel itself dispatches no migration-failure
        | event. Composition-safe: never clobbers another package that has
        | already decorated the migrator.
        */
        'failure_detection' => [
            'enabled' => env('PACKAGE_TOOLS_MIGRATION_FAILURE_DETECTION', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Asset publishing and pruning
    |--------------------------------------------------------------------------
    |
    | publish.tag_pattern — what counts as a laranail publish tag when --all
    |                       enumerates them.
    |
    | prune.roots         — the ONLY directories anything here will delete from.
    |                       Read and write scope both. Always inside base_path().
    |
    | prune.min_depth     — a floor on how shallow a root may be. "public" is
    |                       already refused outright by a non-overridable
    |                       deny-list; this catches the rest.
    |
    | prune.max_deletions — abort before deleting anything if a run would remove
    |                       more than this. A prune that wants to delete 4,000
    |                       files is a misconfiguration, not a tidy-up.
    |
    */

    'assets' => [

        'publish' => [
            'tag_pattern' => '*::*',
            'always' => [],
        ],

        'prune' => [
            'roots' => ['public/vendor'],
            'protect' => ['.gitignore', '.gitkeep'],
            'min_depth' => 2,
            'max_deletions' => 500,
            'max_depth' => 12,
        ],

    ],

];

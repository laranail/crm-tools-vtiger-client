<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Support\Arr;

/**
 * Environment predicates backed by the application's own environment resolver.
 *
 * A thin, dependency-free convenience layer over `app()->environment()` and
 * `app()->runningUnitTests()` — it adds the named buckets (local/staging/beta/
 * alpha/…) and the `isNonProduction()` aggregate that the framework does not
 * ship out of the box.
 */
final class Environment
{
    /**
     * Whether the app is running in a local-ish environment (local, staging, or
     * development).
     */
    public static function isLocal(): bool
    {
        return app()->isLocal()
            || app()->environment(['local', 'staging'])
            || self::isDevelopment();
    }

    public static function isDevelopment(): bool
    {
        return app()->environment(['development']);
    }

    public static function isStaging(): bool
    {
        return app()->environment(['staging']);
    }

    public static function isTesting(): bool
    {
        return app()->environment(['testing']);
    }

    public static function isBeta(): bool
    {
        return app()->environment(['beta']);
    }

    public static function isAlpha(): bool
    {
        return app()->environment(['alpha']);
    }

    public static function isRelease(): bool
    {
        return app()->environment(['release']);
    }

    public static function isProduction(): bool
    {
        return app()->isProduction();
    }

    /**
     * Whether the app is in any non-production environment.
     */
    public static function isNonProduction(): bool
    {
        return self::isLocal()
            || self::isStaging()
            || self::isTesting()
            || self::isBeta()
            || self::isAlpha()
            || self::isDevelopment();
    }

    /**
     * Whether the current environment matches the given name(s).
     *
     * @param string|list<string> $environment
     */
    public static function isEnvironment(string|array $environment): bool
    {
        return app()->environment(Arr::wrap($environment));
    }

    /**
     * Whether the app is running a unit-test suite.
     */
    public static function isRunningUnitTests(): bool
    {
        return app()->runningUnitTests();
    }

    /**
     * The current environment name.
     */
    public static function current(): string
    {
        return app()->environment();
    }
}

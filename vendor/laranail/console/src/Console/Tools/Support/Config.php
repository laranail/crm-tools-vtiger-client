<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Reads `config('laranail.console.*')` defensively so the toolkit also works outside a
 * booted Laravel application (e.g. standalone scripts, tests) — returning the
 * given default when no config container is available.
 */
final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('app') && app()->bound('config')) {
            return config("laranail.console.{$key}", $default);
        }

        return $default;
    }

    /**
     * The configured `console.locale`, or null to fall back to the app locale.
     * Used by widgets to resolve translatable strings without mutating the host
     * app's global locale.
     */
    public static function locale(): ?string
    {
        $locale = self::get('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}

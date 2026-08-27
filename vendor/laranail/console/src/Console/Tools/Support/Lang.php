<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Resolves widget strings from the `laranail-console::console.*` translation namespace,
 * honouring `config('laranail.console.locale')` WITHOUT mutating the host app's global
 * locale. Falls back to the supplied English default (with `:placeholder`
 * interpolation) when no translator or key is available — so widgets render
 * correctly both inside and outside a booted Laravel application.
 */
final class Lang
{
    /**
     * @param array<string, scalar|null> $replace
     */
    public static function get(string $key, string $default, array $replace = []): string
    {
        $namespaced = "laranail-console::console.{$key}";

        if (function_exists('app') && app()->bound('translator')) {
            $message = trans($namespaced, $replace, Config::locale());

            if (is_string($message) && $message !== $namespaced) {
                return $message;
            }
        }

        return self::interpolate($default, $replace);
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    private static function interpolate(string $text, array $replace): string
    {
        foreach ($replace as $search => $value) {
            $text = str_replace(':' . $search, (string) $value, $text);
        }

        return $text;
    }
}

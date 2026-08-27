<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

/**
 * Typed accessors over Laravel's `config()` helper.
 *
 * Laravel's `config()` returns `mixed`, which forces every call site to either
 * cast (unsafe under strict static analysis) or hand-roll narrowing. These
 * static helpers centralise that narrowing once: each returns the requested
 * scalar type, falling back to the supplied default when the configured value
 * is absent or of an unexpected type.
 */
final class Config
{
    public static function string(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = config($key, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = config($key, $default);

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = config($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @param array<array-key, mixed> $default
     *
     * @return array<array-key, mixed>
     */
    public static function array(string $key, array $default = []): array
    {
        $value = config($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * A list of strings, dropping any non-string members.
     *
     * @param list<string> $default
     *
     * @return list<string>
     */
    public static function stringList(string $key, array $default = []): array
    {
        $value = config($key, $default);

        if (!is_array($value)) {
            return $default;
        }

        return array_values(array_filter($value, is_string(...)));
    }
}

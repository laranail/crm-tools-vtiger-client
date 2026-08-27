<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

/**
 * Safe scalar coercion helpers for `mixed` values.
 *
 * Many call sites read loosely-typed values (config repositories, request
 * input, session data, decoded JSON) that PHPStan widens to `mixed`. Casting
 * `mixed` directly is rejected under strict analysis; these helpers narrow the
 * value first and fall back to a supplied default when it is of an unusable
 * type, centralising the narrowing instead of repeating it at every site.
 */
final class Cast
{
    public static function toString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $default;
    }

    public static function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    public static function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}

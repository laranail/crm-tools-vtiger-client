<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Helpers\Helper;

/**
 * Array-shaping helpers (trim / flatten / dot-notation).
 *
 * Folded into {@see Helper} — call via the
 * `Helper::` facade, never the trait directly.
 */
trait InteractsWithArrays
{
    /**
     * Trim every string value in an array, leaving non-string values untouched.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function arrayTrim(array $array): array
    {
        return array_map(fn ($value) => is_string($value) ? trim($value) : $value, $array);
    }

    /**
     * Flatten a multi-dimensional array into a single level of leaf values.
     *
     * @param array<mixed> $array
     *
     * @return array<int, mixed>
     */
    public static function arrayFlatten(array $array): array
    {
        return Arr::flatten($array);
    }

    /**
     * Convert a bracketed array expression into dot notation:
     * `a[b][c]` => `a.b.c`. A plain key (no brackets) is returned unchanged.
     */
    public static function arrayToDotNotation(string $expr): string
    {
        return Str::replace(['[', ']'], ['.', ''], $expr);
    }
}

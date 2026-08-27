<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CollectionFilter
{
    /**
     * Filter a collection by a given key value pair.
     *
     * @param Collection<array-key, mixed> $items
     * @param mixed                        $value
     *
     * @return Collection<array-key, mixed>
     */
    public static function filter(Collection $items, string $name, string $operator, $value)
    {
        return $items->filter(function ($item) use ($name, $operator, $value) {
            $actual = data_get($item, $name);

            if ($operator === 'equals') {
                return $actual == $value;
            }

            if ($operator === 'not_equals') {
                return $actual != $value;
            }

            // String operators: compare case-insensitively, guarding non-scalars.
            $haystack = is_scalar($actual) ? Str::lower((string) $actual) : '';
            $needle = is_scalar($value) ? Str::lower((string) $value) : '';

            return match ($operator) {
                'contains' => str_contains($haystack, $needle),
                'not_contains' => !str_contains($haystack, $needle),
                'starts_with' => str_starts_with($haystack, $needle),
                'ends_with' => str_ends_with($haystack, $needle),
                default => false,
            };
        });
    }
}

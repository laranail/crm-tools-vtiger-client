<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the toolkit's general-purpose Arr macros.
 */
final class ArrMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerArrMacros();
    }

    private function registerArrMacros(): void
    {
        Arr::macro('filterNulls', fn (array $array): array => array_filter(
            $array,
            static fn (mixed $value): bool => $value !== null,
        ));

        Arr::macro('filterEmpty', fn (array $array): array => array_filter(
            $array,
            static fn (mixed $value): bool => !empty($value),
        ));

        Arr::macro('mapKeys', function (array $array, callable $callback): array {
            $result = [];

            foreach ($array as $key => $value) {
                $result[$callback($key, $value)] = $value;
            }

            return $result;
        });

        Arr::macro('insertAfter', function (array $array, mixed $key, array $insert): array {
            $index = array_search($key, array_keys($array), true);

            if ($index === false) {
                return array_merge($array, $insert);
            }

            $pos = $index + 1;

            return array_merge(
                array_slice($array, 0, $pos, true),
                $insert,
                array_slice($array, $pos, null, true),
            );
        });

        Arr::macro('insertBefore', function (array $array, mixed $key, array $insert): array {
            $index = array_search($key, array_keys($array), true);

            if ($index === false) {
                return array_merge($insert, $array);
            }

            return array_merge(
                array_slice($array, 0, $index, true),
                $insert,
                array_slice($array, $index, null, true),
            );
        });

        // Removes every element strictly equal to $value (value-membership, not key lookup).
        Arr::macro('removeValue', fn (array $array, mixed $value): array => array_values(array_filter(
            $array,
            static fn (mixed $item): bool => $item !== $value,
        )));

        Arr::macro('removeValues', fn (array $array, array $values): array => array_values(array_filter(
            $array,
            static fn (mixed $item): bool => !in_array($item, $values, true),
        )));

        Arr::macro('renameKey', function (array $array, int|string $oldKey, int|string $newKey): array {
            if (!array_key_exists($oldKey, $array)) {
                return $array;
            }

            $keys = array_keys($array);
            $position = array_search($oldKey, $keys, true);
            $keys[$position] = $newKey;

            return array_combine($keys, $array);
        });

        // Rename many keys at once from an [oldKey => newKey] map; missing keys
        // are skipped (the legacy version assumed each old key existed).
        Arr::macro('renameKeys', function (array $array, array $changes): array {
            foreach ($changes as $oldKey => $newKey) {
                if (!array_key_exists($oldKey, $array)) {
                    continue;
                }

                $array[$newKey] = $array[$oldKey];
                unset($array[$oldKey]);
            }

            return $array;
        });

        Arr::macro('average', function (array $array, ?string $key = null): float|int {
            if ($key !== null) {
                $array = Arr::pluck($array, $key);
            }

            $array = array_filter($array, is_numeric(...));

            if ($array === []) {
                return 0;
            }

            return array_sum($array) / count($array);
        });

        Arr::macro('median', function (array $array, ?string $key = null): float|int {
            if ($key !== null) {
                $array = Arr::pluck($array, $key);
            }

            $array = array_values(array_map(
                static fn (mixed $value): float|int => $value + 0,
                array_filter($array, is_numeric(...)),
            ));
            sort($array);

            $count = count($array);

            if ($count === 0) {
                return 0;
            }

            $middle = (int) floor($count / 2);

            if ($count % 2 === 0) {
                return ($array[$middle - 1] + $array[$middle]) / 2;
            }

            return $array[$middle];
        });

        Arr::macro('groupByKey', function (array $array, string $key): array {
            $result = [];

            foreach ($array as $item) {
                $groupKey = Arr::get($item, $key);

                if (!is_int($groupKey) && !is_string($groupKey)) {
                    continue;
                }

                $result[$groupKey][] = $item;
            }

            return $result;
        });

        Arr::macro('uniqueBy', function (array $array, string $key): array {
            $result = [];
            $seen = [];

            foreach ($array as $item) {
                $uniqueKey = Arr::get($item, $key);

                if (!in_array($uniqueKey, $seen, true)) {
                    $seen[] = $uniqueKey;
                    $result[] = $item;
                }
            }

            return $result;
        });

        Arr::macro('sortByKeys', function (array $array, array $keys): array {
            uksort($array, static function (mixed $a, mixed $b) use ($keys): int {
                $posA = array_search($a, $keys, true);
                $posB = array_search($b, $keys, true);

                if ($posA === false) {
                    return 1;
                }

                if ($posB === false) {
                    return -1;
                }

                return $posA <=> $posB;
            });

            return $array;
        });
    }
}

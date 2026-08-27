<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Registers the toolkit's general-purpose Collection macros.
 */
final class CollectionMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCollectionMacros();
    }

    private function registerCollectionMacros(): void
    {
        Collection::macro('transpose', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            $array = $this->toArray();

            if ($array === []) {
                return new Collection([]);
            }

            // Transpose operates on a collection of rows; coerce each row to an
            // array so the variadic spread into array_map() is well-typed.
            $rows = array_map(static fn (mixed $row): array => is_array($row) ? $row : [$row], array_values($array));

            return new Collection(array_map(null, ...$rows));
        });

        Collection::macro('recursive', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->map(function (mixed $value): mixed {
                if (is_array($value) || is_object($value)) {
                    return (new Collection((array) $value))->recursive();
                }

                return $value;
            });
        });

        Collection::macro('mapToKey', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            $result = [];

            foreach ($this as $key => $value) {
                $mappedValue = $callback($value, $key);
                $result[$mappedValue[0]] = $mappedValue[1];
            }

            return new Collection($result);
        });

        Collection::macro('filterRecursive', function (?callable $callback = null): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->map(function (mixed $value) use ($callback): mixed {
                if ($value instanceof Collection || is_array($value)) {
                    return (new Collection($value))->filterRecursive($callback);
                }

                return $value;
            })->filter($callback);
        });

        Collection::macro('firstOrFail', function (?callable $callback = null, mixed $default = null): mixed {
            /** @var Collection<array-key, mixed> $this */
            $result = $this->first($callback, $default);

            if ($result === null) {
                throw new RuntimeException('No items found in collection.');
            }

            return $result;
        });

        Collection::macro('sumRecursive', function (callable|string|null $key = null): mixed {
            /** @var Collection<array-key, mixed> $this */
            return $this->flatten()->sum($key);
        });

        Collection::macro('averageBy', function (callable $callback): mixed {
            /** @var Collection<array-key, mixed> $this */
            return $this->map($callback)->average();
        });

        // Default escape doubles the enclosure ("" ) per RFC 4180 / Excel / fgetcsv;
        // a backslash default would emit \" which standard CSV readers mis-parse.
        Collection::macro('toCsv', function (string $delimiter = ',', string $enclosure = '"', string $escape = '"'): array {
            /** @var Collection<array-key, mixed> $this */
            $rows = [];

            foreach ($this as $item) {
                $row = is_array($item) ? $item : (array) $item;
                $rows[] = implode($delimiter, array_map(
                    static fn (mixed $value): string => $enclosure
                        . str_replace($enclosure, $escape . $enclosure, Cast::toString($value))
                        . $enclosure,
                    $row,
                ));
            }

            return $rows;
        });

        Collection::macro('prioritize', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->filter($callback)->merge($this->reject($callback));
        });

        Collection::macro('rotateLeft', function (int $count = 1): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                /** @var Collection<array-key, mixed> $empty */
                $empty = new Collection([]);

                return $empty;
            }

            $count %= $this->count();

            return $this->slice($count)->merge($this->take($count));
        });

        Collection::macro('rotateRight', function (int $count = 1): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                return new Collection([]);
            }

            return $this->rotateLeft($this->count() - ($count % $this->count()));
        });

        Collection::macro('toTree', function (string $parentKey = 'parent_id', string $childrenKey = 'children'): Collection {
            /** @var Collection<array-key, mixed> $this */
            $grouped = $this->groupBy($parentKey);

            $buildTree = function (int|string|null $parentId) use ($grouped, $childrenKey, &$buildTree): Collection {
                $children = $grouped->get($parentId);

                if (!$children instanceof Collection) {
                    return new Collection([]);
                }

                return $children->map(function (mixed $item) use ($childrenKey, $buildTree): mixed {
                    if (!is_array($item)) {
                        return $item;
                    }

                    $childId = $item['id'] ?? null;
                    $item[$childrenKey] = $buildTree(is_int($childId) || is_string($childId) ? $childId : null);

                    return $item;
                });
            };

            return $buildTree(null);
        });

        Collection::macro('insertAfter', function (int|string $key, mixed $value): Collection {
            /** @var Collection<array-key, mixed> $this */
            $index = $this->keys()->search($key);

            if (!is_int($index)) {
                return $this->put($key, $value);
            }

            $items = $this->toArray();
            $offset = $index + 1;
            $start = array_slice($items, 0, $offset, true);
            $end = array_slice($items, $offset, null, true);

            return (new Collection($start))->merge([$key => $value])->merge($end);
        });

        Collection::macro('insertBefore', function (int|string $key, mixed $value): Collection {
            /** @var Collection<array-key, mixed> $this */
            $index = $this->keys()->search($key);

            if (!is_int($index)) {
                return $this->prepend($value, $key);
            }

            $items = $this->toArray();
            $start = array_slice($items, 0, $index, true);
            $end = array_slice($items, $index, null, true);

            return (new Collection($start))->merge([$key => $value])->merge($end);
        });

        $this->registerNavigationMacros();
        $this->registerChunkingMacros();
        $this->registerReshapeMacros();
        $this->registerFilterMacros();
    }

    /**
     * Predicate/lookup macros folded from the legacy invokable micro-classes.
     */
    private function registerFilterMacros(): void
    {
        // Note: Collection::after() / before() are native in this Laravel
        // version, so the legacy After macro is not re-registered (a macro would
        // be shadowed by the core method).

        // A new collection built from the value stored at $key (the legacy
        // CollectBy: get-then-wrap). An iterable/Arrayable value becomes the new
        // collection's items; any other scalar/object is wrapped as a single
        // item, so the return type is always a Collection.
        Collection::macro('collectBy', function (int|string $key, mixed $default = null): Collection {
            /** @var Collection<array-key, mixed> $this */
            $value = $this->get($key, $default);

            if ($value === null) {
                return new Collection([]);
            }

            if (is_iterable($value) || $value instanceof Arrayable) {
                return new Collection($value);
            }

            return new Collection([$value]);
        });

        // Map then drop falsy results (the legacy FilterMap). filter() with no
        // callback removes empty/null/false entries after mapping.
        Collection::macro('filterMap', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->map($callback)->filter();
        });

        // Run $callback (and return self) only when the collection is NOT empty
        // — the complement of the kept ifEmpty() macro.
        Collection::macro('ifAny', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isNotEmpty()) {
                $callback($this);
            }

            return $this;
        });

        // The logical inverse of contains(): true when NO item matches. Mirrors
        // contains()'s flexible signature (value, key+value, or truth-test).
        Collection::macro('none', function (mixed $key, mixed $value = null): bool {
            /** @var Collection<array-key, mixed> $this */
            if (func_num_args() === 2) {
                return !$this->contains($key, $value);
            }

            return !$this->contains($key);
        });

        // pluck() returning a plain array rather than a Collection.
        Collection::macro('pluckToArray', function (string|array $value, ?string $key = null): array {
            /** @var Collection<array-key, mixed> $this */
            return $this->pluck($value, $key)->all();
        });

        // A collection with $size sequential integers [1..$size]; empty when
        // $size < 1.
        Collection::macro('withSize', function (int $size): Collection {
            if ($size < 1) {
                return new Collection([]);
            }

            return new Collection(range(1, $size));
        });

        // Insert $item after the entry whose KEY equals $afterKey, preserving all
        // existing keys (unlike insertAt, which re-indexes). The inserted value
        // is keyed by $key when given. Appends when $afterKey is absent.
        Collection::macro('insertAfterKey', function (int|string $afterKey, mixed $item, int|string|null $key = null): Collection {
            /** @var Collection<array-key, mixed> $this */
            $items = $this->all();
            $position = array_search($afterKey, array_keys($items), true);
            $inserted = $key !== null ? [$key => $item] : [$item];

            if ($position === false) {
                return (new Collection($items))->merge($inserted);
            }

            $offset = $position + 1;

            return new Collection(array_slice($items, 0, $offset, true))
                ->merge($inserted)
                ->merge(array_slice($items, $offset, null, true));
        });

        // Insert $item before the entry whose KEY equals $beforeKey, preserving
        // existing keys. Prepends when $beforeKey is absent.
        Collection::macro('insertBeforeKey', function (int|string $beforeKey, mixed $item, int|string|null $key = null): Collection {
            /** @var Collection<array-key, mixed> $this */
            $items = $this->all();
            $position = array_search($beforeKey, array_keys($items), true);
            $inserted = $key !== null ? [$key => $item] : [$item];

            $offset = $position === false ? 0 : $position;

            return new Collection(array_slice($items, 0, $offset, true))
                ->merge($inserted)
                ->merge(array_slice($items, $offset, null, true));
        });

        // Split into consecutive sections, starting a new section each time the
        // value resolved by $key changes. Each section is [sectionKey => name,
        // itemsKey => Collection].
        Collection::macro('sectionBy', function (
            callable|string $key,
            bool $preserveKeys = false,
            int|string $sectionKey = 0,
            int|string $itemsKey = 1,
        ): Collection {
            /** @var Collection<array-key, mixed> $this */
            $resolver = is_string($key)
                ? static fn (mixed $item): mixed => data_get($item, $key)
                : $key;

            $results = new Collection([]);
            $current = null;
            $currentName = null;

            foreach ($this as $itemKey => $value) {
                $name = $resolver($value);

                if ($current === null || $currentName !== $name) {
                    $current = new Collection([]);
                    $currentName = $name;
                    $results->push(new Collection([
                        $sectionKey => $name,
                        $itemsKey => $current,
                    ]));
                }

                if ($preserveKeys) {
                    $current->put($itemKey, $value);
                } else {
                    $current->push($value);
                }
            }

            return $results;
        });

        $this->registerDeepPathStringFilters();
    }

    /**
     * Deep-path string-filter macros — keep only items whose value resolved at a
     * dot-path $key is a STRING matching the substring test. Restored (and fixed)
     * from the legacy WhereContains / WhereStartsWith / WhereEndsWith invokables.
     *
     * Two deliberate departures from the loose legacy versions:
     *   - Non-string resolved values are EXCLUDED rather than coerced, so a
     *     numeric/null/array column never silently stringifies into a match.
     *   - Case-insensitivity lowercases via Str::lower (multibyte-safe) instead
     *     of strtolower / strncasecmp, and the comparisons use the native
     *     str_contains / str_starts_with / str_ends_with rather than strrev or
     *     strncmp tricks.
     */
    private function registerDeepPathStringFilters(): void
    {
        // Items whose value at $key (a string) CONTAINS $value.
        Collection::macro('whereContains', function (string $key, string $value, bool $caseSensitive = true): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->filter(static function (mixed $item) use ($key, $value, $caseSensitive): bool {
                $haystack = data_get($item, $key);

                if (!is_string($haystack)) {
                    return false;
                }

                if (!$caseSensitive) {
                    $haystack = Str::lower($haystack);
                    $value = Str::lower($value);
                }

                return str_contains($haystack, $value);
            });
        });

        // Items whose value at $key (a string) STARTS WITH $value.
        Collection::macro('whereStartsWith', function (string $key, string $value, bool $caseSensitive = true): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->filter(static function (mixed $item) use ($key, $value, $caseSensitive): bool {
                $haystack = data_get($item, $key);

                if (!is_string($haystack)) {
                    return false;
                }

                if (!$caseSensitive) {
                    $haystack = Str::lower($haystack);
                    $value = Str::lower($value);
                }

                return str_starts_with($haystack, $value);
            });
        });

        // Items whose value at $key (a string) ENDS WITH $value.
        Collection::macro('whereEndsWith', function (string $key, string $value, bool $caseSensitive = true): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->filter(static function (mixed $item) use ($key, $value, $caseSensitive): bool {
                $haystack = data_get($item, $key);

                if (!is_string($haystack)) {
                    return false;
                }

                if (!$caseSensitive) {
                    $haystack = Str::lower($haystack);
                    $value = Str::lower($value);
                }

                return str_ends_with($haystack, $value);
            });
        });
    }

    /**
     * Item-navigation and positional-insertion macros.
     */
    private function registerNavigationMacros(): void
    {
        // The previous item relative to $current (mirror of the native after()).
        // Returns null when $current is the first item or is not present.
        Collection::macro('before', function (mixed $current, bool $strict = false): mixed {
            /** @var Collection<array-key, mixed> $this */
            return $this->reverse()->after($current, $strict);
        });

        // Insert $item at a positional $index (optionally keyed). Returns a new
        // collection — unlike the legacy version it does not mutate in place.
        Collection::macro('insertAt', function (int $index, mixed $item, int|string|null $key = null): Collection {
            /** @var Collection<array-key, mixed> $this */
            $head = $this->slice(0, $index);
            $tail = $this->slice($index);

            $inserted = $key !== null ? new Collection([$key => $item]) : new Collection([$item]);

            return $head->merge($inserted)->merge($tail)->values();
        });

        // Rotate items by a (possibly negative) offset.
        Collection::macro('rotate', function (int $offset): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                return new Collection([]);
            }

            $count = $this->count();
            $offset %= $count;

            if ($offset < 0) {
                $offset += $count;
            }

            return $this->slice($offset)->merge($this->take($offset))->values();
        });

        // First item passing $callback, or push value($value) onto $instance
        // (defaulting to this collection) and return it.
        Collection::macro('firstOrPush', function (
            callable $callback,
            mixed $value,
            ?Collection $instance = null,
        ): mixed {
            /** @var Collection<array-key, mixed> $this */
            $found = $this->first($callback);

            if ($found !== null) {
                return $found;
            }

            $resolved = value($value);
            ($instance ?? $this)->push($resolved);

            return $resolved;
        });
    }

    /**
     * Consecutive-window and predicate-chunking macros.
     */
    private function registerChunkingMacros(): void
    {
        // Consecutive overlapping windows of $chunkSize items.
        Collection::macro('eachCons', function (int $chunkSize, bool $preserveKeys = false): Collection {
            /** @var Collection<array-key, mixed> $this */
            $result = new Collection([]);
            $limit = $this->count() - $chunkSize;

            for ($index = 0; $index <= $limit; $index++) {
                $window = $this->slice($index, $chunkSize);
                $result->push($preserveKeys ? $window : $window->values());
            }

            return $preserveKeys ? $result : $result->values();
        });

        // Split into chunks before each point where $callback's result changes.
        Collection::macro('sliceBefore', function (callable $callback, bool $preserveKeys = false): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                return new Collection([]);
            }

            $chunks = new Collection([]);
            $current = null;

            foreach ($this as $key => $item) {
                if ($current === null) {
                    $current = $preserveKeys ? new Collection([$key => $item]) : new Collection([$item]);

                    continue;
                }

                $previous = $current->last();

                if ($callback($item, $previous)) {
                    $chunks->push($current);
                    $current = $preserveKeys ? new Collection([$key => $item]) : new Collection([$item]);
                } elseif ($preserveKeys) {
                    $current->put($key, $item);
                } else {
                    $current->push($item);
                }
            }

            if ($current instanceof Collection) {
                $chunks->push($current);
            }

            return $chunks;
        });

        // Chunk while $callback's result over consecutive items stays the same.
        Collection::macro('chunkBy', function (callable $callback, bool $preserveKeys = false): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->sliceBefore(
                static fn (mixed $item, mixed $previous): bool => $callback($item) !== $callback($previous),
                $preserveKeys,
            );
        });

        // Group by an Eloquent model resolved from each item, returning rows of
        // [model, items].
        Collection::macro('groupByModel', function (
            callable|string $callback,
            int|string $modelKey = 0,
            int|string $itemsKey = 1,
        ): Collection {
            /** @var Collection<array-key, mixed> $this */
            $resolver = is_string($callback)
                ? static fn (mixed $item): mixed => data_get($item, $callback)
                : $callback;

            return $this
                ->groupBy(static function (mixed $item) use ($resolver): int|string {
                    $model = $resolver($item);
                    $modelId = $model instanceof Model ? $model->getKey() : null;

                    return is_int($modelId) ? $modelId : Cast::toString($modelId);
                })
                ->map(static fn (Collection $items): array => [
                    $modelKey => $resolver($items->first()),
                    $itemsKey => $items,
                ])
                ->values();
        });
    }

    /**
     * Key/value reshaping and conditional macros.
     */
    private function registerReshapeMacros(): void
    {
        // Sort by lowercased $value, key by $key, optionally prepend an empty option.
        Collection::macro('forSelectBox', function (string $key, string $value, bool $addEmpty = true): array {
            /** @var Collection<array-key, mixed> $this */
            $options = $this
                ->sortBy(static fn (mixed $item): string => Str::lower(Cast::toString(data_get($item, $value))))
                ->mapWithKeys(static fn (mixed $item): array => [
                    Cast::toString(data_get($item, $key)) => data_get($item, $value),
                ])
                ->all();

            return $addEmpty ? ['' => ''] + $options : $options;
        });

        // Pull the given keys in order, substituting null for missing ones, and
        // drop the keys so list() destructuring works.
        Collection::macro('extract', function (mixed $keys): Collection {
            /** @var Collection<array-key, mixed> $this */
            $keys = is_array($keys) ? $keys : func_get_args();

            return new Collection(array_map(
                fn (mixed $key): mixed => data_get(
                    $this->all(),
                    is_array($key) || is_int($key) || is_string($key) ? $key : null,
                ),
                $keys,
            ));
        });

        // Everything except the first item.
        Collection::macro('tail', function (bool $preserveKeys = false): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $preserveKeys ? $this->slice(1) : $this->slice(1)->values();
        });

        // To a collection of [key, value] pairs.
        Collection::macro('toPairs', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->map(static fn (mixed $value, mixed $key): array => [$key, $value])->values();
        });

        // From a collection of [key, value] pairs back to an associative collection.
        Collection::macro('fromPairs', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            $assoc = [];

            foreach ($this as $pair) {
                if (!is_array($pair)) {
                    continue;
                }

                [$key, $value] = $pair;

                if (!is_int($key) && !is_string($key)) {
                    continue;
                }

                $assoc[$key] = $value;
            }

            return new Collection($assoc);
        });

        // Run $callback when the collection is empty, then return the collection.
        Collection::macro('ifEmpty', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                $callback($this);
            }

            return $this;
        });

        // Map a list of {key, value} rows into an associative [key => value]
        // collection. Fixes the broken legacy Collection->select.
        Collection::macro('mapKeyValuePairs', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            $result = [];

            foreach ($this as $row) {
                $key = data_get($row, 'key');
                $value = data_get($row, 'value');

                if ($key === null) {
                    continue;
                }

                /** @var array-key $key */
                $result[$key] = $value;
            }

            return new Collection($result);
        });

        // Pluck several keys per item, returning a collection of reduced rows.
        // Each item is narrowed to only $keys: Collections via only(), arrays via
        // Arr::only(), ArrayAccess by reading each key, and plain objects by
        // intersecting their public vars.
        Collection::macro('pluckMany', function (array $keys): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->map(static function (mixed $item) use ($keys): mixed {
                if ($item instanceof Collection) {
                    return $item->only($keys);
                }

                if (is_array($item)) {
                    return Arr::only($item, $keys);
                }

                if ($item instanceof ArrayAccess) {
                    $picked = [];
                    foreach ($keys as $key) {
                        if (isset($item[$key])) {
                            $picked[$key] = $item[$key];
                        }
                    }

                    return $picked;
                }

                if (is_object($item)) {
                    return (object) Arr::only(get_object_vars($item), $keys);
                }

                return $item;
            });
        });

        // Run str_replace over every key of the collection, preserving values.
        Collection::macro('replaceInKeys', function (string|array $search, string|array $replace): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->mapWithKeys(static function (mixed $value, int|string $key) use ($search, $replace): array {
                $newKey = str_replace($search, $replace, (string) $key);

                return [$newKey => $value];
            });
        });

        // Sort by search relevance against $column: exact match +100,
        // starts-with +50, contains +25, otherwise a similar_text() weight.
        Collection::macro('sortSearchResults', function (string $searchTerms, string $column): Collection {
            /** @var Collection<array-key, mixed> $this */
            $terms = Collection::make(explode(' ', Str::lower(trim($searchTerms))))
                ->filter(static fn (string $term): bool => $term !== '')
                ->all();

            return $this->sortByDesc(static function (mixed $item) use ($terms, $column): float {
                $value = Str::lower(Cast::toString(data_get($item, $column, '')));
                $score = 0.0;

                foreach ($terms as $term) {
                    if ($value === $term) {
                        $score += 100;
                    } elseif (str_starts_with($value, $term)) {
                        $score += 50;
                    } elseif (str_contains($value, $term)) {
                        $score += 25;
                    } else {
                        $similarity = 0;
                        similar_text($value, $term, $similarity);
                        $score += $similarity / 10;
                    }
                }

                return $score;
            })->values();
        });
    }
}

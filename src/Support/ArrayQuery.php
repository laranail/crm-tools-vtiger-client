<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Support;

use Countable;
use ArrayAccess;
use Traversable;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
use InvalidArgumentException;

/**
 * An in-memory query over a list of records, compatible with the list-query surface of
 * `simtabi/pheg`'s `Arr\Query\QueryEngine`, which `Operations::fetchDeepWithPagination()` used to
 * return. pheg is archived and no longer a dependency; this keeps callers of that result working.
 *
 * The contract follows pheg's:
 *
 * - **Clauses are lazy.** `where()` and the `where*()` family are AND-ed within a group;
 *   `orWhere()` starts a new group, and a record passes when any group passes. They are applied
 *   by the first method that reads the records, and that application **keeps the records' keys**.
 * - `where($key, $value)` means `where($key, '=', $value)`. Comparison is loose except for the
 *   strict operators, and a missing key reads as `null`. A dotted key reads a nested value.
 * - **Shaping methods mutate and return `$this`:** `select()`, `except()`, `offset()`, `take()`,
 *   `sortBy()`, `sort()`, `groupBy()`, `distinct()`, `filter()`, `map()`, `transform()`.
 * - **Result methods return a new `ArrayQuery`** wrapping their result, as pheg's `makeResult()`
 *   did: `get()`, `fetch()`, `first()`, `last()`, `nth()`, `column()`, `implode()`. Read it with
 *   array access, `->key`, iteration or `toArray()`.
 * - **Aggregates return scalars:** `count()`, `size()`, `exists()`, `sum()`, `min()`, `max()`,
 *   `avg()`.
 *
 * Two deliberate differences, both where pheg was broken: `get($columns)` / `first($columns)`
 * return those columns (pheg ignored the list and returned whole records), and the string clauses
 * (`whereContains()`, `whereStartsWith()`, ...) treat a missing key as no match (pheg threw a
 * `TypeError`). Checked side by side against pheg's own engine on the same records.
 *
 * Not carried over from pheg: reading JSON files, `from()`/`at()`/`find()` path navigation,
 * `whereDate()`, `whereDataType()`, `whereInstance()`, `whereCount()`, `then()` and `copy()`.
 * They operate on documents rather than on a list of records, and nothing in this package's
 * results calls for them.
 *
 * @implements ArrayAccess<array-key, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 */
final class ArrayQuery implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    private const array OPERATORS = [
        '='    => '=',
        'eq'   => '=',
        '=='   => '==',
        'seq'  => '==',
        '!='   => '!=',
        'neq'  => '!=',
        '<>'   => '!=',
        '!=='  => '!==',
        'sneq' => '!==',
        '>'    => '>',
        'gt'   => '>',
        '<'    => '<',
        'lt'   => '<',
        '>='   => '>=',
        'gte'  => '>=',
        '<='   => '<=',
        'lte'  => '<=',
    ];

    /** @var list<list<callable(mixed): bool>> OR-ed groups of AND-ed conditions */
    private array $groups = [];

    /** @var list<string> */
    private array $select = [];

    /** @var list<string> */
    private array $except = [];

    private int $offset = 0;

    private ?int $take = null;

    /**
     * @param array<array-key, mixed> $records
     */
    public function __construct(private array $records = []) {}

    // ---- clauses (lazy) -------------------------------------------------------------------

    public function where(string $key, mixed $operator = null, mixed $value = null): self
    {
        return $this->addCondition(false, ...self::comparison($key, $operator, $value));
    }

    public function orWhere(string $key, mixed $operator = null, mixed $value = null): self
    {
        return $this->addCondition(true, ...self::comparison($key, $operator, $value));
    }

    /**
     * @param callable(mixed): mixed $callback receives a record
     */
    public function callableWhere(callable $callback): self
    {
        return $this->addCondition(false, static fn (mixed $record): bool => (bool) $callback($record));
    }

    /**
     * @param callable(mixed): mixed $callback receives a record
     */
    public function orCallableWhere(callable $callback): self
    {
        return $this->addCondition(true, static fn (mixed $record): bool => (bool) $callback($record));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public function whereIn(string $key, array $values): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => in_array(self::read($r, $key), $values, false));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public function whereNotIn(string $key, array $values): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => ! in_array(self::read($r, $key), $values, false));
    }

    public function whereNull(string $key): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => self::read($r, $key) === null);
    }

    public function whereNotNull(string $key): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => self::read($r, $key) !== null);
    }

    public function whereExists(string $key): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => self::has($r, $key));
    }

    public function whereNotExists(string $key): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => ! self::has($r, $key));
    }

    public function whereStartsWith(string $key, string $value): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => str_starts_with(self::text($r, $key), $value));
    }

    public function whereEndsWith(string $key, string $value): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => str_ends_with(self::text($r, $key), $value));
    }

    public function whereContains(string $key, string $value): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => str_contains(self::text($r, $key), $value));
    }

    /** Case-insensitive substring match, as pheg's `like`. */
    public function whereLike(string $key, string $value): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => stripos(self::text($r, $key), $value) !== false);
    }

    /** Full regular-expression match, delimiters included: `whereMatch('email1', '/@example\.com$/')`. */
    public function whereMatch(string $key, string $pattern): self
    {
        return $this->addCondition(false, static fn (mixed $r): bool => preg_match($pattern, self::text($r, $key)) === 1);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public function whereAny(string $key, array $values): self
    {
        return $this->addCondition(false, static function (mixed $r) use ($key, $values): bool {
            $actual = self::read($r, $key);

            return is_array($actual) && array_intersect($actual, $values) !== [];
        });
    }

    // ---- shaping (mutate, return $this) ----------------------------------------------------

    /**
     * @param string|list<string> ...$columns
     */
    public function select(string|array ...$columns): self
    {
        $this->select = self::flatten($columns);

        return $this;
    }

    /**
     * @param string|list<string> ...$columns
     */
    public function except(string|array ...$columns): self
    {
        $this->except = self::flatten($columns);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    public function take(int $take): self
    {
        $this->take = max(0, $take);

        return $this;
    }

    public function sortBy(string $column, string $order = 'asc'): self
    {
        $this->apply();
        $descending = strtolower(trim($order)) === 'desc';

        usort($this->records, static function (mixed $a, mixed $b) use ($column, $descending): int {
            $left = self::sortable(self::read($a, $column));
            $right = self::sortable(self::read($b, $column));

            return $descending ? $right <=> $left : $left <=> $right;
        });

        return $this;
    }

    public function sort(string $order = 'asc'): self
    {
        $this->apply();
        $descending = strtolower(trim($order)) === 'desc';

        usort($this->records, static fn (mixed $a, mixed $b): int => $descending
            ? self::sortable($b) <=> self::sortable($a)
            : self::sortable($a) <=> self::sortable($b));

        return $this;
    }

    /** Records keyed by the column's value; records with an empty value are dropped, as in pheg. */
    public function groupBy(string $column): self
    {
        $this->apply();
        $groups = [];

        foreach ($this->records as $record) {
            $value = self::read($record, $column);

            if ($value !== null && $value !== '' && $value !== false && is_scalar($value)) {
                $groups[(string) $value][] = $record;
            }
        }

        $this->records = $groups;

        return $this;
    }

    /** The first record for each distinct value of the column, re-indexed. */
    public function distinct(string $column): self
    {
        $this->apply();
        $seen = [];

        foreach ($this->records as $record) {
            $value = self::read($record, $column);

            if ($value !== null && $value !== '' && $value !== false && is_scalar($value) && ! array_key_exists((string) $value, $seen)) {
                $seen[(string) $value] = $record;
            }
        }

        $this->records = array_values($seen);

        return $this;
    }

    /**
     * @param callable(mixed): mixed $callback receives a record
     */
    public function filter(callable $callback): self
    {
        $this->apply();

        $this->records = array_values(array_filter($this->records, static fn (mixed $record): bool => (bool) $callback($record)));

        return $this;
    }

    /**
     * @param callable(mixed, array-key): mixed $callback receives a record and its key
     */
    public function map(callable $callback): self
    {
        $this->apply();

        $this->records = array_map($callback, $this->records, array_keys($this->records));

        return $this;
    }

    /**
     * @param callable(mixed, array-key): mixed $callback
     */
    public function transform(callable $callback): self
    {
        return $this->map($callback);
    }

    /**
     * @param callable(array-key, mixed): mixed $callback receives a key and its record, as pheg's did
     */
    public function each(callable $callback): self
    {
        foreach ($this->records() as $key => $record) {
            $callback($key, $record);
        }

        return $this;
    }

    // ---- results (a new ArrayQuery) --------------------------------------------------------

    /**
     * @param string|list<string> ...$columns
     */
    public function get(string|array ...$columns): self
    {
        if ($columns !== []) {
            $this->select(...$columns);
        }

        return new self($this->records());
    }

    /**
     * @param string|list<string> ...$columns
     */
    public function fetch(string|array ...$columns): self
    {
        return $this->get(...$columns);
    }

    /**
     * @param string|list<string> ...$columns
     */
    public function first(string|array ...$columns): ?self
    {
        return $this->nth(1, ...$columns);
    }

    /**
     * @param string|list<string> ...$columns
     */
    public function last(string|array ...$columns): ?self
    {
        return $this->nth(-1, ...$columns);
    }

    /**
     * The `$index`th record, counting from 1; a negative index counts from the end.
     *
     * @param string|list<string> ...$columns
     */
    public function nth(int $index, string|array ...$columns): ?self
    {
        if ($columns !== []) {
            $this->select(...$columns);
        }

        $records = array_values($this->records());
        $count = count($records);

        if ($index === 0 || abs($index) > $count) {
            return null;
        }

        $record = $records[$index > 0 ? $index - 1 : $count + $index];

        return new self(is_array($record) ? $record : [$record]);
    }

    public function column(string $column, ?string $index = null): self
    {
        return new self(array_column(array_values($this->records()), $column, $index));
    }

    /**
     * @param string|list<string> $keys
     */
    public function implode(string|array $keys, string $delimiter = ','): self
    {
        $records = array_values($this->records());
        $result = [];

        foreach ((array) $keys as $key) {
            $result[$key] = implode($delimiter, array_map(static fn (mixed $r): string => self::text($r, $key), $records));
        }

        return new self($result);
    }

    // ---- aggregates ----------------------------------------------------------------------

    public function count(): int
    {
        return count($this->records());
    }

    public function size(): int
    {
        return $this->count();
    }

    public function exists(): bool
    {
        return $this->records() !== [];
    }

    public function sum(?string $column = null): int|float
    {
        $sum = 0;

        foreach ($this->values($column) as $value) {
            $sum += $value;
        }

        return $sum;
    }

    public function min(?string $column = null): int|float|null
    {
        $values = $this->values($column);

        return $values === [] ? null : min($values);
    }

    public function max(?string $column = null): int|float|null
    {
        $values = $this->values($column);

        return $values === [] ? null : max($values);
    }

    public function avg(?string $column = null): int|float|null
    {
        $values = $this->values($column);

        return $values === [] ? null : array_sum($values) / count($values);
    }

    // ---- reading -------------------------------------------------------------------------

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->records();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function result(): array
    {
        return $this->toArray();
    }

    public function toJson(int $flags = 0): string
    {
        return (string) json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->apply();

        if ($offset === null) {
            $this->records[] = $value;
        } else {
            $this->records[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->apply();
        unset($this->records[$offset]);
    }

    public function __get(string $key): mixed
    {
        return $this->offsetGet($key);
    }

    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    // ---- internals -----------------------------------------------------------------------

    /**
     * The records after pending clauses, then offset/take, then select/except.
     *
     * @return array<array-key, mixed>
     */
    private function records(): array
    {
        $this->apply();
        $records = $this->records;

        if ($this->offset > 0 || $this->take !== null) {
            $records = array_values(array_slice($records, $this->offset, $this->take)); // re-indexed, as pheg did
        }

        if ($this->select === [] && $this->except === []) {
            return $records;
        }

        return array_map(function (mixed $record): mixed {
            if (! is_array($record)) {
                return $record;
            }

            if ($this->select !== []) {
                $picked = [];

                foreach ($this->select as $column) {
                    $picked[$column] = self::read($record, $column);
                }

                $record = $picked;
            }

            return array_diff_key($record, array_flip($this->except));
        }, $records);
    }

    /**
     * @return list<int|float>
     */
    private function values(?string $column): array
    {
        $values = [];

        foreach ($this->records() as $record) {
            $value = $column === null ? $record : self::read($record, $column);

            if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                $values[] = $value + 0;
            }
        }

        return $values;
    }

    /**
     * @return array{0: callable(mixed): bool}
     */
    private static function comparison(string $key, mixed $operator, mixed $value): array
    {
        if ($operator !== null && $value === null) {
            $value = $operator;
            $operator = '=';
        }

        if (! is_string($operator) || ! isset(self::OPERATORS[$operator])) {
            throw new InvalidArgumentException(sprintf('Condition [%s] is not supported.', is_string($operator) ? $operator : get_debug_type($operator)));
        }

        $operator = self::OPERATORS[$operator];

        return [static fn (mixed $record): bool => self::compare(self::read($record, $key), $operator, $value)];
    }

    /**
     * @param callable(mixed): bool $condition
     */
    private function addCondition(bool $startsGroup, callable $condition): self
    {
        if ($startsGroup || $this->groups === []) {
            $this->groups[] = [];
        }

        $this->groups[array_key_last($this->groups)][] = $condition;

        return $this;
    }

    private function apply(): void
    {
        if ($this->groups === []) {
            return;
        }

        $kept = [];

        foreach ($this->records as $index => $record) {
            foreach ($this->groups as $group) {
                if (self::passesAll($record, $group)) {
                    $kept[$index] = $record;
                    break;
                }
            }
        }

        $this->records = $kept;
        $this->groups = [];
    }

    /**
     * @param list<callable(mixed): bool> $group
     */
    private static function passesAll(mixed $record, array $group): bool
    {
        foreach ($group as $condition) {
            if (! $condition($record)) {
                return false;
            }
        }

        return true;
    }

    private static function read(mixed $record, string $key): mixed
    {
        if ($key === '' || $key === '.') {
            return $record;
        }

        if (! is_array($record)) {
            return null;
        }

        if (isset($record[$key])) {
            return $record[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (! is_array($record) || ! isset($record[$segment])) {
                return null;
            }

            $record = $record[$segment];
        }

        return $record;
    }

    private static function has(mixed $record, string $key): bool
    {
        if (! is_array($record)) {
            return false;
        }

        if (array_key_exists($key, $record)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (! is_array($record) || ! array_key_exists($segment, $record)) {
                return false;
            }

            $record = $record[$segment];
        }

        return true;
    }

    private static function text(mixed $record, string $key): string
    {
        $value = self::read($record, $key);

        return is_scalar($value) ? (string) $value : '';
    }

    private static function sortable(mixed $value): mixed
    {
        return is_string($value) ? strtolower($value) : $value;
    }

    /**
     * @param array<array-key, string|list<string>> $columns
     *
     * @return list<string>
     */
    private static function flatten(array $columns): array
    {
        $flat = [];

        array_walk_recursive($columns, static function (mixed $column) use (&$flat): void {
            $flat[] = (string) $column;
        });

        return $flat;
    }

    private static function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '='   => $actual == $expected,
            '=='  => $actual === $expected,
            '!='  => $actual != $expected,
            '!==' => $actual !== $expected,
            '>'   => $actual > $expected,
            '<'   => $actual < $expected,
            '>='  => $actual >= $expected,
            '<='  => $actual <= $expected,
            default => throw new InvalidArgumentException("Condition [{$operator}] is not supported."),
        };
    }
}

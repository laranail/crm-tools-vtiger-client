<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Support;

use InvalidArgumentException;

/**
 * A small in-memory query over a list of records.
 *
 * Replaces the part of `simtabi/pheg`'s `Arr\Query\QueryEngine` this package used:
 * `where($key, $operator, $value)`, `filter(callable)` and `toArray()`. The semantics are pheg's:
 *
 * - `where()` is lazy and AND-ed; it is applied on the first `filter()` or `toArray()`, and
 *   **keeps the original keys** of the records that pass.
 * - `where($key, $value)` means `where($key, '=', $value)`.
 * - Comparison is loose (`==`, `!=`, ...) except for the strict operators, and a missing key reads
 *   as `null`, so `where('email1', '!=', '')` drops records with no `email1` at all.
 * - `filter()` applies any pending `where()` first and **re-indexes** the records it keeps.
 * - A dotted key (`address.city`) reads a nested value.
 *
 * Only comparison operators are implemented. Anything else throws, where pheg threw its own
 * `ConditionNotAllowedException`.
 */
final class ArrayQuery
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

    /** @var list<array{key: string, operator: string, value: mixed}> */
    private array $conditions = [];

    /**
     * @param array<array-key, mixed> $records
     */
    public function __construct(private array $records = []) {}

    public function where(string $key, mixed $operator = null, mixed $value = null): self
    {
        if ($operator !== null && $value === null) {
            $value = $operator;
            $operator = '=';
        }

        if (! is_string($operator) || ! isset(self::OPERATORS[$operator])) {
            throw new InvalidArgumentException(sprintf('Condition [%s] is not supported.', is_string($operator) ? $operator : get_debug_type($operator)));
        }

        $this->conditions[] = ['key' => $key, 'operator' => self::OPERATORS[$operator], 'value' => $value];

        return $this;
    }

    /**
     * @param callable(mixed): mixed $callback
     */
    public function filter(callable $callback): self
    {
        $this->apply();

        $this->records = array_values(array_filter($this->records, static fn (mixed $record): bool => (bool) $callback($record)));

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        $this->apply();

        return $this->records;
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
        };
    }

    private function apply(): void
    {
        if ($this->conditions === []) {
            return;
        }

        $kept = [];

        foreach ($this->records as $index => $record) {
            if ($this->passes($record)) {
                $kept[$index] = $record;
            }
        }

        $this->records = $kept;
        $this->conditions = [];
    }

    private function passes(mixed $record): bool
    {
        foreach ($this->conditions as $condition) {
            if (! self::compare(self::read($record, $condition['key']), $condition['operator'], $condition['value'])) {
                return false;
            }
        }

        return true;
    }
}

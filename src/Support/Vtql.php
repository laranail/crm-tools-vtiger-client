<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Support;

use InvalidArgumentException;

/**
 * Builds the pieces of a VTQL query that come from callers, so none of them is interpolated raw.
 *
 * Until 2026-10 every query builder in this package put caller values straight between single
 * quotes -- `WHERE email1 = '{$emailOrId}'` -- so a value containing a quote ended the literal and
 * the rest of it became query text. VTQL escapes a quote inside a literal by doubling it
 * (`'O''Connor'`); a backslash is not an escape character there, which is what makes doubling
 * sufficient. Module and column names cannot be quoted at all, so they are held to the shape a
 * Vtiger field name has, and comparison operators to a fixed set.
 */
final class Vtql
{
    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    public static function literal(mixed $value): string
    {
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    public static function identifier(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Not a valid VTQL module or field name: [{$name}]");
        }

        return $name;
    }

    /**
     * A SELECT column: a field name, or `count(*)`, the one aggregate VTQL supports. The old
     * builders accepted `count(*)` because they interpolated it raw; it stays accepted here.
     */
    public static function column(string $name): string
    {
        if (strtolower(str_replace(' ', '', $name)) === 'count(*)') {
            return 'count(*)';
        }

        return self::identifier($name);
    }

    public static function operator(string $operator): string
    {
        $normalised = strtoupper(trim($operator));

        if (! in_array($normalised, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Not a supported VTQL operator: [{$operator}]");
        }

        return $normalised;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use Closure;
use Illuminate\Support\Facades\Schema;

/**
 * Disables FK constraint enforcement around a callback, with safe nesting
 * and exception-safe restoration. Re-entrant: repeated guards within the
 * same call stack do not double-toggle the schema.
 */
final class ForeignKeyCheckGuard
{
    private int $depth = 0;

    /**
     * Run `$callback` with FK checks disabled. Returns the callback's value.
     *
     * @template T
     *
     * @param Closure(): T $callback
     * @return T
     */
    public function run(Closure $callback): mixed
    {
        $this->depth++;
        if ($this->depth === 1) {
            Schema::disableForeignKeyConstraints();
        }

        try {
            return $callback();
        } finally {
            $this->depth--;
            if ($this->depth === 0) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    public function depth(): int
    {
        return $this->depth;
    }
}

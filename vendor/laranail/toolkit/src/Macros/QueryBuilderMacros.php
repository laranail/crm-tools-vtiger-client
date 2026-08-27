<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Registers convenience macros on the query and Eloquent builders.
 *
 * Raw-SQL/injection-prone macros from the legacy provider are intentionally
 * omitted; the macros here only touch developer-controlled column/direction
 * tokens (which are quoted via the grammar) or parameterise their bindings.
 * The legacy `whereLike`/`whereNotLike`/`orWhereLike` macros are dropped
 * because Laravel ships them natively now.
 */
final class QueryBuilderMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerQueryBuilderMacros();
        $this->registerEloquentBuilderMacros();
    }

    private function registerQueryBuilderMacros(): void
    {
        QueryBuilder::macro('whenFilled', function (mixed $value, callable $callback): QueryBuilder {
            /** @var QueryBuilder $this */
            if (filled($value)) {
                $result = $callback($this, $value);

                return $result instanceof QueryBuilder ? $result : $this;
            }

            return $this;
        });

        QueryBuilder::macro('whereBetweenDates', function (string $column, mixed $startDate, mixed $endDate): QueryBuilder {
            /** @var QueryBuilder $this */
            return $this->whereBetween($column, [$startDate, $endDate]);
        });

        QueryBuilder::macro('orderByNullsLast', function (string $column, string $direction = 'asc'): QueryBuilder {
            /** @var QueryBuilder $this */
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
            // The column is grammar-quoted, so the raw fragment is injection-safe.
            $nullExpression = new Expression($this->grammar->wrap($column) . ' is null');

            return $this->orderBy($nullExpression)->orderBy($column, $direction);
        });

        QueryBuilder::macro('orderByNullsFirst', function (string $column, string $direction = 'asc'): QueryBuilder {
            /** @var QueryBuilder $this */
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
            // The column is grammar-quoted, so the raw fragment is injection-safe.
            $nullExpression = new Expression($this->grammar->wrap($column) . ' is not null');

            return $this->orderBy($nullExpression)->orderBy($column, $direction);
        });

        QueryBuilder::macro('log', function (?string $channel = null): QueryBuilder {
            /** @var QueryBuilder $this */
            // Log::channel(null) resolves the default channel.
            Log::channel($channel)->debug('Query Builder SQL', [
                'sql' => $this->toSql(),
                'bindings' => $this->getBindings(),
            ]);

            return $this;
        });
    }

    private function registerEloquentBuilderMacros(): void
    {
        EloquentBuilder::macro('whenFilled', function (mixed $value, callable $callback): EloquentBuilder {
            /** @var EloquentBuilder<Model> $this */
            if (filled($value)) {
                $result = $callback($this, $value);

                return $result instanceof EloquentBuilder ? $result : $this;
            }

            return $this;
        });

        EloquentBuilder::macro('whereBetweenDates', function (string $column, mixed $startDate, mixed $endDate): EloquentBuilder {
            /** @var EloquentBuilder<Model> $this */
            $this->whereBetween($column, [$startDate, $endDate]);

            return $this;
        });

        EloquentBuilder::macro('existsOr', function (callable $callback): mixed {
            /** @var EloquentBuilder<Model> $this */
            return $this->exists() ? true : $callback();
        });

        EloquentBuilder::macro('doesntExistOr', function (callable $callback): mixed {
            /** @var EloquentBuilder<Model> $this */
            return $this->doesntExist() ? true : $callback();
        });
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Helpers for Eloquent model operations: select-box conversion, formable user
 * lists, concatenated-name expressions, tree sorting, and observer registration.
 *
 * Any raw SQL that embeds an identifier (table/column) validates it against the
 * live schema and quotes it through the connection grammar — bare interpolation
 * of identifiers into raw SQL is never performed.
 */
final readonly class ModelService
{
    /** Columns concatenated by {@see concatName()} / {@see getUsersFromModel()}. */
    private const NAME_COLUMNS = ['first_name', 'last_name'];

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * Coerce a (possibly null/mixed) model attribute to a string for display.
     */
    private function stringAttr(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : '';
    }

    /**
     * Build an `id => display-name` list from a users model.
     *
     * @return array<int|string, string>
     */
    public function getFormableUsersList(Model $usersModel): array
    {
        $results = [];

        $rows = $usersModel->newQuery()->orderBy($usersModel->getKeyName(), 'asc')->get();

        if ($rows->isEmpty()) {
            return $results;
        }

        foreach ($rows as $item) {
            $usernameAttr = $item->getAttribute('username');
            $username = filled($usernameAttr)
                ? Str::ucfirst($this->stringAttr($usernameAttr))
                : Str::lower($this->stringAttr($item->getAttribute('email')));

            $key = $item->getKey();
            if (is_int($key) || is_string($key)) {
                $results[$key] = $username;
            }
        }

        return $results;
    }

    /**
     * Get users from a model with a SQL-concatenated `name` column.
     *
     * @return array<int|string, mixed>
     */
    public function getUsersFromModel(Model $model, bool $keyed = true, bool $asJson = false): array
    {
        $query = $model->newQuery()
            ->select('*')
            ->addSelect($this->concatNameExpression($model));

        // Key by the model's primary key (unique). A prior keyBy(name) collapsed
        // distinct users sharing a name before the pluck, silently dropping them.
        $form = $query->get()->pluck('name', $model->getKeyName());

        $data = $form->map(static fn ($item, $key): array => [
            'name' => $item,
            'id' => $key,
        ])->values()->all();

        if ($keyed) {
            return collect($data)
                ->mapWithKeys(fn (array $item): array => [
                    $item['id'] => Str::title(Str::lower($this->stringAttr($item['name']))),
                ])
                ->toArray();
        }

        return $data;
    }

    /**
     * Convert an Eloquent collection/model into a select-box array.
     *
     * @param Collection<int|string, mixed>|Model $data
     *
     * @return array<int|string, mixed>
     */
    public function eloquent2selectbox(
        Collection|Model $data,
        string $columnName = 'name',
        string $idColumnName = 'id',
        ?string $placeholderText = 'Select something',
        string $emptyDataText = 'Nothing to select'
    ): array {
        if ($data instanceof Collection && $data->isEmpty()) {
            return ['' => $emptyDataText];
        }

        $collection = $data instanceof Collection ? $data : collect([$data]);

        $array = $collection->mapWithKeys(static fn ($value): array => [
            $value->{$idColumnName} => $value->{$columnName},
        ])->toArray();

        if ($placeholderText !== null && $placeholderText !== '') {
            return ['' => $placeholderText] + $array;
        }

        return $array;
    }

    /**
     * Sort a flat list of nodes into a depth-annotated parent→child order.
     *
     * @param array<int, object>|Collection<int, object> $list
     * @param array<int, object>                         $result
     *
     * @return array<int, object>
     */
    public function sortItemWithChildren(
        array|Collection $list,
        array &$result = [],
        int|string|null $parent = null,
        int $depth = 0
    ): array {
        $list = $list instanceof Collection ? $list->all() : $list;

        foreach ($list as $key => $object) {
            if ((int) $object->parent_id === (int) $parent) {
                $object->depth = $depth;
                $result[] = $object;
                unset($list[$key]);
                $this->sortItemWithChildren($list, $result, $object->id, $depth + 1);
            }
        }

        return $result;
    }

    /**
     * Read a value off a model by dot-path key, with a default fallback
     * (a thin, typed convenience over `data_get`).
     */
    public function getModelItem(object $model, string $key, mixed $default = null): mixed
    {
        return data_get($model, $key, $default);
    }

    /**
     * Register an observer on a model, when both classes exist.
     */
    public function registerModelObserver(string $modelClass, string $observerClass): void
    {
        if (class_exists($modelClass) && class_exists($observerClass)) {
            $modelClass::observe($observerClass);

            $this->logger->info('Model observer registered', [
                'model' => $modelClass,
                'observer' => $observerClass,
            ]);
        }
    }

    /**
     * Build a `CONCAT(first_name, ' ', last_name) as name` expression for the
     * given table, with the table and columns validated + grammar-quoted.
     *
     * @param string|null $connection Optional connection name for schema/grammar.
     */
    public function concatName(string $table, ?string $connection = null): Expression
    {
        return $this->buildConcatExpression($table, $connection);
    }

    /**
     * Build the concat expression for a model, resolving its table + connection.
     */
    private function concatNameExpression(Model $model): Expression
    {
        return $this->buildConcatExpression($model->getTable(), $model->getConnectionName());
    }

    /**
     * Validate the table + name columns against the live schema and return a
     * grammar-quoted `CONCAT(...) as name` expression. Rejects unknown tables or
     * columns instead of interpolating an unvalidated identifier into raw SQL.
     */
    private function buildConcatExpression(string $table, ?string $connection): Expression
    {
        $schema = Schema::connection($connection);

        if (!$schema->hasTable($table)) {
            throw new InvalidArgumentException(
                sprintf('Unknown table [%s]: refusing to build a raw SQL expression.', $table),
            );
        }

        foreach (self::NAME_COLUMNS as $column) {
            if (!$schema->hasColumn($table, $column)) {
                throw new InvalidArgumentException(
                    sprintf('Unknown column [%s.%s]: refusing to build a raw SQL expression.', $table, $column),
                );
            }
        }

        $grammar = DB::connection($connection)->getQueryGrammar();

        $first = $grammar->wrap($table . '.' . self::NAME_COLUMNS[0]);
        $last = $grammar->wrap($table . '.' . self::NAME_COLUMNS[1]);
        $alias = $grammar->wrap('name');

        return DB::connection($connection)->raw("CONCAT({$first}, ' ', {$last}) as {$alias}");
    }
}

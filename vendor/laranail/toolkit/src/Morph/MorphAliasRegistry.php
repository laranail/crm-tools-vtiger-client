<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Morph;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Morph\Exceptions\UnknownMorphAliasException;

/**
 * A governed, bidirectional alias <-> Eloquent-model-class map, plus the model <-> reference resolution
 * built on top of it.
 *
 * The map is allocated once from a caller-supplied array and is never reassigned — the same allocate-once
 * discipline as the register it usually names, retired with the thing it names. An unknown alias or an
 * unmapped class is a CRITICAL failure (an unresolvable pointer about to be written into a durable,
 * never-deleted row), never a silently stored string, so every miss throws {@see UnknownMorphAliasException}
 * rather than returning null.
 *
 * This class is framework-light and dependency-free beyond Illuminate: it deals only in primitive string
 * aliases / ids and the Eloquent {@see Model}. Domain-specific value objects (e.g. a `{type,id}` subject
 * reference) belong in a thin adapter in the consuming package, not here.
 */
final class MorphAliasRegistry
{
    /** @var array<string, class-string<Model>> alias => model class */
    private array $aliasToClass = [];

    /** @var array<class-string<Model>, string> model class => alias */
    private array $classToAlias = [];

    /** @param array<string, class-string<Model>> $map alias => model class */
    public function __construct(array $map)
    {
        foreach ($map as $alias => $class) {
            $this->aliasToClass[$alias] = $class;
            $this->classToAlias[$class] = $alias;
        }
    }

    /** @return array<string, class-string<Model>> alias => model class */
    public function map(): array
    {
        return $this->aliasToClass;
    }

    /**
     * The governed alias for a model class.
     *
     * @throws UnknownMorphAliasException when the class is not in the map.
     */
    public function aliasFor(string $class): string
    {
        return $this->classToAlias[$class] ?? throw UnknownMorphAliasException::forClass($class);
    }

    /**
     * The model class bound to an alias.
     *
     *
     * @throws UnknownMorphAliasException when the alias is unknown.
     *
     * @return class-string<Model>
     */
    public function classFor(string $alias): string
    {
        return $this->aliasToClass[$alias] ?? throw UnknownMorphAliasException::forAlias($alias);
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->aliasToClass[$alias]);
    }

    public function hasClass(string $class): bool
    {
        return isset($this->classToAlias[$class]);
    }

    public function isEmpty(): bool
    {
        return $this->aliasToClass === [];
    }

    /**
     * Guard that an alias is in the governed map, throwing if not.
     *
     * @throws UnknownMorphAliasException when the alias is unknown.
     */
    public function assertKnownAlias(string $alias): void
    {
        if (!$this->hasAlias($alias)) {
            throw UnknownMorphAliasException::forAlias($alias);
        }
    }

    /**
     * The scalar primary key of a model, as a string. A morph subject must be scalar-keyed: a composite or
     * otherwise non-scalar key cannot be written as a portable `{type,id}` reference.
     *
     * @throws RuntimeException when the model's key is not scalar.
     */
    public function keyFor(Model $model): string
    {
        $key = $model->getKey();

        if (!is_scalar($key)) {
            throw new RuntimeException('A morph subject model must have a scalar primary key.');
        }

        return (string) $key;
    }

    /**
     * A model's governed alias paired with its scalar key. The scalar-key guard runs before the alias
     * lookup so a non-scalar-keyed model fails the same way whether or not it is mapped.
     *
     *
     * @throws RuntimeException           when the model's key is not scalar.
     * @throws UnknownMorphAliasException when the model class is unmapped.
     *
     * @return array{0: string, 1: string} `[alias, key]`
     */
    public function aliasAndKeyFor(Model $model): array
    {
        $key = $this->keyFor($model);

        return [$this->aliasFor($model::class), $key];
    }

    /**
     * Resolve an (alias, id) pair back to its model row, or null if the row is gone.
     *
     * @throws UnknownMorphAliasException when the alias is unknown.
     */
    public function resolve(string $alias, string $id): ?Model
    {
        return $this->classFor($alias)::query()->find($id);
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use BadMethodCallException;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionFunction;

/**
 * A macro registry keyed by model class.
 *
 * `Builder::macro()` is global: register `whereActive` and it exists on every
 * model's builder, including the ones for which it makes no sense. This narrows
 * that — a macro is registered *for a model*, and calling it on a different one
 * fails the way an undefined method should.
 *
 * ```php
 * Toolkit::macroableModels()->addMacro(Post::class, 'published', function () {
 *     return $this->where('published_at', '<=', now());
 * });
 *
 * Post::query()->published();     // works
 * Comment::query()->published();  // BadMethodCallException
 * ```
 *
 * ## What `$this` is inside a macro
 *
 * The **model**, not the builder. The closure is bound to the model instance and
 * scoped to its class, so `$this->someProtectedThing` reaches the real member
 * rather than falling through `Model::__get()` to an attribute lookup that
 * returns null. That is what makes accessor-style macros work, which is the case
 * this was written for — and it is a deliberate difference from a bare
 * `Closure::bind($closure, $model)`, which keeps the closure's own scope.
 *
 * ## Removing the last macro for a name
 *
 * Laravel's `Macroable` has no way to unregister, so the `Builder` macro stays
 * registered and simply throws for every model. That is the correct outcome —
 * the method no longer exists for anyone — but it is worth knowing that
 * `removeMacro()` cannot make `Builder::hasMacro()` return false.
 */
class MacroableModels
{
    /**
     * name => [model class => closure]
     *
     * @var array<string, array<class-string<Model>, Closure>>
     */
    protected array $macros = [];

    /**
     * @return array<string, array<class-string<Model>, Closure>>
     */
    public function getAllMacros(): array
    {
        return $this->macros;
    }

    /**
     * Register a macro for one model.
     *
     * @param class-string<Model> $model
     */
    public function addMacro(string $model, string $name, Closure $closure): void
    {
        $this->guardModel($model);

        $this->macros[$name][$model] = $closure;

        $this->syncMacro($name);
    }

    /**
     * The closures registered under a name, keyed by model.
     *
     * @return array<class-string<Model>, Closure>
     */
    public function getMacro(string $name): array
    {
        return $this->macros[$name] ?? [];
    }

    /**
     * @param class-string<Model> $model
     */
    public function removeMacro(string $model, string $name): bool
    {
        $this->guardModel($model);

        if (!isset($this->macros[$name][$model])) {
            return false;
        }

        unset($this->macros[$name][$model]);

        if ($this->macros[$name] === []) {
            unset($this->macros[$name]);
        }

        $this->syncMacro($name);

        return true;
    }

    /**
     * @param class-string<Model> $model
     */
    public function modelHasMacro(string $model, string $name): bool
    {
        $this->guardModel($model);

        return isset($this->macros[$name][$model]);
    }

    /**
     * @return list<class-string<Model>>
     */
    public function modelsThatImplement(string $name): array
    {
        return array_keys($this->macros[$name] ?? []);
    }

    /**
     * Every macro registered for a model, with its parameter names.
     *
     * Parameter *names* rather than `ReflectionParameter` objects: the code this
     * came from returned the objects, which leaked reflection into a public API
     * for the sake of an IDE-helper generator that only ever read `->getName()`.
     *
     * @param class-string<Model> $model
     *
     * @return array<string, array{name: string, parameters: list<string>}>
     */
    public function macrosForModel(string $model): array
    {
        $this->guardModel($model);

        $macros = [];

        foreach ($this->macros as $name => $models) {
            if (!isset($models[$model])) {
                continue;
            }

            // Reflecting a Closure cannot fail, so there is nothing to catch
            // here — the original swallowed a ReflectionException that could
            // never be thrown.
            $macros[$name] = [
                'name' => $name,
                'parameters' => array_map(
                    static fn (\ReflectionParameter $p): string => $p->getName(),
                    (new ReflectionFunction($models[$model]))->getParameters(),
                ),
            ];
        }

        return $macros;
    }

    /**
     * Drop every registration. For tests; the `Builder` macros remain and throw.
     */
    public function flush(): void
    {
        foreach (array_keys($this->macros) as $name) {
            $this->macros[$name] = [];
            $this->syncMacro($name);
        }

        $this->macros = [];
    }

    /**
     * (Re)register the dispatching `Builder` macro for a name.
     */
    protected function syncMacro(string $name): void
    {
        $models = $this->macros[$name] ?? [];

        Builder::macro($name, function (mixed ...$arguments) use ($name, $models): mixed {
            /** @var Builder<Model> $this */
            $model = $this->getModel();
            $class = $model::class;

            if (!isset($models[$class])) {
                throw new BadMethodCallException(
                    sprintf('Call to undefined method %s::%s()', $class, $name)
                );
            }

            // Bound to the instance AND scoped to its class. Closure::bind()
            // defaults to keeping the closure's own scope, which would leave
            // `$this->someProtected` falling through Model::__get() to an
            // attribute lookup and quietly returning null — the exact failure a
            // per-model macro exists to avoid.
            return (Closure::bind($models[$class], $model, $class))(...$arguments);
        });
    }

    /**
     * @param class-string<Model> $model
     */
    protected function guardModel(string $model): void
    {
        if (!class_exists($model)) {
            throw new InvalidArgumentException(sprintf('The class [%s] does not exist.', $model));
        }

        if (!is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException(
                sprintf('[%s] must be a subclass of %s.', $model, Model::class)
            );
        }
    }
}

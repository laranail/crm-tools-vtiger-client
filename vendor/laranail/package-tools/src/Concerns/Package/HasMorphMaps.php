<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * declarative, non-enforcing morph-map registration. hosts opting into
 * Relation::requireMorphMap() do so themselves — a package forcing it
 * globally would break unrelated host morphs.
 */
trait HasMorphMaps
{
    /** @var list<array<string, class-string<Model>>|Closure> */
    protected array $morphMaps = [];

    /** @var list<array{map: string, user_model: ?string, user_alias: ?string}> */
    protected array $morphMapConfigSpecs = [];

    /**
     * static alias => model entries, or a closure evaluated lazily at boot.
     *
     * @param array<string, class-string<Model>>|Closure $map
     */
    public function registerMorphMap(array|Closure $map): static
    {
        $this->morphMaps[] = $map;

        return $this;
    }

    /**
     * build the map from config at boot: $mapConfigKey holds host-defined
     * alias => class entries (explicit and required — no namespace-derivation
     * magic); when $userAlias is non-null the user model resolves from
     * $userModelConfigKey ?? auth.providers.users.model under that alias.
     */
    public function registerMorphMapFromConfig(
        string $mapConfigKey,
        ?string $userModelConfigKey = null,
        ?string $userAlias = 'user',
    ): static {
        $this->morphMapConfigSpecs[] = [
            'map' => $mapConfigKey,
            'user_model' => $userModelConfigKey,
            'user_alias' => $userAlias,
        ];

        return $this;
    }

    public function bootPackageMorphMaps(): void
    {
        $map = [];

        foreach ($this->morphMapConfigSpecs as $spec) {
            if ($spec['user_alias'] !== null) {
                $userModel = ($spec['user_model'] !== null ? config($spec['user_model']) : null)
                    ?? config('auth.providers.users.model');

                if (is_string($userModel) && is_subclass_of($userModel, Model::class)) {
                    $map[$spec['user_alias']] = $userModel;
                }
            }

            $extra = config($spec['map'], []);

            foreach (is_array($extra) ? $extra : [] as $alias => $class) {
                if (is_string($alias) && is_string($class) && is_subclass_of($class, Model::class)) {
                    $map[$alias] = $class;
                }
            }
        }

        foreach ($this->morphMaps as $entries) {
            $entries = $entries instanceof Closure ? $entries() : $entries;

            foreach (is_array($entries) ? $entries : [] as $alias => $class) {
                if (is_string($alias) && is_string($class) && is_subclass_of($class, Model::class)) {
                    $map[$alias] = $class;
                }
            }
        }

        if ($map !== []) {
            Relation::morphMap($map);
        }
    }
}

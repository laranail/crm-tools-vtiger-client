<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Registers factory and seeder paths and boots them with Laravel.
 */
trait HasFactoriesAndSeeders
{
    /** @var array<string> Registered factory paths */
    protected array $factoryPaths = [];

    /** @var list<AutoSeederDefinition> */
    protected array $packageSeederDefinitions = [];

    /**
     * The lazily-created definition backing registerSeeder() calls.
     */
    private ?AutoSeederDefinition $defaultSeederDefinition = null;

    /**
     * Package-level autorun switch applied to every definition at boot.
     */
    protected bool $autorunAllSeeders = false;

    /**
     * Load factories from a directory
     *
     * @param string $path Path to factories directory (relative to package root)
     *
     * @example
     * ```php
     * $package->loadFactoriesFrom('database/factories');
     * ```
     */
    public function loadFactoriesFrom(string $path): static
    {
        $this->factoryPaths[] = $path;

        return $this;
    }

    /**
     * Discover Seeder subclasses under `$path` (relative to the package
     * root) and register them for db:seed-time execution. Sugar over the
     * definition pipeline — each path gets its own discovery definition.
     *
     * @param string $path Path to seeders directory (relative to package root)
     */
    public function loadSeedersFrom(string $path): static
    {
        $resolved = $this->basePath('/' . ltrim($path, '/'));

        return $this->hasPackageSeeders(
            AutoSeederDefinition::make("{$this->name}:seeders:" . md5((string) $resolved))->discoverIn($resolved),
        );
    }

    /**
     * Register a single seeder class for db:seed-time execution. Repeated
     * calls append (in call order) to one shared per-package definition.
     *
     * @param class-string<Seeder> $seederClass
     */
    public function registerSeeder(string $seederClass): static
    {
        $this->defaultSeederDefinition()->addSeeders($seederClass);

        return $this;
    }

    /**
     * Opt EVERY seeder definition of this package into post-migration
     * autorun (equivalent to autorunAfterMigrations() on each).
     */
    public function autorunSeeders(bool $autorun = true): static
    {
        $this->autorunAllSeeders = $autorun;

        return $this;
    }

    private function defaultSeederDefinition(): AutoSeederDefinition
    {
        if (! $this->defaultSeederDefinition instanceof AutoSeederDefinition) {
            $this->defaultSeederDefinition = AutoSeederDefinition::make($this->name);
            $this->packageSeederDefinitions[] = $this->defaultSeederDefinition;
        }

        return $this->defaultSeederDefinition;
    }

    /**
     * Register the factory paths with Laravel. Call from the service
     * provider's boot() method.
     */
    public function bootPackageFactories(): void
    {
        foreach ($this->factoryPaths as $path) {
            $fullPath = $this->resolveFactoryPath($path);

            if (File::isDirectory($fullPath)) {
                /**
                 * @param class-string<Model> $modelName
                 * @return class-string<Factory<Model>>
                 */
                $resolver = function (string $modelName) use ($fullPath): string {
                    $factoryBasename = class_basename($modelName);
                    $factoryName = $factoryBasename . 'Factory';
                    $factoryFile = $fullPath . DIRECTORY_SEPARATOR . $factoryName . '.php';

                    if (File::exists($factoryFile)) {
                        require_once $factoryFile;

                        $namespace = $this->guessFactoryNamespace();
                        $factoryClass = $namespace . '\\' . $factoryName;

                        if (class_exists($factoryClass) && is_subclass_of($factoryClass, Factory::class)) {
                            return $factoryClass;
                        }
                    }

                    // Not a package factory — fall back to Laravel's
                    // conventional resolution so the host app keeps working.
                    /** @var class-string<Factory<Model>> $fallback */
                    $fallback = Factory::$namespace . class_basename($modelName) . 'Factory';

                    return $fallback;
                };

                Factory::guessFactoryNamesUsing($resolver);
            }
        }
    }

    /**
     * Register the package's seeders to run with the host app's
     * `php artisan db:seed` (via the SeederManager's resolver hook).
     * Accepts a fluent AutoSeederDefinition for full control — discovery,
     * ignore lists, config gating, priority — or the string + array
     * shorthand for the simple case. Seeders NEVER execute at package
     * boot; registration happens at boot, execution at db:seed time.
     *
     * @param list<class-string<Seeder>> $seeders execution order = array order
     */
    public function hasPackageSeeders(AutoSeederDefinition|string $key, array $seeders = []): static
    {
        $this->packageSeederDefinitions[] = $key instanceof AutoSeederDefinition
            ? $key
            : AutoSeederDefinition::make($key)->seeders($seeders);

        return $this;
    }

    /**
     * Discover Seeder subclasses under `$path` and register them for
     * db:seed-time execution (sugar over the definition's discovery mode).
     */
    public function discoverPackageSeedersIn(string $path, ?string $namespace = null): static
    {
        // Key on namespace AND path: two calls sharing a namespace (or two
        // packages picking the same one) must not clobber each other.
        $key = ($namespace ?? 'discovered') . ':' . md5($path);

        return $this->hasPackageSeeders(
            AutoSeederDefinition::make($key)->discoverIn($path)->inNamespace($namespace),
        );
    }

    /**
     * @return list<AutoSeederDefinition>
     */
    public function getPackageSeederDefinitions(): array
    {
        return $this->packageSeederDefinitions;
    }

    /**
     * Called from the deferred-hooks chain: evaluates each definition's
     * config gate and registers the surviving bundles with the shared
     * SeederManager. Registration only — execution belongs to db:seed.
     */
    public function bootPackageAutoSeeders(): void
    {
        if ($this->packageSeederDefinitions === [] || ! function_exists('app')) {
            return;
        }

        $defaultDiscoveryPath = $this->basePath('/' . self::SEEDERS_DIR);

        /** @var SeederManager $manager */
        $manager = app(SeederManager::class);

        // Per-package autorun kill-switch: the host can veto this package's
        // autorun wholesale via {vendor}.{package}.seeders.autorun => false.
        $autorunVetoed = $this->packageAutorunVetoed();

        foreach ($this->packageSeederDefinitions as $definition) {
            if (! $definition->shouldRegister()) {
                continue;
            }

            // Degradable: a malformed seeder file surfacing from
            // resolveSeeders() (discovery) leaves a safe reduced state — the
            // app serves, that bundle just doesn't seed. It reports through
            // the central handler and is recorded degraded (a CI health gate
            // catches it) rather than taking down every request.
            FailurePolicy::run(
                fn () => $this->registerSeederDefinition($manager, $definition, $defaultDiscoveryPath, $autorunVetoed),
                "seeder bundle [{$definition->key()}]",
                BootCriticality::Degradable,
            );
        }
    }

    private function registerSeederDefinition(
        SeederManager $manager,
        AutoSeederDefinition $definition,
        string $defaultDiscoveryPath,
        bool $autorunVetoed,
    ): void {
        $seeders = $definition->resolveSeeders($defaultDiscoveryPath);

        if ($seeders === []) {
            return;
        }

        // Options first; an explicit fluent priority() overrides an
        // options(['priority' => …]) value, but a never-set fluent
        // priority no longer clobbers it with the default 0.
        $options = $definition->optionsValue();
        if ($definition->hasExplicitPriority() || ! array_key_exists('priority', $options)) {
            $options['priority'] = $definition->priorityValue();
        }

        $options['autorun'] = ! $autorunVetoed
            && ($definition->isAutorun() || $this->autorunAllSeeders);
        $options['stop_on_failure'] = $definition->shouldStopOnFailure()
            || (bool) ($options['stop_on_failure'] ?? false);
        $options['autorun_environments'] = $definition->autorunEnvironmentsValue();
        $options['background'] = $definition->isBackground() || (bool) ($options['background'] ?? false);
        $options['queue'] = $definition->queueValue() ?? $options['queue'] ?? null;
        $options['connection'] = $definition->queueConnectionValue() ?? $options['connection'] ?? null;
        $options['notify'] = $definition->shouldNotify() && (bool) ($options['notify'] ?? true);
        $options['without_overlapping'] = $definition->overlapExpiresAtValue() ?? $options['without_overlapping'] ?? null;

        $manager->autoSeed(
            $definition->key(),
            $seeders,
            $definition->namespace(),
            $options,
        );
    }

    private function packageAutorunVetoed(): bool
    {
        // A name-less/vendor-less Package (partial trait usage in tests)
        // has no per-package config namespace to consult. isset() is false
        // for both an uninitialized and a null vendor.
        if (! isset($this->configVendor)) {
            return false;
        }

        $key = $this->getDottedNamespace() . '.seeders.autorun';

        return config($key, true) === false;
    }

    /**
     * Resolve a relative factory path to an absolute one.
     *
     * @param string $path Relative path
     * @return string Absolute path
     */
    protected function resolveFactoryPath(string $path): string
    {
        return $this->getPath($path);
    }

    /**
     * Guess the factory namespace from the package name.
     */
    protected function guessFactoryNamespace(): string
    {
        $parts = explode('/', $this->name);
        if (count($parts) === 2) {
            $vendor = ucfirst($parts[0]);
            $package = str_replace(['-', '_'], '', ucwords($parts[1], '-_'));

            return "{$vendor}\\{$package}\\Database\\Factories";
        }

        return 'Database\\Factories';
    }

    /**
     * Get all factory paths
     *
     * @return array<string>
     */
    public function getFactoryPaths(): array
    {
        return $this->factoryPaths;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\LoadsHelpers;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessAboutSections;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessAssets;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessBladeComponents;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessBladeDirectives;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessCommands;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessConfigs;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessInertia;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessLivewireComponents;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessLogging;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessMigrations;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessRoutes;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessScheduledCommands;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessScheduledSeeders;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessServiceProviders;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessTranslations;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessValidationRules;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessViewComposers;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessViews;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\ProcessViewSharedData;
use Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider\RegisterChildProviders;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPackage;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;
use Simtabi\Laranail\Package\Tools\Support\Definitions\DoctorCheckDefinition;
use Simtabi\Laranail\Package\Tools\Support\Path\Path;
use Simtabi\Laranail\Package\Tools\Support\Registry\PackageRegistry;

/**
 * Base service provider for Laravel packages. Manages the package
 * lifecycle and exposes hooks for infrastructure setup, service
 * registration, and boot-time operations.
 *
 * Hooks, by phase:
 *   - registeringPackage(): log channels, Horizon supervisors, and other
 *     infrastructure that must exist before any service uses it. Declarative
 *     morph maps (registerMorphMap()) apply at boot via
 *     bootPackageDeferredHooks() — do not rely on them during register.
 *   - configurePackage(): package name, paths, and features.
 *   - packageRegistered(): singletons, bindings, and aliases (package
 *     config is available by now).
 *   - bootingPackage(): middleware and Blade namespaces, before routes and
 *     views register.
 *   - packageBooted(): event listeners, schedulers, and finalization, once
 *     everything else is ready.
 *
 * Laravel runs all providers' register phase before any boot phase, so
 * place work in the hook whose preconditions are met.
 *
 * @see https://laravel.com/docs/providers
 */
abstract class PackageServiceProvider extends ServiceProvider
{
    use LoadsHelpers;
    use ProcessAboutSections;
    use ProcessAssets;
    use ProcessBladeComponents;
    use ProcessBladeDirectives;
    use ProcessCommands;
    use ProcessConfigs;
    use ProcessInertia;
    use ProcessLivewireComponents;
    use ProcessLogging;
    use ProcessMigrations;
    use ProcessRoutes;
    use ProcessScheduledCommands;
    use ProcessScheduledSeeders;
    use ProcessServiceProviders;
    use ProcessTranslations;
    use ProcessValidationRules;
    use ProcessViewComposers;
    use ProcessViews;
    use ProcessViewSharedData;
    use RegisterChildProviders;

    public Package $package;

    abstract public function configurePackage(Package $package): void;

    /**
     * Register application services. Override registerPackage() for custom
     * registration logic.
     *
     * Laravel's base ServiceProvider::register() is empty, so there is no
     * parent::register() to call.
     *
     * @throws InvalidPackage|InvalidPath
     */
    #[Override]
    public function register(): void
    {
        $this->registerPackage();
    }

    /**
     * Orchestrate package registration: fire the registeringPackage() hook,
     * build and configure the Package, validate it, load helpers, register
     * configs, then fire packageRegistered().
     *
     * Override to customize the flow; call parent::registerPackage() to keep
     * the default behavior.
     *
     * @throws InvalidPackage|InvalidPath
     */
    public function registerPackage(): void
    {
        $this->registeringPackage();

        $this->package = $this->newPackage();
        $this->package->setPathFrom($this->getPackageBaseDir());

        // Arm early-logging: lines written inside configurePackage() buffer
        // until the package's config merges (registerPackageLogging()).
        $this->package->bufferEarlyLogs();

        $this->configurePackage($this->package);

        // Record what this package claimed, so the whole set can be listed and checked for two
        // packages claiming one name. Laravel's registries are flat maps that overwrite silently;
        // nothing else in the framework can answer that question afterwards.
        //
        // singletonIf here rather than relying on package-tools' own provider having registered the
        // binding: a consumer's package boots through THIS class, and whether the toolkit's provider
        // has loaded yet is an ordering detail. Without it every make() returns a fresh registry and
        // the report is silently empty -- the exact failure mode this feature exists to end.
        $this->app->singletonIf(PackageRegistry::class);
        $this->app->make(PackageRegistry::class)->register($this->package, static::class);

        if ($this->package->name === '' || $this->package->name === '0') {
            throw InvalidPackage::nameIsRequired();
        }

        if ($this->package->basePath === '' || $this->package->basePath === '0') {
            throw InvalidPath::pathIsRequired();
        }

        $this->loadPackageHelpers();

        $this->registerPackageConfigs();

        $this->registerPackageConfigDecorations();

        $this->registerPackageLogging();

        $this->registerPackageChildProviders();

        $this->packageRegistered();
    }

    /**
     * Hook called before package registration begins. Override for setup
     * that must run first: environment-specific config, early bindings that
     * don't depend on the package, and shared resources.
     */
    public function registeringPackage(): void
    {
        // Override in child class to add custom pre-registration logic.
    }

    /**
     * Create a new Package instance. Override to use a custom Package
     * subclass or inject dependencies.
     */
    public function newPackage(): Package
    {
        return new Package;
    }

    /**
     * Hook called after package registration completes. Override for
     * bindings that depend on the package, event listeners, package-specific
     * services, and post-registration validation.
     */
    public function packageRegistered(): void
    {
        // Override in child class to add custom post-registration logic.
    }

    /**
     * Apply the package's declarative config-default merges
     * (`mergesConfigDefaults()` / `mergesConfigDefaultsFrom()`) in the
     * register phase, after registerPackageConfigs() so the package's own
     * config is already present and host values win.
     */
    protected function registerPackageConfigDecorations(): void
    {
        $this->package->applyPackageConfigDefaults();
    }

    /**
     * Bootstrap application services. Override bootPackage() for custom boot
     * logic. Laravel's base ServiceProvider::boot() is empty, so there is no
     * parent::boot() to call.
     */
    public function boot(): void
    {
        $this->bootPackage();
    }

    /**
     * Orchestrate package booting: fire bootingPackage(), boot all package
     * features (assets, views, routes, and so on), then fire packageBooted().
     *
     * Override to customize the flow; call parent::bootPackage() to keep the
     * default behavior.
     */
    public function bootPackage(): void
    {
        $this->bootingPackage();

        $this
            ->bootPackageLogging()
            ->bootPackageAssets()
            ->bootPackageBladeComponents()
            ->bootPackageBladeDirectives()
            ->bootPackageCommands()
            ->bootPackageConsoleCommands()
            ->bootPackageScheduledCommands()
            ->bootPackageScheduledSeeders()
            ->bootPackageConfigs()
            ->bootPackageInertia()
            ->bootPackageLivewireComponents()
            ->bootPackageMigrations()
            ->bootPackageRoutes()
            ->bootPackageServiceProviders()
            ->bootPackageTranslations()
            ->bootPackageViews()
            ->bootPackageViewComposers()
            ->bootPackageViewSharedData()
            ->bootPackageCustomPublishes()
            ->bootPackageValidationRules()
            ->bootPackageAboutSections()
            ->bootPackageDeferredHooks()
            ->bootPackageDoctorChecks()
            ->packageBooted();
    }

    /**
     * Drive the boot-time hooks that live on the Package object itself
     * (middleware, event listeners, factories, seeders). Their
     * `bootPackage*` methods aren't part of the Process* chain above, so the
     * provider dispatches them explicitly.
     */
    protected function bootPackageDeferredHooks(): static
    {
        // morphs first: models may be touched by any later step
        $this->package->bootPackageMorphMaps();

        $this->package->bootPackageMiddleware(
            $this->app->make(Router::class)
        );

        $this->package->bootPackageEventListeners();
        $this->package->bootPackageEventSubscribers();
        $this->package->bootPackageEventSubscriberCallbacks(
            $this->app->make(Dispatcher::class)
        );
        $this->package->bootPackagePolicies();
        $this->package->bootPackageObservers();
        $this->package->bootPackageRateLimiters();
        $this->package->bootPackageGates();
        $this->package->bootPackageRouteBindings(
            $this->app->make(Router::class)
        );
        $this->package->bootPackageRuntimeTweaks();
        $this->package->bootPackageConfigDecorators();
        $this->package->bootPackageFactories();

        // seeder registration only — execution belongs to db:seed (the
        // boot-time-immediate path was removed in 2.0)
        $this->package->bootPackageAutoSeeders();

        return $this;
    }

    /**
     * Register the package's declared doctor checks (`$package->hasDoctorChecks()`)
     * into the shared DoctorService so they surface in the unified
     * `php artisan laranail::package-tools.doctor` command.
     */
    protected function bootPackageDoctorChecks(): static
    {
        $checks = $this->package->getDoctorChecks();

        if ($checks === [] || ! $this->app->bound(DoctorService::class)) {
            return $this;
        }

        $service = $this->app->make(DoctorService::class);

        foreach ($checks as $check) {
            // gated definitions evaluate their config gate at boot; a
            // failed gate means the check is never registered
            if ($check instanceof DoctorCheckDefinition && ! $check->shouldRegister()) {
                continue;
            }

            $group = $this->package->configVendor !== null
                ? "{$this->package->configVendor}/{$this->package->name}"
                : $this->package->name;

            $service->register($check, $group);
        }

        return $this;
    }

    /**
     * Hook called before package boot begins. Override for setup that must
     * run before boot: early middleware, route bindings, and services that
     * need to be ready beforehand.
     */
    public function bootingPackage(): void
    {
        // Override in child class to add custom pre-boot logic.
    }

    /**
     * Hook called after package boot completes. Override for bindings and
     * event listeners that depend on booted services, and for finalizing
     * package setup.
     */
    public function packageBooted(): void
    {
        // Override in child class to add custom post-boot logic.
    }

    /**
     * A path inside the package, resolved from the provider's own location.
     *
     * Replaces `__DIR__ . '/../../config/db-tools.php'`, whose dot-dot count is correct only for the
     * provider's current depth and is checked by nothing -- moving the provider into `Providers/`
     * left a string that still parsed, still looked plausible, and pointed at a file that was not
     * there. This counts nothing: `getPackageBaseDir()` reflects on the provider class and walks up
     * out of `Providers/` and `src/`, so the answer is the same wherever the provider sits.
     *
     *     $this->mergeConfigFrom($this->packagePath('config/db-tools.php'), 'laranail.db-tools');
     *
     * Available from `register()` onward -- it does not need `$this->package` to have been built.
     * Outside a provider, use `PathResolver::packageRoot()`, which finds the root by marker instead.
     */
    protected function packagePath(string $relative = ''): string
    {
        return $relative === ''
            ? $this->getPackageBaseDir()
            : Path::join($this->getPackageBaseDir(), $relative);
    }

    protected function getPackageBaseDir(): string
    {
        $reflector = new ReflectionClass(static::class);

        $fileName = $reflector->getFileName();
        if ($fileName === false) {
            throw new RuntimeException('Unable to resolve the service provider file path via reflection.');
        }

        $packageBaseDir = dirname($fileName);

        // Some packages like to keep Laravel's directory structure and place
        // the service providers in a Providers folder. Step up out of it.
        if (Str::endsWith($packageBaseDir, DIRECTORY_SEPARATOR . 'Providers')) {
            $packageBaseDir = dirname($packageBaseDir);
        }

        // When the PHP source lives under a src/ directory, the package root —
        // where resources/, database/, routes/ and config/ live — is the level
        // above it. Step up so resource paths resolve against the package root
        // rather than the source root.
        if (Str::endsWith($packageBaseDir, DIRECTORY_SEPARATOR . 'src')) {
            return dirname($packageBaseDir);
        }

        return $packageBaseDir;
    }

    public function packageView(?string $namespace): ?string
    {
        return is_null($namespace)
            ? $this->package->shortName()
            : $this->package->viewNamespace;
    }

    /**
     * Record every publish tag this package registers, then register it.
     *
     * One seam rather than ten. `publishes()` is the single method every
     * `Process*` trait funnels through — configs, views, migrations,
     * translations, assets, Blade components, Inertia, service providers — so
     * overriding it here captures all of them, and every future call site, for
     * free. Recording at each trait instead would have been ten edits and one
     * forgotten the next time a trait is added.
     *
     * @param array<string, string> $paths
     * @param array<int, string>|string|null $groups
     */
    #[Override]
    protected function publishes(array $paths, $groups = null): void
    {
        parent::publishes($paths, $groups);

        if (! isset($this->package)) {
            return;
        }

        foreach ((array) ($groups ?? []) as $tag) {
            if (is_string($tag) && $tag !== '') {
                $this->recordPublishTag($tag, $paths, $this->tagWantsCleaning($tag));
            }
        }
    }

    /**
     * Whether the package asked for this tag's destination to be cleaned.
     *
     * Two places record that intent — the fluent `publish(..., clean: true)`
     * and an asset-registry entry's `clean` flag — so both are consulted rather
     * than whichever one the caller happened to use.
     */
    private function tagWantsCleaning(string $tag): bool
    {
        if ((bool) ($this->package->getPublishPathsToClean()[$tag] ?? false)) {
            return true;
        }

        return array_any($this->package->getAssetRegistry(), fn (array $entry): bool => ($entry['tag'] ?? null) === $tag && ($entry['clean'] ?? false));
    }

    /**
     * Boot custom publish paths registered via $package->publish().
     *
     * A destination marked `cleanBeforePublish` is recorded, not deleted.
     * Deleting here ran on every console command — an unrelated
     * `php artisan route:list` removed the published assets of any package
     * that had asked for a clean, and they stayed gone until someone
     * re-published. The clean is honoured when publishing actually happens.
     */
    protected function bootPackageCustomPublishes(): static
    {
        if (! $this->app->runningInConsole()) {
            return $this;
        }

        $publishPaths = $this->package->getPublishPaths();

        if ($publishPaths === []) {
            return $this;
        }

        foreach ($publishPaths as $tag => $paths) {
            $this->publishes($paths, $tag);
        }

        return $this;
    }
}

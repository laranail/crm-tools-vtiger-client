<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Illuminate\Console\Command;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\AskToRunMigrations;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\AskToStarRepoOnGitHub;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\PublishesResources;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\SupportsServiceProviderInApp;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\SupportsStartWithEndWith;
use Simtabi\Laranail\Package\Tools\Package;

class InstallCommand extends Command
{
    use AskToRunMigrations;
    use AskToStarRepoOnGitHub;
    use PublishesResources;

    // Required for the default `{vendor}::{package}.install` name: Symfony's
    // validateName() rejects the empty segment in `::`.
    use SupportsNamespacedNames;
    use SupportsServiceProviderInApp;
    use SupportsStartWithEndWith;

    protected Package $package;

    /**
     * @param Package $package The package to install
     * @param string|null $signature Override the default `{vendor}::{package}.install`
     * @param bool|null $hidden Override the hidden-by-default listing
     */
    public function __construct(Package $package, ?string $signature = null, ?bool $hidden = null)
    {
        // Vendor-scoped by default. A bare `{package}:install` is a plausible
        // collision with a sibling package or the consuming app, and Artisan's
        // command registry is a flat map — the loser is replaced silently.
        $this->signature = $signature ?? $package->getDoubleColonNamespace() . '.install';

        $this->description = 'Install ' . $package->name;

        $this->package = $package;

        // Hidden from `php artisan list` by default. Surface the install
        // command through your README instead. Override `$hidden = false` in a
        // subclass (or pass $hidden) to list it.
        $this->hidden = $hidden ?? true;

        parent::__construct();
    }

    public function getPackage(): Package
    {
        return $this->package;
    }

    /**
     * Run the star-repo prompt immediately (the definition-based install
     * runs steps in declaration order instead of a fixed pipeline).
     */
    public function starRepoNow(): self
    {
        return $this->processStarRepo();
    }

    /**
     * Copy and register the publishable service provider immediately.
     */
    public function copyProviderNow(): self
    {
        return $this->copyServiceProviderInApp();
    }

    public function handle(): int
    {
        $this
            ->processStartWith()
            ->processPublishes()
            ->processAskToRunMigrations()
            ->processCopyServiceProviderInApp()
            ->processStarRepo()
            ->processEndWith();

        $this->info("{$this->package->shortName()} has been installed!");

        return self::SUCCESS;
    }
}

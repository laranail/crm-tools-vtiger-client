<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Providers;

use Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * Every name here was the bare `vtiger-rest-api-client`, and the publish tags spelled themselves
 * `vtiger-rest-api-client:config` -- a form nothing else in the family uses. Config keys, translation
 * namespaces and publish tags are flat global registries, so a sibling package or the consuming
 * application claiming one silently replaces this.
 *
 * The base class derives all of them from the composer name.
 *
 * Three registrations were also dropped rather than ported: loadViewsFrom, loadMigrationsFrom and a
 * publishes() of `public/`. This package ships no `resources/views`, no `database/migrations` and no
 * `public` directory -- all three pointed at paths that do not exist and had never published
 * anything.
 */
class VtWsClientServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name(name: 'laranail/crm-tools-vtiger-client')
            ->hasConfigFile(configFileName: 'crm-tools-vtiger-client')
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(VtWsClient::class, fn (): VtWsClient => new VtWsClient);
    }
}

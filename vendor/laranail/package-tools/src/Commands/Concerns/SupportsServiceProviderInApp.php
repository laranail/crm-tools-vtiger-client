<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait SupportsServiceProviderInApp
{
    protected bool $copyServiceProviderInApp = false;

    public function copyAndRegisterServiceProviderInApp(): self
    {
        $this->copyServiceProviderInApp = true;

        return $this;
    }

    protected function processCopyServiceProviderInApp(): self
    {
        if ($this->copyServiceProviderInApp) {
            $this->comment('Publishing service provider...');

            $this->copyServiceProviderInApp();
        }

        return $this;
    }

    protected function copyServiceProviderInApp(): self
    {
        $providerName = $this->package->publishableProviderName;

        if (! $providerName) {
            return $this;
        }

        $this->callSilent('vendor:publish', ['--tag' => $this->package->shortName() . '-provider']);

        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        if (intval(app()->version()) < 11 || ! File::exists(base_path('bootstrap/providers.php'))) {
            $appConfig = File::get(config_path('app.php'));
        } else {
            $appConfig = File::get(base_path('bootstrap/providers.php'));
        }

        $class = '\\Providers\\' . Str::replace('/', '\\', $providerName) . '::class';

        if (Str::contains($appConfig, $namespace . $class)) {
            return $this;
        }

        if (intval(app()->version()) < 11 || ! File::exists(base_path('bootstrap/providers.php'))) {
            File::put(config_path('app.php'), str_replace(
                "{$namespace}\\Providers\\BroadcastServiceProvider::class,",
                "{$namespace}\\Providers\\BroadcastServiceProvider::class," . PHP_EOL . "        {$namespace}{$class},",
                $appConfig
            ));
        } else {
            File::put(base_path('bootstrap/providers.php'), str_replace(
                "{$namespace}\\Providers\\AppServiceProvider::class,",
                "{$namespace}\\Providers\\AppServiceProvider::class," . PHP_EOL . "        {$namespace}{$class},",
                $appConfig
            ));
        }

        File::put(app_path('Providers/' . $providerName . '.php'), str_replace(
            "namespace App\Providers;",
            "namespace {$namespace}\Providers;",
            File::get(app_path('Providers/' . $providerName . '.php'))
        ));

        return $this;
    }
}

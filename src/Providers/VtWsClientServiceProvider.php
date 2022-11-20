<?php declare(strict_types=1);

namespace USIPCOM\VtWsClient\Providers;

use Illuminate\Support\ServiceProvider;
use USIPCOM\VtWsClient\Helpers\Helpers;
use USIPCOM\VtWsClient\VtWsClient;

class VtWsClientServiceProvider extends ServiceProvider
{
    private const  PACKAGE_PATH = __DIR__ . '/../../';

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->loadTranslationsFrom(self::PACKAGE_PATH . "resources/lang/", Helpers::PACKAGE_NAME);
        $this->loadMigrationsFrom(self::PACKAGE_PATH  . "database/migrations");
        $this->mergeConfigFrom(self::PACKAGE_PATH      . "config/config.php", Helpers::PACKAGE_NAME);
        $this->loadViewsFrom(self::PACKAGE_PATH        . "resources/views", Helpers::PACKAGE_NAME);

        // Register the main class to use with the facade
        $this->app->singleton(VtWsClient::class, function () {
            return new VtWsClient();
        });

        $this->app->bind(VtWsClient::class, function($app) {
            return new VtWsClient();
        });
    }

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->registerConsoles();
    }

    private function registerConsoles(): static
    {
        $packageName = Helpers::PACKAGE_NAME;

        if ($this->app->runningInConsole())
        {
            $this->publishes([
                self::PACKAGE_PATH . "config/config.php"               => config_path("{$packageName}.php"),
            ], "{$packageName}:config");

            $this->publishes([
                self::PACKAGE_PATH . "public"                          => public_path("vendor/{$packageName}"),
            ], "{$packageName}:assets");

            $this->publishes([
                self::PACKAGE_PATH . "resources/views"                 => resource_path("views/vendor/{$packageName}"),
            ], "{$packageName}:views");

            $this->publishes([
                self::PACKAGE_PATH . "resources/lang"                  => $this->app->langPath("vendor/{$packageName}"),
            ], "{$packageName}:translations");
        }

        return $this;
    }

}

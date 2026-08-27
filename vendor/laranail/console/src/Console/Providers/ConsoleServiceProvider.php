<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Providers;

use Illuminate\Support\ServiceProvider;
use Override;
use Simtabi\Laranail\Console\ConsoleManager;
use Simtabi\Laranail\Console\Exceptions\ConsoleException;
use Simtabi\Laranail\Console\Prompter\Providers\PrompterServiceProvider;
use Simtabi\Laranail\Console\Tools\Providers\ToolsServiceProvider;
use Simtabi\Laranail\Console\Tools\Support\ConfigValidator;

/**
 * Root service provider for laranail/console.
 *
 * Owns package-wide wiring — configuration, translations, the ConsoleManager
 * binding — and registers the per-sub-domain child providers. New sub-domains
 * are added by registering their child provider here.
 *
 * @internal Auto-discovered framework wiring; not part of the public API.
 */
final class ConsoleServiceProvider extends ServiceProvider
{
    private const string CONFIG_PATH = __DIR__ . '/../../../config/console.php';

    private const string LANG_PATH = __DIR__ . '/../../../resources/lang';

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'laranail.console');

        $this->app->singleton(ConsoleManager::class, static fn (): ConsoleManager => new ConsoleManager);

        $this->app->register(ToolsServiceProvider::class);
        $this->app->register(PrompterServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(self::LANG_PATH, 'laranail-console');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => $this->app->configPath('laranail/console.php'),
            ], 'laranail::console-config');

            // vendor/laranail-console, matching the namespace registered above.
            // Publishing to the lang root put the files where the namespaced
            // loader never looks, so every published override was ignored.
            $this->publishes([
                self::LANG_PATH => $this->app->langPath('vendor/laranail-console'),
            ], 'laranail::console-lang');

            // Opt-in fail-fast: validate console.* config at boot (console only, so
            // web requests are never affected). Off by default.
            if ((bool) config('laranail.console.validate_config', false)) {
                $errors = ConfigValidator::validate();

                if ($errors !== []) {
                    throw new ConsoleException(
                        "Invalid laranail/console configuration:\n  - " . implode("\n  - ", $errors),
                    );
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Providers;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Override;
use Simtabi\Laranail\Console\Tools\Commands\CheckConfigCommand;
use Simtabi\Laranail\Console\Tools\Support\ConsoleWriterFactory;
use Simtabi\Laranail\Console\Tools\Widgets\Menu\Menu;

/**
 * Child provider for the Tools sub-domain.
 *
 * Tools classes are either static (ConsoleUIFormatter), instantiated directly
 * (widgets, the command base + services), or extended (Command) — none require
 * container bindings today. It also registers the `menu()` command macro.
 *
 * @internal Auto-discovered framework wiring; not part of the public API.
 */
final class ToolsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(ConsoleWriterFactory::class, fn (): ConsoleWriterFactory => new ConsoleWriterFactory);
    }

    public function boot(): void
    {
        if (! Command::hasMacro('menu')) {
            Command::macro('menu', fn (string $title = '', array $options = []): Menu => Menu::make($title, $options));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CheckConfigCommand::class]);
        }
    }
}

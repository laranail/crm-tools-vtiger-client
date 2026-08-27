<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Simtabi\Laranail\Package\Tools\Commands\DefinedInstallCommand;

trait ProcessCommands
{
    protected function bootPackageCommands(): self
    {
        if (empty($this->package->commands)) {
            return $this;
        }

        $this->commands($this->package->commands);

        return $this;
    }

    protected function bootPackageConsoleCommands(): self
    {
        if (! $this->app->runningInConsole()) {
            return $this;
        }

        // definition-based install commands are constructed here — lazily,
        // console only — instead of eagerly at configure time
        foreach ($this->package->getInstallCommandDefinitions() as $definition) {
            $this->commands([new DefinedInstallCommand($this->package, $definition)]);
        }

        if (empty($this->package->consoleCommands)) {
            return $this;
        }

        $this->commands($this->package->consoleCommands);

        return $this;
    }
}

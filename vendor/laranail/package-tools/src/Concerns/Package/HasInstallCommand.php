<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Commands\InstallCommand;
use Simtabi\Laranail\Package\Tools\Support\Definitions\InstallCommandDefinition;

trait HasInstallCommand
{
    /** @var list<InstallCommandDefinition> */
    protected array $installCommandDefinitions = [];

    /**
     * Register the package install command: a fluent
     * InstallCommandDefinition (steps run in declaration order, built
     * lazily — nothing is constructed on web requests), or the legacy
     * callable receiving a live InstallCommand.
     *
     * @param InstallCommandDefinition|callable(InstallCommand): void $definition
     */
    public function hasInstallCommand(InstallCommandDefinition|callable $definition): static
    {
        if ($definition instanceof InstallCommandDefinition) {
            $this->installCommandDefinitions[] = $definition;

            return $this;
        }

        $installCommand = new InstallCommand($this);

        $definition($installCommand);

        $this->consoleCommands[] = $installCommand;

        return $this;
    }

    /**
     * @return list<InstallCommandDefinition>
     */
    public function getInstallCommandDefinitions(): array
    {
        return $this->installCommandDefinitions;
    }
}

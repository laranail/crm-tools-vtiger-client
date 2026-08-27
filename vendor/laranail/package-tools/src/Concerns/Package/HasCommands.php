<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasCommands
{
    /** @var list<string> */
    public array $commands = [];

    /** @var list<string|object> */
    public array $consoleCommands = [];

    public function hasCommand(string $commandClassName): static
    {
        $this->commands[] = $commandClassName;

        return $this;
    }

    /**
     * @param string|array<int, string> ...$commandClassNames
     */
    public function hasCommands(...$commandClassNames): static
    {
        /** @var list<string> $flattened */
        $flattened = collect($commandClassNames)->flatten()->toArray();
        $this->commands = array_merge(
            $this->commands,
            $flattened
        );

        return $this;
    }

    public function hasConsoleCommand(string $commandClassName): static
    {
        $this->consoleCommands[] = $commandClassName;

        return $this;
    }

    /**
     * @param string|array<int, string> ...$commandClassNames
     */
    public function hasConsoleCommands(...$commandClassNames): static
    {
        /** @var list<string> $flattened */
        $flattened = collect($commandClassNames)->flatten()->toArray();
        $this->consoleCommands = array_merge(
            $this->consoleCommands,
            $flattened
        );

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Simtabi\Laranail\Package\Tools\Contracts\CronExpressible;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;
use Simtabi\Laranail\Package\Tools\Support\Definitions\ScheduledCommandDefinition;

/**
 * declarative scheduler registration. definitions are applied by the
 * provider once the Schedule resolves (console only, after every provider
 * has booted) — config gates and cadences evaluate then, never earlier.
 */
trait HasScheduledCommands
{
    /** @var list<ScheduledCommandDefinition> */
    protected array $scheduledCommands = [];

    /** @var list<Closure> fn (Schedule): void */
    protected array $scheduleCallbacks = [];

    public function registerScheduledCommand(
        ScheduledCommandDefinition|string $command,
        Cadence|CronExpressible|string|Closure $cadence = Cadence::Daily,
    ): static {
        $this->scheduledCommands[] = $command instanceof ScheduledCommandDefinition
            ? $command
            : ScheduledCommandDefinition::make($command)->cadence($cadence);

        return $this;
    }

    /**
     * definitions, plain command strings, or [$command => $cadence] pairs.
     *
     * @param array<int|string, ScheduledCommandDefinition|Cadence|CronExpressible|string|Closure> $commands
     */
    public function registerScheduledCommands(array $commands): static
    {
        foreach ($commands as $key => $value) {
            if (is_string($key)) {
                /** @var Cadence|CronExpressible|string|Closure $value */
                $this->registerScheduledCommand($key, $value);

                continue;
            }

            /** @var ScheduledCommandDefinition|string $value */
            $this->registerScheduledCommand($value);
        }

        return $this;
    }

    /**
     * raw escape hatch: full access to the Schedule.
     */
    public function schedulesUsing(Closure $callback): static
    {
        $this->scheduleCallbacks[] = $callback;

        return $this;
    }

    /**
     * @return list<ScheduledCommandDefinition>
     */
    public function getScheduledCommands(): array
    {
        return $this->scheduledCommands;
    }

    /**
     * @return list<Closure>
     */
    public function getScheduleCallbacks(): array
    {
        return $this->scheduleCallbacks;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Runners;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Override;

/**
 * Conditional runner specialised for console-only execution: it skips
 * unless the app is running in the console, plus command/maintenance/
 * scheduled/verbosity guards.
 */
class ConsoleRunner extends BaseRunner
{
    /**
     * Static factory with immediate console check
     */
    #[Override]
    public static function make(?Application $app = null): static
    {
        $instance = new static($app);
        $instance->reset();

        return $instance;
    }

    #[Override]
    protected function initialize(): void
    {
        // Console is required by default
        if (! $this->app->runningInConsole()) {
            $this->shouldRun = false;
            $this->conditions[] = [
                'type' => 'console_check',
                'label' => 'running_in_console',
                'passed' => false,
                'timestamp' => now()->toIso8601String(),
            ];
        } else {
            $this->conditions[] = [
                'type' => 'console_check',
                'label' => 'running_in_console',
                'passed' => true,
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Only run when not in maintenance
     */
    public function whenNotInMaintenance(): static
    {
        return $this->unless(
            fn () => $this->app->isDownForMaintenance(),
            'maintenance_mode'
        );
    }

    /**
     * Only run for specific command
     */
    public function whenCommand(string|array $commands): static
    {
        $commands = Arr::wrap($commands);

        return $this->when(
            function () use ($commands): bool {
                // The command is the first non-option argument after the script
                // name; skip leading global options like `-v`.
                $args = array_slice($_SERVER['argv'] ?? [], 1);
                $command = Arr::first($args, static fn (string $arg): bool => ! str_starts_with($arg, '-'));

                return $command !== null && in_array($command, $commands, true);
            },
            'command_' . implode('_or_', $commands)
        );
    }

    /**
     * Only run during scheduled task
     */
    public function whenScheduled(): static
    {
        return $this->when(
            fn (): bool => defined('LARAVEL_START_FROM_SCHEDULE'),
            'scheduled_task'
        );
    }

    /**
     * Only run at or above a given output verbosity (1 = -v, 2 = -vv, 3 = -vvv).
     */
    public function whenVerbose(int $level = 1): static
    {
        return $this->when(
            fn (): bool => $this->detectVerbosity() >= $level,
            "verbose_level_{$level}"
        );
    }

    /**
     * Derive the requested verbosity from the CLI flags, mirroring how Symfony
     * Console interprets `-v`/`-vv`/`-vvv`/`--verbose` and `-q`/`--quiet`.
     */
    protected function detectVerbosity(): int
    {
        $verbosity = 0;

        foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
            if ($arg === '--quiet' || $arg === '-q') {
                return 0;
            }

            if ($arg === '--verbose') {
                $verbosity = max($verbosity, 1);
            } elseif (preg_match('/^-v+$/', (string) $arg) === 1) {
                $verbosity = max($verbosity, strlen((string) $arg) - 1);
            }
        }

        return $verbosity;
    }
}

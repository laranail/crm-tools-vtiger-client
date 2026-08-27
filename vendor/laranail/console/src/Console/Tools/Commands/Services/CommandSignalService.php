<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

use Illuminate\Support\Facades\Log;

/**
 * Handles operating-system signals for graceful command shutdown.
 *
 * Real handlers are installed via ext-pcntl when it is available; on platforms
 * without it (e.g. Windows) the service degrades to a no-op so commands still
 * run. {@see simulateSignal()} drives the same handlers in tests.
 */
class CommandSignalService
{
    protected bool $shouldKeepRunning = true;

    /** @var array<int, callable> */
    protected array $signalHandlers = [];

    public function __construct(protected string $commandName = '') {}

    /**
     * Install handlers for the given signals (defaults to SIGTERM + SIGINT).
     *
     * Signal constants are only referenced when ext-pcntl is loaded, so calling
     * this on a platform without pcntl is safe.
     *
     * @param array<int, int> $signals
     */
    public function setupSignalHandling(array $signals = []): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        $signals = $signals !== [] ? $signals : [SIGTERM, SIGINT];

        foreach ($signals as $signal) {
            $this->registerSignalHandler($signal);
        }
    }

    /**
     * Register a single signal handler with the OS.
     */
    protected function registerSignalHandler(int $signal): void
    {
        $handler = function (int $receivedSignal): void {
            $this->handleSignal($receivedSignal);
        };

        $this->signalHandlers[$signal] = $handler;

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal($signal, $handler);
        }
    }

    /**
     * Flip the running flag and record the signal.
     */
    protected function handleSignal(int $signal): void
    {
        $this->shouldKeepRunning = false;

        Log::info('Command received termination signal', [
            'command' => $this->commandName,
            'signal' => $signal,
        ]);
    }

    public function shouldKeepRunning(): bool
    {
        return $this->shouldKeepRunning;
    }

    public function stop(): void
    {
        $this->shouldKeepRunning = false;
    }

    public function resume(): void
    {
        $this->shouldKeepRunning = true;
    }

    /**
     * @return array<int, int>
     */
    public function getSignalHandlers(): array
    {
        return array_keys($this->signalHandlers);
    }

    public function isHandlingSignal(int $signal): bool
    {
        return isset($this->signalHandlers[$signal]);
    }

    public function setCommandName(string $commandName): self
    {
        $this->commandName = $commandName;

        return $this;
    }

    public function getCommandName(): string
    {
        return $this->commandName;
    }

    /**
     * Invoke a registered handler as if the signal had been received (tests).
     */
    public function simulateSignal(int $signal): void
    {
        if (isset($this->signalHandlers[$signal])) {
            ($this->signalHandlers[$signal])($signal);
        }
    }
}

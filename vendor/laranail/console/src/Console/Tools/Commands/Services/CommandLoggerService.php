<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\ManagesCommandContext;

/**
 * Command Logger Service
 *
 * Handles structured logging for console commands.
 * Provides consistent logging patterns and context management.
 */
class CommandLoggerService
{
    use ManagesCommandContext;

    public function __construct(
        /**
         * Command name for context
         */
        protected string $commandName = ''
    ) {}

    /**
     * Set command name for logging context
     */
    public function setCommandName(string $commandName): self
    {
        $this->commandName = $commandName;

        return $this;
    }

    /**
     * Get current context
     */
    public function getContext(): array
    {
        return array_merge([
            'command' => $this->commandName,
            'timestamp' => Carbon::now()->toISOString(),
        ], $this->context);
    }

    /**
     * Log command start
     */
    public function logStart(array $additionalContext = []): void
    {
        Log::info('Command started', array_merge($this->getContext(), $additionalContext));
    }

    /**
     * Log command completion
     */
    public function logCompletion(int $exitCode, array $performanceData = [], array $additionalContext = []): void
    {
        $logData = array_merge($this->getContext(), [
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
        ], $performanceData, $additionalContext);

        if ($exitCode === 0) {
            Log::info('Command completed successfully', $logData);
        } else {
            Log::warning('Command completed with errors', $logData);
        }
    }

    /**
     * Log signal received
     */
    public function logSignal(int $signal, array $additionalContext = []): void
    {
        Log::info('Command received termination signal', array_merge($this->getContext(), [
            'signal' => $signal,
        ], $additionalContext));
    }

    /**
     * Get command name
     */
    public function getCommandName(): string
    {
        return $this->commandName;
    }
}

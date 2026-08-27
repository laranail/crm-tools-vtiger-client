<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\App;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Command Service Manager
 *
 * Centralized service manager that coordinates all command-related services.
 * Provides a single entry point for command functionality management.
 */
class CommandServiceManager
{
    /**
     * Recognised keys for {@see configure()} — the single source of truth for the
     * public `InteractsWithConsoleServices::configureServices()` contract.
     */
    public const string NATIVE_EVENTS = 'native_events';

    public const string CUSTOM_EVENTS = 'custom_events';

    public const string SIGNALS = 'signals';

    public const string NON_INTERACTIVE = 'non_interactive';

    protected CommandPerformanceService $performance;

    protected CommandEventService $events;

    protected CommandSignalService $signals;

    protected CommandMetadataService $metadata;

    protected CommandLoggerService $logger;

    protected CommandErrorService $error;

    protected CommandConfigurationService $config;

    protected CommandInteractionService $interaction;

    protected ?CommandDisplayService $display = null;

    public function __construct(protected string $commandName = '', ?OutputInterface $output = null)
    {
        $this->initializeServices($output);
    }

    /**
     * Initialize all services
     */
    protected function initializeServices(?OutputInterface $output = null): void
    {
        $this->performance = new CommandPerformanceService;
        $this->events = new CommandEventService(App::make(Dispatcher::class));
        $this->signals = new CommandSignalService($this->commandName);
        $this->metadata = new CommandMetadataService;
        $this->logger = new CommandLoggerService($this->commandName);
        $this->error = new CommandErrorService($this->commandName);
        $this->config = new CommandConfigurationService;
        $this->interaction = new CommandInteractionService;

        if ($output instanceof OutputInterface) {
            $this->display = new CommandDisplayService($output);
        }
    }

    /**
     * Set command name for all services
     */
    public function setCommandName(string $commandName): self
    {
        $this->commandName = $commandName;
        $this->signals->setCommandName($commandName);
        $this->logger->setCommandName($commandName);
        $this->error->setCommandName($commandName);

        return $this;
    }

    /**
     * Set output for display service
     */
    public function setOutput(OutputInterface $output): self
    {
        $this->display = new CommandDisplayService($output);

        return $this;
    }

    /**
     * Get performance service
     */
    public function performance(): CommandPerformanceService
    {
        return $this->performance;
    }

    /**
     * Get event service
     */
    public function events(): CommandEventService
    {
        return $this->events;
    }

    /**
     * Get signal service
     */
    public function signals(): CommandSignalService
    {
        return $this->signals;
    }

    /**
     * Get metadata service
     */
    public function metadata(): CommandMetadataService
    {
        return $this->metadata;
    }

    /**
     * Get logger service
     */
    public function logger(): CommandLoggerService
    {
        return $this->logger;
    }

    /**
     * Get error service
     */
    public function error(): CommandErrorService
    {
        return $this->error;
    }

    /**
     * Get configuration service
     */
    public function config(): CommandConfigurationService
    {
        return $this->config;
    }

    /**
     * Get interaction service
     */
    public function interaction(): CommandInteractionService
    {
        return $this->interaction;
    }

    /**
     * Get display service
     */
    public function display(): CommandDisplayService
    {
        // Lazy default so callers that never set an output don't hit an
        // uninitialised typed property.
        return $this->display ??= new CommandDisplayService(new ConsoleOutput);
    }

    /**
     * Start command execution
     */
    public function startCommand(): void
    {
        $this->performance->startTimer();
        $this->logger->logStart();
    }

    /**
     * End command execution
     */
    public function endCommand(int $exitCode): void
    {
        $this->performance->endTimer();

        $performanceData = [
            'execution_time' => $this->performance->getFormattedExecutionTime(),
            'memory_usage' => $this->performance->getMemoryUsage(),
        ];

        $this->logger->logCompletion($exitCode, $performanceData, $this->metadata->all());
    }

    /**
     * Handle command exception
     */
    public function handleException(Throwable $exception): void
    {
        $this->error->logError($exception, [
            'execution_time' => $this->performance->getFormattedExecutionTime(),
            'memory_usage' => $this->performance->getMemoryUsage(),
            'metadata' => $this->metadata->all(),
        ]);
    }

    /**
     * Return the command summary
     */
    public function getCommandSummary(): array
    {
        return [
            'command' => $this->commandName,
            'performance' => $this->performance->getPerformanceSummary($this->commandName, $this->metadata->all()),
            'metadata' => $this->metadata->all(),
            'signals' => $this->signals->getSignalHandlers(),
            'events' => [
                'native_enabled' => $this->events->isNativeEventsEnabled(),
                'custom_enabled' => $this->events->isCustomEventsEnabled(),
            ],
            'config_cache' => $this->config->getAllCached(),
        ];
    }

    /**
     * Reset all services
     */
    public function reset(): void
    {
        $this->performance->reset();
        $this->metadata->clear();
        $this->logger->clearContext();
        $this->error->clearContext();
        $this->signals->resume();
        $this->config->clearCache();
    }

    /**
     * Configure service settings
     */
    public function configure(array $config): self
    {
        if (isset($config[self::NATIVE_EVENTS])) {
            $this->events->useNativeEvents($config[self::NATIVE_EVENTS]);
        }

        if (isset($config[self::CUSTOM_EVENTS])) {
            $this->events->useCustomEvents($config[self::CUSTOM_EVENTS]);
        }

        if (isset($config[self::SIGNALS])) {
            $this->signals->setupSignalHandling($config[self::SIGNALS]);
        }

        if (isset($config[self::NON_INTERACTIVE])) {
            $this->interaction->setNonInteractive($config[self::NON_INTERACTIVE]);
        }

        return $this;
    }

    /**
     * Get command name
     */
    public function getCommandName(): string
    {
        return $this->commandName;
    }
}

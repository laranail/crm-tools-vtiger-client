<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\Console\Tools\Events\CommandEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command Event Service
 *
 * Handles dispatching of both Laravel native console events and custom events.
 * Provides a unified interface for event management across console commands.
 */
class CommandEventService
{
    protected CommandEvents $commandEvent;

    /**
     * Whether to use Laravel's native console events
     */
    protected bool $useNativeEvents = true;

    /**
     * Whether to use custom event system
     */
    protected bool $useCustomEvents = true;

    public function __construct(protected Dispatcher $events)
    {
        $this->commandEvent = new CommandEvents;
    }

    /**
     * Dispatch command starting events
     */
    public function dispatchStarting(
        string $commandName,
        InputInterface $input,
        OutputInterface $output,
        array $metadata = []
    ): void {
        // Dispatch custom starting event
        if ($this->useCustomEvents) {
            $this->events->dispatch(
                $this->commandEvent::starting(
                    (object) ['name' => $commandName],
                    $input,
                    null,
                    $metadata
                )
            );
        }

        // Dispatch Laravel native starting event
        if ($this->useNativeEvents) {
            $this->events->dispatch(new CommandStarting($commandName, $input, $output));
        }
    }

    /**
     * Dispatch command finished events
     */
    public function dispatchFinished(
        string $commandName,
        InputInterface $input,
        OutputInterface $output,
        int $exitCode,
        array $metadata = []
    ): void {
        // Dispatch custom terminating event
        if ($this->useCustomEvents) {
            $this->events->dispatch(
                $this->commandEvent::terminating(
                    (object) ['name' => $commandName],
                    $input,
                    $exitCode,
                    null,
                    $metadata
                )
            );
        }

        // Dispatch Laravel native finished event
        if ($this->useNativeEvents) {
            $this->events->dispatch(new CommandFinished($commandName, $input, $output, $exitCode));
        }
    }

    /**
     * Enable or disable Laravel native events
     */
    public function useNativeEvents(bool $enabled = true): self
    {
        $this->useNativeEvents = $enabled;

        return $this;
    }

    /**
     * Enable or disable custom events
     */
    public function useCustomEvents(bool $enabled = true): self
    {
        $this->useCustomEvents = $enabled;

        return $this;
    }

    /**
     * Check if native events are enabled
     */
    public function isNativeEventsEnabled(): bool
    {
        return $this->useNativeEvents;
    }

    /**
     * Check if custom events are enabled
     */
    public function isCustomEventsEnabled(): bool
    {
        return $this->useCustomEvents;
    }

    /**
     * Dispatch a custom event
     */
    public function dispatchCustomEvent(object $event): void
    {
        $this->events->dispatch($event);
    }

    /**
     * Get the event dispatcher instance
     */
    public function getDispatcher(): Dispatcher
    {
        return $this->events;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Observers;

use Closure;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use ReflectionFunction;
use Throwable;

final class ConsoleCommandObserver
{
    /** @var list<Closure(CommandStarting):mixed> */
    protected array $startCallbacks = [];

    /** @var list<Closure(CommandFinished):mixed> */
    protected array $finishCallbacks = [];

    /** Optional extra predicate to inspect $event (args/options/etc.). */
    protected ?Closure $predicate = null;

    /**
     * @var null|string|array<int,string>|callable(string):bool
     *                                                          - null or '*'  : match all commands
     *                                                          - string       : command name, wildcard ('cache:*'), or '/regex/'
     *                                                          - array        : any of the above
     *                                                          - callable     : receives command name, must return bool
     */
    protected $filter;

    protected Dispatcher $events;

    public function __construct(
        string|array|callable|null $commandFilter = null,
        ?Dispatcher $events = null
    ) {
        $this->filter = $commandFilter ?? '*';
        // Prefer DI, but fall back to container for convenience
        $this->events = $events ?? app(Dispatcher::class);

        // Register listeners once this instance is constructed
        $this->events->listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($this->matches($event->command) && $this->passesPredicate($event)) {
                $this->callStartCallbacks($event);
            }
        });

        $this->events->listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($this->matches($event->command) && $this->passesPredicate($event)) {
                $this->callFinishCallbacks($event);
            }
        });
    }

    /** Fluent static constructor. */
    public static function for(string|array|callable|null $commandFilter = null, ?Dispatcher $events = null): self
    {
        return new self($commandFilter, $events);
    }

    /** Add an additional event-level predicate (inspect args/options, etc.). */
    public function when(Closure $predicate): static
    {
        $this->predicate = $predicate;

        return $this;
    }

    /** Register a callback for CommandStarting. */
    public function onStart(Closure $callback): static
    {
        $this->startCallbacks[] = $callback;

        return $this;
    }

    /** Register a callback for CommandFinished. */
    public function onFinish(Closure $callback): static
    {
        $this->finishCallbacks[] = $callback;

        return $this;
    }

    /** Execute all start callbacks with the event. */
    protected function callStartCallbacks(CommandStarting $event): void
    {
        foreach ($this->startCallbacks as $callback) {
            $this->invoke($callback, $event);
        }
    }

    /** Execute all finish callbacks with the event. */
    protected function callFinishCallbacks(CommandFinished $event): void
    {
        foreach ($this->finishCallbacks as $callback) {
            $this->invoke($callback, $event);
        }
    }

    /** Invoke a closure safely: supports 0, 1, or 2 params (event, listener). */
    protected function invoke(Closure $callback, object $event): mixed
    {
        $ref = new ReflectionFunction($callback);
        $argc = $ref->getNumberOfParameters();

        return match (true) {
            $argc >= 2 => $callback($event, $this),
            $argc === 1 => $callback($event),
            default => $callback(),
        };
    }

    protected function passesPredicate(object $event): bool
    {
        return ! $this->predicate || (bool) ($this->predicate)($event);
    }

    /** Determine if a command name matches the filter. */
    protected function matches(string $command): bool
    {
        $f = $this->filter;

        if ($f === null || $f === '*') {
            return true;
        }

        if (is_string($f)) {
            return $this->matchPattern($f, $command);
        }

        if (is_array($f)) {
            return array_any($f, fn (string $pattern): bool => $this->matchPattern($pattern, $command));
        }

        if (is_callable($f)) {
            return (bool) $f($command);
        }

        return false;
    }

    /** Support literal, wildcard, and '/regex/' patterns. */
    protected function matchPattern(string $pattern, string $command): bool
    {
        $isRegex = Str::startsWith($pattern, '/') && Str::endsWith($pattern, '/');

        return $isRegex
            ? (bool) @preg_match($pattern, $command)
            : Str::is($pattern, $command); // supports wildcards like 'cache:*'
    }

    /**
     * Convenience: get buffered output from a finished command when available.
     * Some outputs are BufferedOutput and expose fetch().
     */
    public static function fetchOutput(CommandFinished $event): ?string
    {
        try {
            return (isset($event->output) && method_exists($event->output, 'fetch'))
                ? (string) $event->output->fetch()
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}

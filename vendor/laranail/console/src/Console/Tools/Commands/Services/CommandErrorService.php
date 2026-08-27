<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\ManagesCommandContext;
use Throwable;

/**
 * Structured error logging for console commands.
 *
 * Context is scrubbed of sensitive keys before logging, and full stack traces
 * are only included when the application is in debug mode (configurable), so
 * traces carrying credentials never leak into shared log channels.
 */
class CommandErrorService
{
    use ManagesCommandContext;

    public function __construct(protected string $commandName = '') {}

    public function setCommandName(string $commandName): self
    {
        $this->commandName = $commandName;

        return $this;
    }

    /**
     * Log an exception with scrubbed context.
     *
     * @param array<string, mixed> $additionalContext
     */
    public function logError(Throwable $e, array $additionalContext = []): void
    {
        $context = $this->scrub(array_merge($this->context, $additionalContext));

        $logData = array_merge($context, [
            'command' => $this->commandName,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'timestamp' => now()->toISOString(),
        ]);

        if (! $this->traceInDebugOnly() || (bool) config('app.debug')) {
            $logData['trace'] = $e->getTraceAsString();
        }

        $channel = config('laranail.console.logging.channel');

        ($channel ? Log::channel($channel) : Log::getFacadeRoot())
            ->error('Command Error', $logData);
    }

    public function executeWithErrorHandling(callable $callback, string $operation = 'operation'): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $this->logError($e, ['operation' => $operation]);

            throw $e;
        }
    }

    public function executeWithFallback(callable $callback, mixed $fallback = null, string $operation = 'operation'): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $this->logError($e, ['operation' => $operation, 'fallback_used' => true]);

            return $fallback;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Redact values whose key matches a configured sensitive token.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function scrub(array $context): array
    {
        $redactKeys = (array) config('laranail.console.logging.redact_keys', ['password', 'secret', 'token', 'key', 'authorization']);

        foreach ($context as $key => $value) {
            foreach ($redactKeys as $needle) {
                if (is_string($key) && stripos($key, (string) $needle) !== false) {
                    $context[$key] = '[redacted]';

                    continue 2;
                }
            }

            if (is_array($value)) {
                $context[$key] = $this->scrub($value);
            }
        }

        return $context;
    }

    protected function traceInDebugOnly(): bool
    {
        return (bool) config('laranail.console.logging.trace_in_debug_only', true);
    }
}

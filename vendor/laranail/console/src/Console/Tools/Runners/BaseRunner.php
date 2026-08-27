<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Runners;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * @phpstan-consistent-constructor
 */
abstract class BaseRunner
{
    protected bool $shouldRun = true;

    protected mixed $default = null;

    protected bool $expectReturn = false;

    protected array $conditions = [];

    // Lifecycle callbacks
    protected ?Closure $onBefore = null;

    protected ?Closure $onAfter = null;

    protected ?Closure $onFinally = null;

    protected ?Closure $onError = null;

    protected ?Closure $onSuccess = null;

    protected ?Closure $onSkipped = null;

    // Logging configuration
    protected ?Closure $logger = null;

    protected bool $logConditions = false;

    protected bool $logExecution = false;

    protected string $logChannel = 'default';

    protected array $context = [];

    // Application instance
    protected Application $app;

    protected string $name = 'Runner';

    protected function __construct(?Application $app = null, ?string $name = null)
    {
        $this->app = $app ?? app();
        $this->name = $name ?? class_basename(static::class);
        $this->initialize();
    }

    /**
     * Static factory method
     */
    public static function make(?Application $app = null): static
    {
        $instance = new static($app);
        $instance->reset();

        return $instance;
    }

    /**
     * Reset the runner to initial state
     */
    public function reset(): static
    {
        $this->shouldRun = true;
        $this->default = null;
        $this->expectReturn = false;
        $this->conditions = [];

        $this->onBefore = null;
        $this->onAfter = null;
        $this->onFinally = null;
        $this->onError = null;
        $this->onSuccess = null;
        $this->onSkipped = null;

        $this->logger = null;
        $this->logConditions = false;
        $this->logExecution = false;
        $this->logChannel = 'default';
        $this->context = [];

        $this->initialize();

        return $this;
    }

    /**
     * Initialize runner with default conditions
     */
    abstract protected function initialize(): void;

    /**
     * Add a condition that must be true
     */
    public function when(bool|callable $condition, ?string $label = null): static
    {
        $calculateExecutionTimeMs = static function (float $startTime): int {
            $elapsedSeconds = microtime(true) - $startTime;

            return (int) round($elapsedSeconds * 1000);
        };

        if ($this->shouldRun) {
            $startTime = microtime(true);
            $result = is_callable($condition) ? $condition() : $condition;
            $this->shouldRun = (bool) $result;

            $this->conditions[] = [
                'type' => 'when',
                'label' => $label ?? 'condition_' . count($this->conditions),
                'passed' => $result,
                'execution_time_ms' => $calculateExecutionTimeMs($startTime),
                'timestamp' => now()->toIso8601String(),
            ];

            $this->logCondition('when', $label, $result);
        }

        return $this;
    }

    /**
     * Add a condition that must be false
     */
    public function unless(bool|callable $condition, ?string $label = null): static
    {
        if ($this->shouldRun) {
            $startTime = microtime(true);
            $result = is_callable($condition) ? $condition() : $condition;
            $this->shouldRun = ! $result;

            $this->conditions[] = [
                'type' => 'unless',
                'label' => $label ?? 'unless_' . count($this->conditions),
                'passed' => ! $result,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 4),
                'timestamp' => now()->toIso8601String(),
            ];

            $this->logCondition('unless', $label, ! $result);
        }

        return $this;
    }

    /**
     * Only run in specific environment(s)
     */
    public function whenEnvironment(string|array $environments): static
    {
        return $this->when(
            fn () => $this->app->environment($environments),
            'environment_' . (is_array($environments) ? implode('_', $environments) : $environments)
        );
    }

    /**
     * Only run when config matches
     */
    public function whenConfig(string $key, mixed $value = true): static
    {
        return $this->when(
            fn (): bool => config($key) === $value,
            "config_{$key}"
        );
    }

    /**
     * Only run when feature is enabled
     */
    public function whenFeature(string $feature): static
    {
        return $this->when(
            fn () => config("features.{$feature}", false),
            "feature_{$feature}"
        );
    }

    /**
     * Only run when path/file exists
     */
    public function whenExists(string $path): static
    {
        return $this->when(
            fn () => File::exists($path),
            "exists_{$path}"
        );
    }

    /**
     * Only run when array/collection is not empty
     */
    public function whenNotEmpty(mixed $items, ?string $label = null): static
    {
        return $this->when(
            fn (): bool => ! empty($items),
            $label ?? 'not_empty'
        );
    }

    /**
     * Only run when value is truthy
     */
    public function whenTruthy(mixed $value, ?string $label = null): static
    {
        return $this->when(
            (bool) $value,
            $label ?? 'truthy'
        );
    }

    /**
     * Set execution context for logging
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Enable condition logging
     */
    public function logConditions(bool $enable = true, ?string $channel = null): static
    {
        $this->logConditions = $enable;
        if ($channel) {
            $this->logChannel = $channel;
        }

        return $this;
    }

    /**
     * Enable execution logging
     */
    public function logExecution(bool $enable = true, ?string $channel = null): static
    {
        $this->logExecution = $enable;
        if ($channel) {
            $this->logChannel = $channel;
        }

        return $this;
    }

    /**
     * Set custom logger
     */
    public function withLogger(callable $logger): static
    {
        $this->logger = $logger(...);

        return $this;
    }

    /**
     * Set callback to run before execution
     */
    public function before(callable $callback): static
    {
        $this->onBefore = $callback(...);

        return $this;
    }

    /**
     * Set callback to run after successful execution
     */
    public function after(callable $callback): static
    {
        $this->onAfter = $callback(...);

        return $this;
    }

    /**
     * Set callback to run on success
     */
    public function onSuccess(callable $callback): static
    {
        $this->onSuccess = $callback(...);

        return $this;
    }

    /**
     * Set callback to run on error
     */
    public function onError(callable $callback): static
    {
        $this->onError = $callback(...);

        return $this;
    }

    /**
     * Set callback to run always (like finally)
     */
    public function finally(callable $callback): static
    {
        $this->onFinally = $callback(...);

        return $this;
    }

    /**
     * Set callback to run when conditions fail (skipped)
     */
    public function whenSkipped(callable $callback): static
    {
        $this->onSkipped = $callback(...);

        return $this;
    }

    /**
     * Expect a return value
     */
    public function expectReturn(mixed $default = null, bool $expectReturn = true): static
    {
        $this->expectReturn = $expectReturn;
        $this->default = $default;

        return $this;
    }

    /**
     * Execute the callback
     */
    public function run(callable $callback): mixed
    {
        $executionId = Str::uuid()->toString();
        $startTime = microtime(true);

        $this->log('debug', 'Execution starting', [
            'execution_id' => $executionId,
            'runner_type' => static::class,
            'should_run' => $this->shouldRun,
            'conditions_checked' => count($this->conditions),
        ]);

        if (! $this->shouldRun) {
            try {
                if ($this->onSkipped instanceof Closure) {
                    ($this->onSkipped)($this->conditions, $this->context);
                }
            } catch (Throwable $e) {
                $this->log('error', 'Error in onSkipped callback', [
                    'execution_id' => $executionId,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->log('info', 'Execution skipped - conditions not met', [
                'execution_id' => $executionId,
                'failed_conditions' => $this->getFailedConditions(),
            ]);

            return $this->expectReturn ? $this->default : null;
        }

        $result = null;
        $error = null;

        try {
            if ($this->onBefore instanceof Closure) {
                try {
                    ($this->onBefore)($this->context);
                    $this->log('debug', 'Before callback executed', ['execution_id' => $executionId]);
                } catch (Throwable $e) {
                    $this->log('error', 'Error in before callback', [
                        'execution_id' => $executionId,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $result = $callback();

            if ($this->onAfter instanceof Closure) {
                try {
                    $afterResult = ($this->onAfter)($result, $this->context);
                    if ($afterResult !== null && $this->expectReturn) {
                        $result = $afterResult;
                    }
                    $this->log('debug', 'After callback executed', ['execution_id' => $executionId]);
                } catch (Throwable $e) {
                    $this->log('error', 'Error in after callback', [
                        'execution_id' => $executionId,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            if ($this->onSuccess instanceof Closure) {
                try {
                    ($this->onSuccess)($result, $this->context);
                } catch (Throwable $e) {
                    $this->log('error', 'Error in success callback', [
                        'execution_id' => $executionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->log('info', 'Execution completed successfully', [
                'execution_id' => $executionId,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'has_result' => $result !== null,
            ]);

            return $this->expectReturn ? $result : null;
        } catch (Throwable $e) {
            $error = $e;

            if ($this->onError instanceof Closure) {
                try {
                    $handled = ($this->onError)($e, $this->context);

                    if ($handled !== null && $this->expectReturn) {
                        $this->log('warning', 'Error handled with fallback value', [
                            'execution_id' => $executionId,
                            'error' => $e->getMessage(),
                        ]);

                        return $handled;
                    }
                } catch (Throwable $handlerError) {
                    $this->log('critical', 'Error handler itself failed', [
                        'execution_id' => $executionId,
                        'original_error' => $e->getMessage(),
                        'handler_error' => $handlerError->getMessage(),
                    ]);
                }
            }

            $this->log('error', 'Execution failed with error', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            report($e);

            return $this->expectReturn ? $this->default : null;
        } finally {
            if ($this->onFinally instanceof Closure) {
                try {
                    ($this->onFinally)($result, $error, $this->context);
                    $this->log('debug', 'Finally callback executed', [
                        'execution_id' => $executionId,
                        'had_error' => $error instanceof Throwable,
                    ]);
                } catch (Throwable $e) {
                    $this->log('error', 'Error in finally callback', [
                        'execution_id' => $executionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->log('debug', 'Execution completed', [
                'execution_id' => $executionId,
                'total_duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'success' => ! $error instanceof Throwable,
            ]);
        }
    }

    /**
     * Execute with alternative
     */
    public function runOr(callable $callback, callable $otherwise): mixed
    {
        if ($this->shouldRun) {
            return $this->run($callback);
        }

        $this->log('debug', 'Running alternative callback', [
            'failed_conditions' => $this->getFailedConditions(),
        ]);

        return $otherwise();
    }

    /**
     * Get failed conditions
     */
    protected function getFailedConditions(): array
    {
        return array_filter($this->conditions, static fn (array $c): bool => ! $c['passed']);
    }

    /**
     * Get passed conditions
     */
    public function getPassedConditions(): array
    {
        return array_filter($this->conditions, static fn (array $c) => $c['passed']);
    }

    /**
     * Log a message
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (! $this->logExecution && ! in_array($level, ['error', 'critical'], true)) {
            return;
        }

        $context = array_merge($this->context, $context, [
            'runner' => $this->name,
            'runner_class' => static::class,
            'conditions_count' => count($this->conditions),
        ]);

        if ($this->logger instanceof Closure) {
            ($this->logger)($level, "[{$this->name}] {$message}", $context);
        } else {
            Log::channel($this->logChannel)->log($level, "[{$this->name}] {$message}", $context);
        }
    }

    /**
     * Log condition check
     */
    protected function logCondition(string $type, ?string $label, bool $passed): void
    {
        if (! $this->logConditions) {
            return;
        }

        $this->log('debug', "Condition checked: {$type}", [
            'label' => $label ?? 'unnamed',
            'passed' => $passed,
            'should_run' => $this->shouldRun,
        ]);
    }

    /**
     * Check if runner would execute
     */
    public function wouldRun(): bool
    {
        return $this->shouldRun;
    }

    /**
     * Get debug information
     */
    public function debug(): array
    {
        return [
            'name' => $this->name,
            'type' => static::class,
            'should_run' => $this->shouldRun,
            'conditions' => $this->conditions,
            'context' => $this->context,
            'callbacks' => [
                'before' => $this->onBefore instanceof Closure,
                'after' => $this->onAfter instanceof Closure,
                'success' => $this->onSuccess instanceof Closure,
                'error' => $this->onError instanceof Closure,
                'finally' => $this->onFinally instanceof Closure,
                'skipped' => $this->onSkipped instanceof Closure,
            ],
            'logging' => [
                'conditions' => $this->logConditions,
                'execution' => $this->logExecution,
                'channel' => $this->logChannel,
            ],
        ];
    }
}

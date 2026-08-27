<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Throwable;

/**
 * Runs console-only code behind a runningInConsole() guard, with optional
 * extra conditions, return-value handling, and error reporting.
 */
trait HasConsoleWrapper
{
    /**
     * Run a callback only when in console, catching and reporting any error.
     *
     * @param callable $callback The callback to execute
     * @param bool|callable $andWhen Additional condition to check (bool or callable that returns bool)
     * @param bool $expectReturn Whether to expect and return a value from callback
     * @param mixed $default Default value to return if conditions not met or on error
     * @return mixed Returns callback result if expectReturn=true, null otherwise
     *
     * @example
     * ```php
     * $this->shouldRunInConsole(fn() => $this->publishes([...], 'config'));
     * ```
     */
    protected function shouldRunInConsole(
        callable $callback,
        bool|callable $andWhen = true,
        bool $expectReturn = false,
        mixed $default = null
    ): mixed {
        if (! app()->runningInConsole()) {
            return $expectReturn ? $default : null;
        }

        $shouldProceed = is_callable($andWhen) ? $andWhen() : $andWhen;

        if (! $shouldProceed) {
            return $expectReturn ? $default : null;
        }

        try {
            $result = $callback();

            return $expectReturn ? $result : null;
        } catch (Throwable $e) {
            report($e);

            return $expectReturn ? $default : null;
        }
    }

    /**
     * Run a callback only in console and in the given environment(s).
     *
     * @param callable $callback The callback to execute
     * @param string|array<int, string> $environments Environment(s) to run in
     * @param bool $expectReturn Whether to expect return value
     * @param mixed $default Default value on failure
     *
     * @example
     * ```php
     * $this->shouldRunInConsoleEnvironment(fn() => $this->loadFactories(), 'local');
     * ```
     */
    protected function shouldRunInConsoleEnvironment(
        callable $callback,
        string|array $environments,
        bool $expectReturn = false,
        mixed $default = null
    ): mixed {
        return $this->shouldRunInConsole(
            callback: $callback,
            andWhen: fn () => app()->environment($environments),
            expectReturn: $expectReturn,
            default: $default
        );
    }

    /**
     * Run a callback only in console and when the condition is true.
     *
     * @param callable $callback The callback to execute
     * @param bool|callable $condition Condition to check
     * @param bool $expectReturn Whether to expect return value
     * @param mixed $default Default value on failure
     *
     * @example
     * ```php
     * $this->shouldRunInConsoleWhen(
     *     fn() => $this->registerFeature(),
     *     fn() => config('package.feature_enabled', false)
     * );
     * ```
     */
    protected function shouldRunInConsoleWhen(
        callable $callback,
        bool|callable $condition,
        bool $expectReturn = false,
        mixed $default = null
    ): mixed {
        return $this->shouldRunInConsole(
            callback: $callback,
            andWhen: $condition,
            expectReturn: $expectReturn,
            default: $default
        );
    }

    /**
     * Run several callbacks in sequence when in console. Stops on the
     * first error without throwing.
     *
     * @param array<callable> $callbacks Array of callbacks to execute
     * @param bool|callable $andWhen Additional condition
     * @return bool True if all succeeded, false if any failed or conditions not met
     *
     * @example
     * ```php
     * $this->shouldRunInConsoleMultiple([
     *     fn() => $this->publishes([...], 'config'),
     *     fn() => $this->publishes([...], 'views'),
     * ]);
     * ```
     */
    protected function shouldRunInConsoleMultiple(
        array $callbacks,
        bool|callable $andWhen = true
    ): bool {
        if (! app()->runningInConsole()) {
            return false;
        }

        $shouldProceed = is_callable($andWhen) ? $andWhen() : $andWhen;

        if (! $shouldProceed) {
            return false;
        }

        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                report($e);

                return false;
            }
        }

        return true;
    }
}

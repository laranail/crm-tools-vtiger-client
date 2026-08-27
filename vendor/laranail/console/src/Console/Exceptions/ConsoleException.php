<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for the laranail/console package.
 *
 * All Tools and Prompter exceptions extend this, so consumers can catch the
 * whole package with a single type. Messages are resolved from the `laranail-console::`
 * translation namespace with a safe fallback, so a missing key never produces
 * an empty-message exception.
 *
 * @phpstan-consistent-constructor
 */
class ConsoleException extends RuntimeException
{
    /**
     * @param array<string, scalar|null> $replace
     */
    public static function fromKey(string $key, array $replace = [], ?Throwable $previous = null): static
    {
        return new static(self::resolveMessage($key, $replace), 0, $previous);
    }

    /**
     * Resolve a translation key to a message, falling back to a readable string
     * when translations are unavailable or the key is missing.
     *
     * @param array<string, scalar|null> $replace
     */
    protected static function resolveMessage(string $key, array $replace = []): string
    {
        $namespaced = "laranail-console::{$key}";

        // Only use the translator when a container has actually bound it, so
        // the exception is still usable outside a booted Laravel application.
        if (function_exists('app') && app()->bound('translator')) {
            $message = trans($namespaced, $replace);

            if (is_string($message) && $message !== $namespaced) {
                return $message;
            }
        }

        // Fallback: humanise the leaf key and interpolate replacements.
        $leaf = str_replace(['_', '.'], ' ', strrchr($key, '.') ?: $key);
        $message = ucfirst(trim(ltrim($leaf, ' ')));

        foreach ($replace as $search => $value) {
            $message .= sprintf(' (%s: %s)', $search, (string) $value);
        }

        return $message;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Exceptions;

use Simtabi\Laranail\Console\Exceptions\ConsoleException;

/**
 * Prompter-domain exception. Messages resolve from `laranail-console::prompter.*` with a
 * safe fallback (via {@see ConsoleException::fromKey()}), so a missing key never
 * produces an empty-message exception.
 */
class PrompterException extends ConsoleException
{
    /**
     * @param array<string, scalar|null> $variables
     */
    public static function triggerErrorMessage(string $key, array $variables = []): static
    {
        return static::fromKey("prompter.{$key}", $variables);
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    public static function badMethodCall(array $variables = []): static
    {
        return static::triggerErrorMessage('bad_context_method_call', $variables);
    }
}

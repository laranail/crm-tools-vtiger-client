<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Exceptions;

use Simtabi\Laranail\Console\Exceptions\ConsoleException;

/**
 * Thrown when a required value is requested in a non-interactive session.
 *
 * Prevents silently proceeding with an empty credential — the caller must
 * supply the value through a command option or environment variable instead.
 */
final class NonInteractiveException extends ConsoleException
{
    public static function forValue(string $label): self
    {
        return self::fromKey('console.non_interactive_required', ['label' => $label]);
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Exceptions;

use Simtabi\Laranail\Console\Exceptions\ConsoleException;

/**
 * Thrown when a colour value is not a valid hex code or known colour name.
 */
final class InvalidColorException extends ConsoleException
{
    public static function forValue(string $value): self
    {
        return self::fromKey('console.invalid_color', ['value' => $value]);
    }
}

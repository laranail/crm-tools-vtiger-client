<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use Exception;

/**
 * Thrown when code attempts to mutate a value object or structure that is
 * meant to be immutable.
 */
class ImmutableDataException extends Exception
{
    /**
     * Build the exception for an attempt to write a read-only property.
     */
    public static function forProperty(string $property): self
    {
        return new self("Cannot modify immutable property: {$property}");
    }
}

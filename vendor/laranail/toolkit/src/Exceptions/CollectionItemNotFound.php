<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use Exception;

/**
 * Thrown when a lookup against a collection fails to find the requested item.
 */
class CollectionItemNotFound extends Exception
{
    /**
     * Build the exception for a missing key in a collection.
     */
    public static function forKey(int|string $key): self
    {
        return new self("No collection item found for key: {$key}");
    }
}

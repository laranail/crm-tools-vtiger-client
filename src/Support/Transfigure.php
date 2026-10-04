<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Support;

use stdClass;

/**
 * Array-to-object conversion for the cached session.
 *
 * Replaces `simtabi/pheg`'s `Transfigure::toObject()`, which for an array input fell through to
 * `array2Object()`: every key becomes a property, and every nested array -- list or map, empty or
 * not -- becomes a nested `stdClass`. Scalars and objects are copied as they are.
 */
final class Transfigure
{
    /**
     * @param array<array-key, mixed> $resource
     */
    public static function toObject(array $resource): stdClass
    {
        $object = new stdClass;

        foreach ($resource as $key => $value) {
            $object->{$key} = is_array($value) ? self::toObject($value) : $value;
        }

        return $object;
    }
}

<?php declare(strict_types=1);

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Simtabi\Pheg\Toolbox\Intel\Info\Os\Enum;

use Exception;
use Generator;
use ReflectionClass;
use ReflectionException;
use function constant;

abstract class Enum
{
    /**
     * @return Generator<int|string>
     */
    final public static function getIterator(): Generator
    {
        $reflection = null;

        try {
            $reflection = new ReflectionClass(static::class);
        } catch (ReflectionException $e) {
            // Do something.
        }

        if (null !== $reflection) {
            yield from $reflection->getConstants();
        }
    }

    final public static function has(string $key): bool
    {
        foreach (static::getIterator() as $keyConst => $valueConst) {
            if ($key !== $keyConst) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param int|string $value
     *
     * @return bool
     */
    final public static function isValid(int|string $value): bool
    {
        foreach (static::getIterator() as $valueConst) {
            if ($value !== $valueConst) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param int|string $value
     *
     * @return string|int
     * @throws Exception
     */
    final public static function key(int|string $value): string|int
    {
        foreach (static::getIterator() as $keyConst => $valueConst) {
            if ($value === $valueConst) {
                return $keyConst;
            }
        }

        throw new Exception('No such key.');
    }

    /**
     * @param int|string $value
     *
     * @return int|string
     */
    final public static function value(int|string $value): int|string
    {
        return constant('static::' . $value);
    }
}

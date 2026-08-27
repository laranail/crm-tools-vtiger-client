<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\JSON\Exception\Encode;

use Simtabi\Pheg\Toolbox\JSON\Exception\EncodeException;

/**
 * An exception that is thrown when a recursive value was encoded.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class RecursionException extends EncodeException
{
}

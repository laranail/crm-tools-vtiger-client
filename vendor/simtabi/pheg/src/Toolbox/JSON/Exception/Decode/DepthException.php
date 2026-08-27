<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\JSON\Exception\Decode;

use Simtabi\Pheg\Toolbox\JSON\Exception\DecodeException;

/**
 * An exception that is thrown when the maximum stack depth is reached.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class DepthException extends DecodeException
{
}

<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\JSON\Exception\Decode;

use Simtabi\Pheg\Toolbox\JSON\Exception\DecodeException;

/**
 * An exception that is thrown when an encoded value is not JSON or is malfored.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class StateMismatchException extends DecodeException
{
}

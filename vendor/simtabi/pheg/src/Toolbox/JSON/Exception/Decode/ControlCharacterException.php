<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\JSON\Exception\Decode;

use Simtabi\Pheg\Toolbox\JSON\Exception\DecodeException;

/**
 * An exception that is thrown when an unexpected control character is found.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class ControlCharacterException extends DecodeException
{
}

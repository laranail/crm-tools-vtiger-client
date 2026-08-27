<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Arr\Query\Exceptions;

class NullValueException extends \Exception
{
    public function __construct($message = "Null value exception", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

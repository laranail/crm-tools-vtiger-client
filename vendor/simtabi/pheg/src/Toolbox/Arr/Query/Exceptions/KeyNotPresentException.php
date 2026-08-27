<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Arr\Query\Exceptions;

class KeyNotPresentException extends \Exception
{
    public function __construct($message = "Key or property not present exception", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

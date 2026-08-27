<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Arr\Query\Exceptions;

class FileNotFoundException extends \Exception
{
    public function __construct($message = "File not found exception", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

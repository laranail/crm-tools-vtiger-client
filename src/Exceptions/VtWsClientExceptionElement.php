<?php

namespace Simtabi\Laranail\CrmTools\VtigerClient\Exceptions;

class VtWsClientExceptionElement
{
    private string $_message;

    private int $_errorCode;

    /**
     * VtigerErrorElement constructor.
     */
    public function __construct(string $message, int $errorCode)
    {
        $this->_message = $message;
        $this->_errorCode = $errorCode;
    }

    public function getMessage(): string
    {
        return $this->_message;
    }

    public function getErrorCode(): int
    {
        return $this->_errorCode;
    }
}

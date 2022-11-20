<?php

namespace USIPCOM\VtWsClient\Exceptions;

class VtWsClientExceptionElement
{

    /**
     * @var string
     */
    private string $_message;

    /**
     * @var int
     */
    private int $_errorCode;

    /**
     * VtigerErrorElement constructor.
     *
     * @param string $message
     * @param int $errorCode
     */
    public function __construct(string $message, int $errorCode)
    {
        $this->_message   = $message;
        $this->_errorCode = $errorCode;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->_message;
    }

    /**
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->_errorCode;
    }

}

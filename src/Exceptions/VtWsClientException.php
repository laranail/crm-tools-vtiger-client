<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Exceptions;

use Exception;
use Traversable;
use ArrayIterator;
use IteratorAggregate;

class VtWsClientException extends Exception implements IteratorAggregate
{
    protected $message;

    protected $code;

    /**
     * Redefine the exception so message isn't optional
     */
    public function __construct($message, $code = 11, ?Exception $previous = null)
    {
        $this->message = $message;
        $this->code = $code;

        // make sure everything is assigned properly
        parent::__construct($this->message, 0, $previous);
    }

    /**
     * Custom string representation of object
     *
     * @return string A custom string representation of exception
     */
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    /**
     * Build a new error using the specified format from the errors array
     *
     * @param VtWsClientExceptionElement[] $errorsArray
     */
    public static function init(array $errorsArray, int $codeToUse, ?string $extraMessage = null): static
    {
        return new self(
            $errorsArray[$codeToUse]->getMessage() . $extraMessage,
            $errorsArray[$codeToUse]->getErrorCode(),
        );
    }

    public static function getVtWsExceptionErrors(): array
    {
        return [
            0  => new VtWsClientExceptionElement('Error received back from the API - ', 0),
            1  => new VtWsClientExceptionElement('API request did not complete correctly - Response code: ', 1),
            2  => new VtWsClientExceptionElement('Success property not set on VTiger response', 2),
            3  => new VtWsClientExceptionElement('Error property not set on VTiger response when success is false', 3),
            4  => new VtWsClientExceptionElement('There are no search fields in the array', 4),
            5  => new VtWsClientExceptionElement('Could not complete login request within %s tries', 5),
            6  => new VtWsClientExceptionElement('Could not complete get token request within %s tries. %s', 6),
            7  => new VtWsClientExceptionElement('Guzzle ran into problems - ', 7),
            8  => new VtWsClientExceptionElement('Laravel Cache problem', 8),
            9  => new VtWsClientExceptionElement("Unsupported '%s' request type/method", 9),
            10 => new VtWsClientExceptionElement("Failed to execute %s call on '%s' URL within %s tries", 10),
            11 => new VtWsClientExceptionElement('Unknown error', 11),
            12 => new VtWsClientExceptionElement('Failed to log into vTiger CRM API (Username: %s, URI: %s)', 12),
            13 => new VtWsClientExceptionElement('Unknown login mode via: %s', 13),
            14 => new VtWsClientExceptionElement('A valid sessionId is required to be able to logout', 14),
            15 => new VtWsClientExceptionElement('You must provide a valid username', 15),
            16 => new VtWsClientExceptionElement('You must provide a valid access key', 16),
            17 => new VtWsClientExceptionElement('You must provide a valid password', 17),
            18 => new VtWsClientExceptionElement("You have to specified a list of operation parameters, but apparently it's not an associative array ('prop' => value)!", 18),
        ];
    }

    public static function getVtWsExceptionError(int $key): VtWsClientExceptionElement
    {
        $errors = self::getVtWsExceptionErrors();

        return $errors[$key];
    }

    /**
     * Retrieve an external iterator
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     *
     * @return Traversable An instance of an object implementing \Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->getAllProperties());
    }

    /**
     * Gets all the properties of the object
     *
     * @return array Array of properties
     */
    private function getAllProperties(): array
    {
        $allProperties = get_object_vars($this);
        $properties = [];
        foreach ($allProperties as $fullName => $value) {
            $fullNameComponents = explode("\0", $fullName);
            $propertyName = array_pop($fullNameComponents);
            if ($propertyName && isset($value)) {
                $properties[$propertyName] = $value;
            }
        }

        return $properties;
    }
}

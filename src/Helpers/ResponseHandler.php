<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Helpers;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Simtabi\Laranail\CrmTools\VtigerClient\Exceptions\VtWsClientException;
use stdClass;
use Throwable;

class ResponseHandler
{
    public function __construct() {}

    /**
     * @throws VtWsClientException
     */
    public static function backoff(callable $body, ?string $baseUri, ?string $endpoint, int $maximumRetries = 10): ResponseInterface|stdClass
    {
        // perform API GET request
        $tryCounter = 1;
        do {

            try {

                $response = $body();

            } catch (GuzzleException|RequestException|InvalidArgumentException|Throwable|Exception $exception) {
                throw new VtWsClientException(sprintf(
                    VtWsClientException::getVtWsExceptionError(6)->getMessage(),
                    $maximumRetries,
                    '['.$exception->getMessage().']',
                ), 6);
            }

            $tryCounter++;
        } while (! isset(self::_processResponse($response)->success) && $tryCounter <= $maximumRetries);

        if ($tryCounter >= $maximumRetries) {
            throw new VtWsClientException(sprintf(
                VtWsClientException::getVtWsExceptionError(10)->getMessage(),
                $endpoint,
                $baseUri,
                $maximumRetries,
            ), 10);
        }

        // decode the response
        return $response;
    }

    /**
     * Get the json decoded response from either the body or the contents
     */
    private static function _processResponse(ResponseInterface $response): ?stdClass
    {
        if (! empty($response->getBody()->getContents())) {
            $response->getBody()->rewind();

            return json_decode($response->getBody()->getContents());
        } else {
            return json_decode($response->getBody());
        }
    }
}

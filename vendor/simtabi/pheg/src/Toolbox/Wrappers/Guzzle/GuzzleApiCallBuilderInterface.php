<?php

namespace Simtabi\Pheg\Toolbox\Wrappers\Guzzle;

use Psr\Http\Message\ResponseInterface;

interface GuzzleApiCallBuilderInterface
{
    /**
     * @param string $method
     * @return static
     */
    public function method(string $method): static;

    /**
     * @param string $uri
     * @return static
     */
    public function uri(string $uri): static;

    /**
     * @param array $body
     * @return static
     */
    public function body(array $body): static;

    /**
     * @param array $formParams
     * @return static
     */
    public function formParams(array $formParams): static;

    /**
     * @param array $multipart
     * @return static
     */
    public function multipart(array $multipart): static;

    /**
     * @param array $header
     * @return static
     */
    public function header(array $header): static;

    /**
     * @param string $token
     * @return static
     */
    public function bearerToken(string $token): static;

    /**
     * @return mixed
     */
    public function call(): ResponseInterface;

}
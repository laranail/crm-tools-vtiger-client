<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Wrappers\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

class GuzzleApiCallBuilder implements GuzzleApiCallBuilderInterface
{
    /** @var string */
    const HTTP_GET = 'GET';

    /** @var string */
    const HTTP_POST = 'POST';

    /** @var string */
    const HTTP_PUT = 'PUT';

    /** @var string */
    const HTTP_PATCH = 'PATCH';

    /** @var string */
    const HTTP_DELETE = 'DELETE';

    /** @var string $apiUrl */
    protected string $apiUrl;

    /** @var string $method */
    protected string $method;

    /** @var string $uri */
    protected string $uri = '/';

    /** @var array $body */
    protected array $body = [];

    /** @var array $formParams */
    protected array $formParams = [];

    /** @var array $multipart */
    protected array $multipart = [];

    /** @var array $headers */
    protected array $headers = [];

    /** @var array $queryString */
    protected array $queryString = [];

    /** @var Request $request */
    protected Request $request;

    /** @var Client $httpClient */
    protected Client $httpClient;

    /**
     * ApiCallBuilder constructor.
     * @param string $url
     * @param string $uri
     * @param string $method
     */
    public function __construct(string $url, string $uri, string $method = self::HTTP_POST)
    {
        $this->apiUrl     = $url;
        $this->method     = $method;
        $this->httpClient = new Client();
        $this->uri        = $uri;
    }

    /**
     * @param string $method
     * @return $this
     */
    public function method(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @param string $uri
     * @return $this
     */
    public function uri(string $uri): static
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * @param array $body
     * @return $this
     */
    public function body(array $body): static
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @param array $formParams
     * @return $this
     */
    public function formParams(array $formParams): static
    {
        $this->formParams = $formParams;
        return $this;
    }

    /**
     * @param array $multipart
     * @return $this
     */
    public function multipart(array $multipart): static
    {
        $this->multipart = $multipart;
        return $this;
    }

    /**
     * @param array $header
     * @return $this
     */
    public function header(array $header): static
    {
        $this->headers = array_merge($this->headers, $header);
        return $this;
    }

    /**
     * @param string $token
     * @return $this
     */
    public function bearerToken(string $token): static
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;
        return $this;
    }

    /**
     * @param string $token
     * @return $this
     */
    public function basicAuthentication(string $token): static
    {
        $this->headers['Authorization'] = 'Basic '. $token;
        return $this;
    }
    /**
     * @param array $queryString
     * @return $this
     */
    public function queryString(array $queryString): static
    {
        $this->queryString = $queryString;
        return $this;
    }

    /**
     * @return ResponseInterface
     */
    public function call(): ResponseInterface
    {
        try {

            $data = ['headers' => $this->headers];

            if (!empty($this->body))
            {
                $data["json"] = $this->body;

            }

            if (!empty($this->formParams))
            {
                $data["form_params"] = $this->formParams;

            }

            if (!empty($this->multipart))
            {
                $data["multipart"] = $this->multipart;

            }

            if (!empty($this->queryString))
            {
                $data["query"] = $this->queryString;
            }

            return $this->httpClient->request($this->method, $this->apiUrl . $this->uri, $data);

        } catch (ClientException|GuzzleException $e) {

            return $e->getResponse();

        }

    }

}

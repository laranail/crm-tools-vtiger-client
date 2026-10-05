<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Services;

use GuzzleHttp\Client;
use Simtabi\Laranail\Toolkit\Facades\Laranail;
use GuzzleHttp\Exception\InvalidArgumentException;
use Simtabi\Laranail\CrmTools\VtigerClient\Helpers\Helpers;
use Simtabi\Laranail\CrmTools\VtigerClient\Support\Transfigure;
use Simtabi\Laranail\CrmTools\VtigerClient\Helpers\ResponseHandler;
use Simtabi\Laranail\CrmTools\VtigerClient\Exceptions\VtWsClientException;

class Session
{
    // HTTP Client instance
    private Client $httpClient;

    private mixed $session;

    // show guzzle http errors
    private bool $httpErrors;

    // verify guzzle http requests
    private bool $verify;

    // request timeout in seconds
    private int $requestTimeout;

    // persist connection
    private bool $persistConnection;

    // maximum request retries
    private int $maximumTries;

    // cache time to live in seconds
    private int|bool $cacheTtl;

    // cache time to live in seconds
    private int $loginWithAccessKey;

    // Service URL to which client connects to
    private ?string $baseUri = null;

    private ?string $endpoint = 'webservice.php';

    // Vtiger CRM and WebServices API version
    private string $vtigerApiVersion = '0.0';

    private string $vtigerVersion = '0.0';

    // Webservice login validity
    private null|string|int $serviceExpireTime = null;

    private ?string $serviceToken = null;

    // Webservice user credentials
    private ?string $userName = null;

    private ?string $accessKey = null;

    private ?string $password = null;

    // Webservice login credentials
    private ?string $userID = null;

    private ?string $sessionName = null;

    // throw runtime errors/exceptions
    private bool $throwErrors = false;

    // store all encountered errors
    private array $errors = [];

    /**
     * Class constructor
     *
     * @throws VtWsClientException
     */
    public function __construct()
    {
        $this->baseUri = Helpers::fixUriProtocol(Helpers::getBaseUri());
        $this->httpErrors = Helpers::isHttpErrors();
        $this->verify = Helpers::isVerify();
        $this->requestTimeout = Helpers::getRequestTimeout();
        $this->maximumTries = Helpers::getMaximumTries();
        $this->persistConnection = Helpers::isPersistConnection();
        $this->cacheTtl = Helpers::getCacheTtl();
        $this->throwErrors = Helpers::isThrowErrors();
        $this->loginWithAccessKey = Helpers::isLoginWithAccessKey();

        $this->userName = Helpers::getUsername();
        $this->accessKey = Helpers::getAccessKey();
        $this->password = Helpers::getPassword();

        try {
            // Initialize WebServices API requests
            $this->httpClient = new Client([
                'base_uri'    => $this->baseUri,
                'http_errors' => $this->httpErrors,
                'verify'      => $this->verify,

            ]);
        } catch (InvalidArgumentException $e) {
            throw VtWsClientException::init(VtWsClientException::getVtWsExceptionErrors(), 7, $e->getMessage());
        }

    }

    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    public function isPersistConnection(): bool
    {
        return $this->persistConnection;
    }

    public function getMaximumTries(): int
    {
        return $this->maximumTries;
    }

    public function getCacheTtl(): int|bool
    {
        return $this->cacheTtl;
    }

    public function getLoginWithAccessKey(): int
    {
        return $this->loginWithAccessKey;
    }

    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * Get generated session & access token data from the VTiger API
     */
    public function getSession(): mixed
    {
        return $this->session;
    }

    public function isHttpErrors(): bool
    {
        return $this->httpErrors;
    }

    public function isVerify(): bool
    {
        return $this->verify;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getServiceExpireTime(): ?string
    {
        return $this->serviceExpireTime;
    }

    public function getServiceToken(): ?string
    {
        return $this->serviceToken;
    }

    public function setUserName(string $userName): static
    {
        $this->userName = $userName;

        return $this;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getUserID(): ?string
    {
        return $this->userID;
    }

    public function getSessionName(): ?string
    {
        return $this->sessionName;
    }

    public function setThrowErrors(bool $throwErrors): static
    {
        $this->throwErrors = $throwErrors;

        return $this;
    }

    public function isThrowErrors(): bool
    {
        return $this->throwErrors;
    }

    public function setErrors(array|string $errors): self
    {
        $errors = ! is_array($errors) ? [$errors] : $errors;

        if (count($this->errors) >= 1) {
            $this->errors = array_merge($this->errors, $errors);
        } else {
            $this->errors = $errors;
        }

        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Gets vTiger version, retrieved on successful login
     *
     * @return string vTiger version, retrieved on successful login
     */
    public function getVtigerVersion(): string
    {
        return $this->vtigerVersion;
    }

    /**
     * Gets vTiger WebServices API version, retrieved on successful login
     *
     * @return string vTiger WebServices API version, retrieved on successful login
     */
    public function getVtigerApiVersion(): string
    {
        return $this->vtigerApiVersion;
    }

    /**
     * Gets an array containing the basic information about current API user
     *
     * @return array Basic information about current API user
     */
    public function getUserInfo(): array
    {
        return [
            'accessKey' => $this->accessKey,
            'userName'  => $this->userName,
            'id'        => $this->userID,
        ];
    }

    /**
     * Get the session id for a login either from a stored session id or fresh from the API
     *
     * @throws VtWsClientException
     */
    public function getSessionId(): ?string
    {
        // Get the session from the cache
        $session = $this->session;

        if (! isset($session, $session->expireTime, $session->token) || ($session->expireTime < time()) || empty($session->token)) {
            $this->login($this->loginWithAccessKey);
        }

        if (isset($session->auth->sessionId)) {
            return $session->auth->sessionId;
        }

        return null;
    }

    /**
     * @throws VtWsClientException
     */
    public function login(): bool
    {

        if ($this->loginWithAccessKey) {
            $login = $this->loginWithAccessKey($this->userName, $this->accessKey);
        } else {
            $login = $this->loginWithPassword($this->userName, $this->password);
        }

        if (! $login) {
            $error = sprintf(VtWsClientException::getVtWsExceptionError(12)->getMessage(), $this->userName, $this->baseUri);
            if ($this->throwErrors) {
                throw new VtWsClientException($error, 12);
            } else {
                $this->setErrors([$error, 12]);
            }
        }

        // Store a new session if needed, to turn it off, use a negative number or 0
        $this->session = Laranail::cache()->remember(Helpers::getCacheName('session'), function () {
            return Transfigure::toObject([
                'expireTime' => $this->serviceExpireTime,
                'token'      => $this->serviceToken,
                'auth'       => [
                    'sessionId' => $this->sessionName,
                    'userId'    => $this->userID,
                ],
            ]);
        }, $this->cacheTtl);

        return $login;
    }

    /**
     * Sends HTTP request to VTiger web service API endpoint
     *
     * @param array $data HTTP request data
     * @param string $method HTTP request method (GET, POST etc)
     *
     * @return array Returns request result object (null in case of failure)
     *
     * @throws VtWsClientException
     */
    public function sendHttpRequest(array $data, string $method = 'POST'): array
    {

        try {

            if (empty($this->userName)) {
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(15)->getMessage(), 15);
            }

            if (empty($this->accessKey)) {
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(16)->getMessage(), 16);
            }

            // Perform re-login if required.
            if ($data['operation'] !== 'getchallenge' && time() > $this->serviceExpireTime) {
                $this->loginWithAccessKey($this->userName, $this->accessKey);
            }

            // set session id
            $data['sessionName'] = $this->sessionName;

            $response = ResponseHandler::backoff(function () use ($method, $data) {
                return match ($method) {
                    'GET' => $this->httpClient->get($this->endpoint, [
                        'timeout' => $this->requestTimeout,
                        'query'   => $data,
                    ]),

                    'POST' => $this->httpClient->post($this->endpoint, [
                        'form_params' => $data,
                        'timeout'     => $this->requestTimeout,
                    ]),

                    default => throw new VtWsClientException(sprintf(
                        VtWsClientException::getVtWsExceptionError(9)->getMessage(),
                        $method,
                    ), 9),
                };
            }, $this->baseUri, $this->endpoint, $this->maximumTries);

        } catch (VtWsClientException $exception) {
            if ($this->throwErrors) {
                throw new VtWsClientException($exception->getMessage(), $exception->getCode());
            } else {
                $this->setErrors($exception->getMessage());
            }
        }

        if (isset($response)) {
            $response = json_decode($response->getBody(), true);

            if ((is_array($response) && ! self::hasHttpResponseErrors($response))) {
                return $response['result'];
            }
        }

        return [];
    }

    /**
     * Closure function to ensure session persistence when requires
     *
     * @throws VtWsClientException
     */
    public function sessionHandler(callable $body): array|bool
    {
        $sessionId = $this->getSessionId();

        if (Helpers::isClosure($body)) {
            $response = $body();

            $this->logout($sessionId);

            return $response;
        }

        return false;
    }

    /**
     * Logout from the VTiger API
     *
     * @throws VtWsClientException
     */
    protected function logout(?string $sessionId = null): bool|array
    {
        if ($this->persistConnection) {
            return true;
        }

        try {

            // get session id
            if (empty($sessionId)) {
                $sessionId = isset($this->session->getSession()->auth->sessionId) ? $this->session->getSession()->auth->sessionId : null;
            }

            // if we still don't have a valid session id
            if (empty($sessionId)) {
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(14)->getMessage(), 14);
            }

            // send a request to close current connection
            $response = $this->sendHttpRequest([
                'sessionName' => $sessionId,
                'operation'   => 'logout',
            ], 'POST');

            // if signout was successful
            if (isset($response['message']) && ! empty($response['message']) && ($response['message'] === 'successful')) {
                return true;
            }

        } catch (VtWsClientException $exception) {
            if ($this->throwErrors) {
                throw new VtWsClientException($exception->getMessage(), $exception->getCode());
            } else {
                $this->setErrors($exception->getMessage());
            }
        }

        return false;
    }

    /**
     * Check if server response contains an error, therefore the requested operation has failed
     *
     * @static
     *
     * @param array $response Server response object to check for errors
     *
     * @return bool True if response object contains an error
     *
     * @throws VtWsClientException
     */
    private static function hasHttpResponseErrors(array $response): bool
    {
        if (isset($response['success']) && (bool) $response['success'] === true) {
            return false;
        }

        if (isset($response['error']) && ! empty($response['error'])) {
            $error = $response['error'];
            throw new VtWsClientException($error['message'], $error['code']);
        }

        // This should never happen
        throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(11)->getMessage(), 11);
    }

    /**
     * Login to the server using username and VTiger access key token
     *
     * @param string|null $username VTiger user name
     * @param string|null $accessKey VTiger access key token (visible on user profile/settings page)
     *
     * @return bool Returns true if login operation has been successful
     *
     * @throws VtWsClientException
     */
    private function loginWithAccessKey(?string $username, ?string $accessKey): bool
    {

        if (empty($username)) {
            throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(15)->getMessage(), 15);
        }

        if (empty($accessKey)) {
            throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(16)->getMessage(), 16);
        }

        // Do the challenge before logging in
        if ($this->passChallenge($username) === false) {
            return false;
        }

        $result = $this->sendHttpRequest([
            'operation' => 'login',
            'username'  => $username,
            'accessKey' => md5($this->serviceToken . $accessKey),
        ]);

        if (! is_array($result) || empty($result)) {
            return false;
        }

        // Backup logged-in user credentials
        $this->userName = $username;
        $this->accessKey = $accessKey;

        // Session data
        $this->sessionName = $result['sessionName'];
        $this->userID = $result['userId'];

        // Vtiger CRM and WebServices API version
        $this->vtigerApiVersion = $result['version'];
        $this->vtigerVersion = $result['vtigerVersion'];

        return true;
    }

    /**
     * Allows you to login using username and password instead of access key (works on some VTige forks)
     *
     * @param string|null $username VTiger user name
     * @param string|null $password VTiger password (used to access CRM using the standard login page)
     * @param string|null $accessKey This parameter will be filled with user's VTiger access key
     *
     * @return bool Returns true if login operation has been successful
     *
     * @throws VtWsClientException
     */
    private function loginWithPassword(?string $username, ?string $password, ?string &$accessKey = null): bool
    {
        try {

            if (empty($username)) {
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(15)->getMessage(), 15);
            }

            if (empty($password)) {
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(17)->getMessage(), 17);
            }

            // Do the challenge before logging in
            if ($this->passChallenge($username) === false) {
                return false;
            }

            $result = $this->sendHttpRequest([
                'operation' => 'login_pwd',
                'username'  => $username,
                'password'  => $password,
            ]);

            if (! is_array($result) || empty($result)) {
                return false;
            }

            $this->accessKey = array_key_exists('accesskey', $result) ? $result['accesskey'] : $result[0];

            return $this->loginWithAccessKey($username, $accessKey);
        } catch (VtWsClientException $exception) {
            if ($this->throwErrors) {
                throw new VtWsClientException($exception->getMessage(), $exception->getCode());
            } else {
                $this->setErrors($exception->getMessage());
            }
        }

        return false;
    }

    /**
     * Gets a challenge token from the server and stores for future requests
     *
     * @param string $username VTiger user name
     *
     * @return bool Returns false in case of failure
     *
     * @throws VtWsClientException
     */
    private function passChallenge(string $username): bool
    {
        $result = $this->sendHttpRequest([
            'operation' => 'getchallenge',
            'username'  => $username,
        ], 'GET');

        if (! is_array($result) || ! isset($result['token'])) {
            return false;
        }

        $this->serviceExpireTime = $result['expireTime'];
        $this->serviceToken = $result['token'];

        return true;
    }
}

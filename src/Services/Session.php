<?php

namespace USIPCOM\VtWsClient\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\InvalidArgumentException;
use Laranail;
use USIPCOM\VtWsClient\Exceptions\VtWsClientException;
use USIPCOM\VtWsClient\Helpers\Helpers;
use USIPCOM\VtWsClient\Helpers\ResponseHandler;

class Session
{
    // HTTP Client instance
    private Client   $httpClient;
    private mixed    $session;

    // show guzzle http errors
    private bool     $httpErrors;

    // verify guzzle http requests
    private bool     $verify;

    // request timeout in seconds
    private int      $requestTimeout;

    // persist connection
    private bool     $persistConnection;

    // maximum request retries
    private int      $maximumTries;

    // cache time to live in seconds
    private int|bool $cacheTtl;

    // cache time to live in seconds
    private int       $loginWithAccessKey;


    // Service URL to which client connects to
    private ?string   $baseUri  = null;
    private ?string   $endpoint = 'webservice.php';

    // Vtiger CRM and WebServices API version
    private string    $vtigerApiVersion = '0.0';
    private string    $vtigerVersion    = '0.0';

    // Webservice login validity
    private null|string|int $serviceExpireTime = null;
    private ?string         $serviceToken      = null;

    // Webservice user credentials
    private ?string  $userName  = null;
    private ?string  $accessKey = null;
    private ?string  $password  = null;

    // Webservice login credentials
    private ?string   $userID      = null;
    private ?string   $sessionName = null;

    // throw runtime errors/exceptions
    private bool      $throwErrors  = false;

    // store all encountered errors
    private array     $errors       = [];

    /**
     * @return int
     */
    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    /**
     * @return bool
     */
    public function isPersistConnection(): bool
    {
        return $this->persistConnection;
    }

    /**
     * @return int
     */
    public function getMaximumTries(): int
    {
        return $this->maximumTries;
    }

    /**
     * @return int|bool
     */
    public function getCacheTtl(): int|bool
    {
        return $this->cacheTtl;
    }

    /**
     * @return int
     */
    public function getLoginWithAccessKey(): int
    {
        return $this->loginWithAccessKey;
    }

    /**
     * @return Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * Get generated session & access token data from the VTiger API
     *
     * @return mixed
     */
    public function getSession(): mixed
    {
        return $this->session;
    }

    /**
     * @return bool
     */
    public function isHttpErrors(): bool
    {
        return $this->httpErrors;
    }

    /**
     * @return bool
     */
    public function isVerify(): bool
    {
        return $this->verify;
    }

    /**
     * @return string
     */
    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return string|null
     */
    public function getServiceExpireTime(): ?string
    {
        return $this->serviceExpireTime;
    }

    /**
     * @return string|null
     */
    public function getServiceToken(): ?string
    {
        return $this->serviceToken;
    }

    /**
     * @param string $userName
     * @return static
     */
    public function setUserName(string $userName): static
    {
        $this->userName = $userName;
        return $this;
    }

    /**
     * @return string
     */
    public function getUserName(): string
    {
        return $this->userName;
    }

    /**
     * @return string
     */
    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @return string|null
     */
    public function getUserID(): ?string
    {
        return $this->userID;
    }

    /**
     * @return string|null
     */
    public function getSessionName(): ?string
    {
        return $this->sessionName;
    }

    /**
     * @param bool $throwErrors
     * @return static
     */
    public function setThrowErrors(bool $throwErrors): static
    {
        $this->throwErrors = $throwErrors;
        return $this;
    }

    /**
     * @return bool
     */
    public function isThrowErrors(): bool
    {
        return $this->throwErrors;
    }

    /**
     * @param string|array $errors
     * @return self
     */
    public function setErrors(array|string $errors): self
    {
        $errors = !is_array($errors) ? [$errors] : $errors;

        if (count($this->errors) >= 1) {
            $this->errors = array_merge($this->errors, $errors);
        } else {
            $this->errors = $errors;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Class constructor
     *
     * @throws VtWsClientException
     */
    public function __construct()
    {
        $this->baseUri            = Helpers::fixUriProtocol(Helpers::getBaseUri());
        $this->httpErrors         = Helpers::isHttpErrors();
        $this->verify             = Helpers::isVerify();
        $this->requestTimeout     = Helpers::getRequestTimeout();
        $this->maximumTries       = Helpers::getMaximumTries();
        $this->persistConnection  = Helpers::isPersistConnection();
        $this->cacheTtl           = Helpers::getCacheTtl();
        $this->throwErrors        = Helpers::isThrowErrors();
        $this->loginWithAccessKey = Helpers::isLoginWithAccessKey();

        $this->userName           = Helpers::getUsername();
        $this->accessKey          = Helpers::getAccessKey();
        $this->password           = Helpers::getPassword();

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

    /**
     * Gets vTiger version, retrieved on successful login
     *
     * @access public
     * @return string vTiger version, retrieved on successful login
     */
    public function getVtigerVersion(): string
    {
        return $this->vtigerVersion;
    }

    /**
     * Gets vTiger WebServices API version, retrieved on successful login
     *
     * @access public
     * @return string vTiger WebServices API version, retrieved on successful login
     */
    public function getVtigerApiVersion(): string
    {
        return $this->vtigerApiVersion;
    }

    /**
     * Gets an array containing the basic information about current API user
     *
     * @access public
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
     * @return string|null
     * @throws VtWsClientException
     */
    public function getSessionId(): ?string
    {
        // Get the session from the cache
        $session = $this->session;

        if(!isset($session, $session->expireTime, $session->token) || ($session->expireTime < time()) || empty($session->token)) {
            $this->login($this->loginWithAccessKey);
        }

        if(isset($session->auth->sessionId)) {
            return $session->auth->sessionId;
        }

        return null;
    }

    /**
     * Logout from the VTiger API
     *
     * @param string|null $sessionId
     * @return array|bool
     * @throws VtWsClientException
     */
    protected function logout(?string $sessionId = null): bool|array
    {
        if($this->persistConnection) {
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
            if (isset($response['message']) && !empty($response['message']) && ($response['message'] === 'successful')) {
                return true;
            }

        }catch (VtWsClientException $exception) {
            if ($this->throwErrors) {
                throw new VtWsClientException($exception->getMessage(), $exception->getCode());
            }else{
                $this->setErrors($exception->getMessage());
            }
        }

        return false;
    }

    /**
     * @throws VtWsClientException
     */
    public function login(): bool
    {

        if ($this->loginWithAccessKey) {
            $login = $this->loginWithAccessKey($this->userName, $this->accessKey);
        }else{
            $login = $this->loginWithPassword($this->userName, $this->password);
        }

        if (!$login) {
            $error = sprintf(VtWsClientException::getVtWsExceptionError(12)->getMessage(), $this->userName, $this->baseUri);
            if ($this->throwErrors){
                throw new VtWsClientException($error, 12);
            }else{
                $this->setErrors([$error, 12,]);
            }
        }

        // Store a new session if needed, to turn it off, use a negative number or 0
        $this->session = Laranail::cache(Helpers::getCacheName('session'), function () {
            return pheg()->transfigure()->toObject([
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
     * Login to the server using username and VTiger access key token
     * @access public
     * @param string|null $username VTiger user name
     * @param string|null $accessKey VTiger access key token (visible on user profile/settings page)
     * @return bool Returns true if login operation has been successful
     * @throws VtWsClientException
     */
    private function loginWithAccessKey(?string $username, ?string $accessKey): bool
    {

        if (empty($username)){
            throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(15)->getMessage(), 15);
        }

        if (empty($accessKey)){
            throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(16)->getMessage(), 16);
        }

        // Do the challenge before logging in
        if ($this->passChallenge($username) === false) {
            return false;
        }

        $result = $this->sendHttpRequest([
            'operation' => 'login',
            'username'  => $username,
            'accessKey' => md5($this->serviceToken.$accessKey)
        ]);

        if (!is_array($result) || empty($result)) {
            return false;
        }

        // Backup logged-in user credentials
        $this->userName  = $username;
        $this->accessKey = $accessKey;

        // Session data
        $this->sessionName = $result['sessionName'];
        $this->userID      = $result['userId'];

        // Vtiger CRM and WebServices API version
        $this->vtigerApiVersion = $result['version'];
        $this->vtigerVersion    = $result['vtigerVersion'];

        return true;
    }

    /**
     * Allows you to login using username and password instead of access key (works on some VTige forks)
     * @access public
     * @param string|null $username VTiger user name
     * @param string|null $password VTiger password (used to access CRM using the standard login page)
     * @param string|null $accessKey This parameter will be filled with user's VTiger access key
     * @return bool  Returns true if login operation has been successful
     * @throws VtWsClientException
     */
    private function loginWithPassword(?string $username, ?string $password, ?string &$accessKey = null): bool
    {
        try {

            if (empty($username)){
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(15)->getMessage(), 15);
            }

            if (empty($password)){
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(17)->getMessage(), 17);
            }

            // Do the challenge before logging in
            if ($this->passChallenge($username) === false) {
                return false;
            }

            $result = $this->sendHttpRequest([
                'operation' => 'login_pwd',
                'username'  => $username,
                'password'  => $password
            ]);

            if (!is_array($result) || empty($result)) {
                return false;
            }

            $this->accessKey = array_key_exists('accesskey', $result) ? $result['accesskey'] : $result[0];

            return $this->loginWithAccessKey($username, $accessKey);
        }catch (VtWsClientException $exception) {
            if ($this->throwErrors) {
                throw new VtWsClientException($exception->getMessage(), $exception->getCode());
            }else{
                $this->setErrors($exception->getMessage());
            }
        }

        return false;
    }

    /**
     * Gets a challenge token from the server and stores for future requests
     *
     * @access private
     * @param string $username VTiger user name
     * @return boolean Returns false in case of failure
     * @throws VtWsClientException
     */
    private function passChallenge(string $username): bool
    {
        $result = $this->sendHttpRequest([
            'operation' => 'getchallenge',
            'username'  => $username
        ], 'GET');

        if (!is_array($result) || !isset($result['token'])) {
            return false;
        }

        $this->serviceExpireTime = $result['expireTime'];
        $this->serviceToken      = $result['token'];

        return true;
    }

    /**
     * Sends HTTP request to VTiger web service API endpoint
     * @access private
     * @param array $data HTTP request data
     * @param string $method HTTP request method (GET, POST etc)
     * @return array Returns request result object (null in case of failure)
     * @throws VtWsClientException
     */
    public function sendHttpRequest(array $data, string $method = 'POST'): array
    {

        try {

            if (empty($this->userName)){
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(15)->getMessage(), 15);
            }

            if (empty($this->accessKey)){
                throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(16)->getMessage(), 16);
            }

            // Perform re-login if required.
            if ('getchallenge' !== $data['operation'] && time() > $this->serviceExpireTime) {
                $this->loginWithAccessKey($this->userName, $this->accessKey);
            }

            // set session id
            $data['sessionName'] = $this->sessionName;

            $response = ResponseHandler::backoff(function () use ($method, $data) {
                return match ($method) {
                    'GET'   => $this->httpClient->get($this->endpoint, [
                        'timeout'     => $this->requestTimeout,
                        'query'       => $data,
                    ]),

                    'POST'  => $this->httpClient->post($this->endpoint, [
                        'form_params' => $data,
                        'timeout'     => $this->requestTimeout,
                    ]),

                    default => throw new VtWsClientException(sprintf(
                        VtWsClientException::getVtWsExceptionError(9)->getMessage(),
                        $method
                    ), 9),
                };
            }, $this->baseUri, $this->endpoint, $this->maximumTries);

        }catch (VtWsClientException $exception) {
            if ($this->throwErrors) {
                throw new VtWsClientException($exception->getMessage(), $exception->getCode());
            }else{
                $this->setErrors($exception->getMessage());
            }
        }

        if (isset($response)) {
            $response = json_decode($response->getBody(), true);

            if ((is_array($response) && !self::hasHttpResponseErrors($response))) {
                return $response['result'];
            }
        }

        return [];
    }

    /**
     * Check if server response contains an error, therefore the requested operation has failed
     *
     * @access private
     * @static
     * @param array $response Server response object to check for errors
     * @return boolean  True if response object contains an error
     * @throws VtWsClientException
     */
    private static function hasHttpResponseErrors(array $response): bool
    {
        if (isset($response['success']) && true === (bool) $response['success']) {
            return false;
        }

        if (isset($response['error']) && !empty($response['error'])) {
            $error = $response['error'];
            throw new VtWsClientException($error['message'], $error['code']);
        }

        // This should never happen
        throw new VtWsClientException(VtWsClientException::getVtWsExceptionError(11)->getMessage(), 11);
    }

    /**
     * Closure function to ensure session persistence when requires
     *
     * @param callable $body
     * @return array|bool
     * @throws VtWsClientException
     */
    public function sessionHandler(callable $body): array|bool
    {
        $sessionId = $this->getSessionId();

        if (Helpers::isClosure($body)) {
            $response  = $body();

            $this->logout($sessionId);

            return $response;
        }

        return false;
    }

}

<?php

return [
    // base uri
    'base_uri' => env('VTWSCLIENT_BASE_URI'),

    // connection configuration
    'persist_connection' => env('VTWSCLIENT_PERSIST_CONNECTION', true), // Persist connection
    'request_timeout' => env('VTWSCLIENT_REQUEST_TIMEOUT', 60),   // Number of seconds after which request times out
    'maximum_retries' => env('VTWSCLIENT_MAXIMUM_RETRIES', 10),   // Number of maximum retries
    'cache_ttl' => env('VTWSCLIENT_CACHE_TTL', 21600),   // Cache TTL in seconds (6hrs = 21600)

    // guzzle client config
    'http_errors' => env('VTWSCLIENT_GUZZLE_HTTP_ERRORS', true),   // Show guzzle HTTP errors
    'verify' => env('VTWSCLIENT_GUZZLE_VERIFY', false),   // Verify guzzle requests

    // show runtime errors/exceptions
    'throw_errors' => env('VTWSCLIENT_THROW_ERRORS', false),   // Verify guzzle requests

    // auth config
    'auth' => [
        'username' => env('VTWSCLIENT_USERNAME'),
        'access_key' => env('VTWSCLIENT_ACCESS_KEY'),
        'password' => env('VTWSCLIENT_PASSWORD'),
        'login_with_access_key' => env('VTWSCLIENT_LOGIN_WITH_ACCESS_KEY'),
    ],
];

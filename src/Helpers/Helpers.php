<?php declare(strict_types=1);

namespace USIPCOM\VtWsClient\Helpers;

use Illuminate\Database\Eloquent\Collection as EC;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Respect\Validation\Validator as v;

class Helpers
{

    public const PACKAGE_NAME = 'vtiger-rest-api-client';
    public const CACHE_NAME   = self::PACKAGE_NAME;

    public static function getPackageName(): string
    {
        return self::PACKAGE_NAME;
    }

    public static function getCacheName(?string $name = null): string
    {
        return Str::snake(self::CACHE_NAME . (!empty($name) ? "__{$name}" : ""));
    }

    public static function getBaseUri(): null|string
    {
        return config(self::PACKAGE_NAME . ".base_uri");
    }

    public static function isPersistConnection(): bool
    {
        return (bool) config(self::PACKAGE_NAME . ".persist_connection", true);
    }

    public static function getRequestTimeout(): int
    {
        return (int) config(self::PACKAGE_NAME . ".request_timeout", 60);
    }

    public static function getMaximumTries(): int
    {
        return (int) config(self::PACKAGE_NAME . ".maximum_retries", 10);
    }

    public static function getCacheTtl(): int
    {
        return (int) config(self::PACKAGE_NAME . ".cache_ttl", 21600);
    }

    public static function getUsername(): null|string
    {
        return config(self::PACKAGE_NAME . ".auth.username");
    }

    public static function getAccessKey(): null|string
    {
        return config(self::PACKAGE_NAME . ".auth.access_key");
    }

    public static function getPassword(): null|string
    {
        return config(self::PACKAGE_NAME . ".auth.password");
    }

    public static function isLoginWithAccessKey(): bool
    {
        return (bool) config(self::PACKAGE_NAME . ".auth.login_with_access_key", true);
    }


    public static function isHttpErrors(): bool
    {
        return (bool) config(self::PACKAGE_NAME . ".http_errors", true);
    }

    public static function isVerify(): bool
    {
        return (bool) config(self::PACKAGE_NAME . ".verify", false);
    }

    public static function isThrowErrors(): bool
    {
        return (bool) config(self::PACKAGE_NAME . ".throw_errors", false);
    }





    public static function filterWhereNotEmpty(array|Collection|EC $data, string $column, bool $toLC = true): Collection
    {
        if (!$data instanceof Collection && is_array($data))
        {
            $data = collect($data);
        }

        $data = $data->map(fn($item) => !empty($item[$column]));

        return $data->map(fn($item) => $toLC ? strtolower($item[$column]) : $item[$column]);
    }

    public static function isEmptyData($data): bool
    {
        if (is_array($data)) {
            return empty($data) || (count($data) == 0);
        }elseif (is_object($data)) {
            return count(get_object_vars($data)) == 0;
        }

        return empty($data);
    }

    public static function isValidEmail(string $email): bool
    {
       return v::email()->validate($email);
    }

    public static function fetchDataInfo( string $column, mixed $value, array $data,string $operand = '='): Collection
    {
        return (collect($data)->where($column, $operand, $value))->first();
    }

    public static function fromCamelCase(string $string): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);

        $ret     = $matches[0];
        foreach ($ret as &$match) {
            $match = ($match == strtoupper($match)) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }


    /**
     * Checks if an array is associative or not
     * @param array $array Array to test
     * @return boolean Returns true in a given array is associative and false if it's not
     */
    public static function isAssocArray(array $array): bool
    {
        if (empty($array) || !is_array($array)) {
            return false;
        }

        foreach (array_keys($array) as $key) {
            if (!is_int($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     *  Cleans and fixes vTiger URL
     * @access private
     * @static
     * @param string|null $baseUri
     * @return string Returns cleaned and fixed vTiger URL
     */
    public static function fixUriProtocol(?string $baseUri): string
    {
        if (empty($baseUri)) {
            return '';
        }

        if (!preg_match('/^https?:\/\//i', $baseUri)) {
            $baseUri = sprintf('http://%s', $baseUri);
        }

        if (strripos($baseUri, '/') !== strlen($baseUri) - 1) {
            $baseUri .= '/';
        }

       return rtrim($baseUri, '/') . '/';
    }

    /**
     * Transforms given string into Vtiger friendly module name
     *
     * @param string $moduleName
     * @return string
     */
    public static function makeModuleName(string $moduleName): string
    {
        return Str::ucfirst(Str::lower(trim($moduleName)));
    }

    /**
     * Validates if a give object can be callable or is a valid closure
     *
     * @param $body
     * @return bool
     */
    public static function isClosure($body): bool
    {
        return $body instanceof \Closure || is_callable($body);
    }

}

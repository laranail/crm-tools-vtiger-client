<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Helpers;

use Closure;
use Illuminate\Database\Eloquent\Collection as EC;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Respect\Validation\Validator as v;

class Helpers
{
    /*
     * Both of these were the bare `vtiger-rest-api-client`. A config key and a cache prefix are flat
     * global namespaces -- the consuming application or a sibling package claiming the same string
     * silently wins, and the failure surfaces as missing settings or a poisoned cache entry.
     *
     * CONFIG_KEY is separate from PACKAGE_NAME because the two registries spell a vendor scope
     * differently: config keys are dotted, everything else here is hyphenated.
     */
    public const PACKAGE_NAME = 'laranail-crm-tools-vtiger-client';

    public const CONFIG_KEY = 'laranail.crm-tools-vtiger-client';

    public const CACHE_NAME = self::PACKAGE_NAME;

    public static function getPackageName(): string
    {
        return self::PACKAGE_NAME;
    }

    public static function getCacheName(?string $name = null): string
    {
        return Str::snake(self::CACHE_NAME.(! empty($name) ? "__{$name}" : ''));
    }

    public static function getBaseUri(): ?string
    {
        return config(self::CONFIG_KEY.'.base_uri');
    }

    public static function isPersistConnection(): bool
    {
        return (bool) config(self::CONFIG_KEY.'.persist_connection', true);
    }

    public static function getRequestTimeout(): int
    {
        return (int) config(self::CONFIG_KEY.'.request_timeout', 60);
    }

    public static function getMaximumTries(): int
    {
        return (int) config(self::CONFIG_KEY.'.maximum_retries', 10);
    }

    public static function getCacheTtl(): int
    {
        return (int) config(self::CONFIG_KEY.'.cache_ttl', 21600);
    }

    public static function getUsername(): ?string
    {
        return config(self::CONFIG_KEY.'.auth.username');
    }

    public static function getAccessKey(): ?string
    {
        return config(self::CONFIG_KEY.'.auth.access_key');
    }

    public static function getPassword(): ?string
    {
        return config(self::CONFIG_KEY.'.auth.password');
    }

    public static function isLoginWithAccessKey(): bool
    {
        return (bool) config(self::CONFIG_KEY.'.auth.login_with_access_key', true);
    }

    public static function isHttpErrors(): bool
    {
        return (bool) config(self::CONFIG_KEY.'.http_errors', true);
    }

    public static function isVerify(): bool
    {
        return (bool) config(self::CONFIG_KEY.'.verify', false);
    }

    public static function isThrowErrors(): bool
    {
        return (bool) config(self::CONFIG_KEY.'.throw_errors', false);
    }

    public static function filterWhereNotEmpty(array|Collection|EC $data, string $column, bool $toLC = true): Collection
    {
        if (! $data instanceof Collection && is_array($data)) {
            $data = collect($data);
        }

        $data = $data->map(fn ($item) => ! empty($item[$column]));

        return $data->map(fn ($item) => $toLC ? strtolower($item[$column]) : $item[$column]);
    }

    public static function isEmptyData($data): bool
    {
        if (is_array($data)) {
            return empty($data) || (count($data) == 0);
        } elseif (is_object($data)) {
            return count(get_object_vars($data)) == 0;
        }

        return empty($data);
    }

    public static function isValidEmail(string $email): bool
    {
        return v::email()->validate($email);
    }

    public static function fetchDataInfo(string $column, mixed $value, array $data, string $operand = '='): Collection
    {
        return collect($data)->where($column, $operand, $value)->first();
    }

    public static function fromCamelCase(string $string): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);

        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = ($match == strtoupper($match)) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }

    /**
     * Checks if an array is associative or not
     *
     * @param  array  $array  Array to test
     * @return bool Returns true in a given array is associative and false if it's not
     */
    public static function isAssocArray(array $array): bool
    {
        if (empty($array) || ! is_array($array)) {
            return false;
        }

        foreach (array_keys($array) as $key) {
            if (! is_int($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     *  Cleans and fixes vTiger URL
     *
     * @static
     *
     * @return string Returns cleaned and fixed vTiger URL
     */
    public static function fixUriProtocol(?string $baseUri): string
    {
        if (empty($baseUri)) {
            return '';
        }

        if (! preg_match('/^https?:\/\//i', $baseUri)) {
            $baseUri = sprintf('http://%s', $baseUri);
        }

        if (strripos($baseUri, '/') !== strlen($baseUri) - 1) {
            $baseUri .= '/';
        }

        return rtrim($baseUri, '/').'/';
    }

    /**
     * Transforms given string into Vtiger friendly module name
     */
    public static function makeModuleName(string $moduleName): string
    {
        return Str::ucfirst(Str::lower(trim($moduleName)));
    }

    /**
     * Validates if a give object can be callable or is a valid closure
     */
    public static function isClosure($body): bool
    {
        return $body instanceof Closure || is_callable($body);
    }
}

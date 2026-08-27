<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Http;

use InvalidArgumentException;
use Simtabi\Laranail\Package\Tools\Services\Http\Contracts\HttpConfigurationServiceInterface;

/**
 * Fluent builder for HTTP-client option arrays.
 *
 * Defaults are read from environment variables on construction (so a
 * consuming package can override them without shipping a config file):
 *
 *   PKG_HTTP_PERSIST_CONNECTION (bool,  default true)
 *   PKG_HTTP_REQUEST_TIMEOUT    (int,   default 60)
 *   PKG_HTTP_MAX_RETRIES        (int,   default 10)
 *   PKG_HTTP_CACHE_TTL          (int,   default 10)
 *   PKG_HTTP_BASE_URI           (str,   default null)
 *   PKG_HTTP_PROXY              (str,   default null)
 *
 * Vendor-neutral by design: `package-tools` must not pull
 * `guzzlehttp/guzzle` or `laranail/laranail` into a consumer's
 * autoload graph. The returned array is keyed compatibly with both
 * Guzzle and Laravel's `Http::withOptions()` callers.
 */
final class HttpConfigurationService implements HttpConfigurationServiceInterface
{
    private bool $persistConnection;

    private int $requestTimeout;

    private int $maxRetries;

    private int $cacheTtl;

    private ?string $baseUri;

    private ?string $proxy;

    public function __construct(
        ?bool $persistConnection = null,
        ?int $requestTimeout = null,
        ?int $maxRetries = null,
        ?int $cacheTtl = null,
        ?string $baseUri = null,
        ?string $proxy = null,
    ) {
        $this->persistConnection = $persistConnection ?? $this->boolEnv('PKG_HTTP_PERSIST_CONNECTION', true);
        $this->requestTimeout = $requestTimeout ?? $this->intEnv('PKG_HTTP_REQUEST_TIMEOUT', 60);
        $this->maxRetries = $maxRetries ?? $this->intEnv('PKG_HTTP_MAX_RETRIES', 10);
        $this->cacheTtl = $cacheTtl ?? $this->intEnv('PKG_HTTP_CACHE_TTL', 10);
        $this->baseUri = $baseUri ?? $this->stringEnv('PKG_HTTP_BASE_URI');
        $this->proxy = $proxy ?? $this->stringEnv('PKG_HTTP_PROXY');

        $this->guardTimeouts();
    }

    public function setPersistConnection(bool $persist): static
    {
        $this->persistConnection = $persist;

        return $this;
    }

    public function isPersistConnection(): bool
    {
        return $this->persistConnection;
    }

    public function setRequestTimeout(int $seconds): static
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Request timeout must be >= 0.');
        }
        $this->requestTimeout = $seconds;

        return $this;
    }

    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    public function setMaxRetries(int $retries): static
    {
        if ($retries < 0) {
            throw new InvalidArgumentException('Max retries must be >= 0.');
        }
        $this->maxRetries = $retries;

        return $this;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setCacheTtl(int $seconds): static
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Cache TTL must be >= 0.');
        }
        $this->cacheTtl = $seconds;

        return $this;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function setBaseUri(?string $baseUri): static
    {
        $this->baseUri = $baseUri === null ? null : trim($baseUri);

        return $this;
    }

    public function getBaseUri(): ?string
    {
        return $this->baseUri;
    }

    public function setProxy(?string $proxy): static
    {
        $this->proxy = $proxy === null ? null : trim($proxy);

        return $this;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function toGuzzleConfig(): array
    {
        $config = [
            'persist' => $this->persistConnection,
            'timeout' => $this->requestTimeout,
            'retry' => ['max' => $this->maxRetries],
            'cache_ttl' => $this->cacheTtl,
        ];

        if ($this->baseUri !== null && $this->baseUri !== '') {
            $config['base_uri'] = $this->baseUri;
        }
        if ($this->proxy !== null && $this->proxy !== '') {
            $config['proxy'] = $this->proxy;
        }

        return $config;
    }

    private function guardTimeouts(): void
    {
        if ($this->requestTimeout < 0) {
            throw new InvalidArgumentException('PKG_HTTP_REQUEST_TIMEOUT must be >= 0.');
        }
        if ($this->maxRetries < 0) {
            throw new InvalidArgumentException('PKG_HTTP_MAX_RETRIES must be >= 0.');
        }
        if ($this->cacheTtl < 0) {
            throw new InvalidArgumentException('PKG_HTTP_CACHE_TTL must be >= 0.');
        }
    }

    private function boolEnv(string $key, bool $default): bool
    {
        // `$_ENV[$key] ?? $_SERVER[$key] ?? getenv($key)` never yields null:
        // getenv() returns string|false. PHPStan flagged the `=== null`
        // branch as dead, so it was removed.
        $raw = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($raw === false || $raw === '') {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function intEnv(string $key, int $default): int
    {
        $raw = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($raw === false || $raw === '') {
            return $default;
        }
        if (! is_numeric($raw)) {
            return $default;
        }

        return (int) $raw;
    }

    private function stringEnv(string $key): ?string
    {
        $raw = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($raw === false || $raw === '') {
            return null;
        }

        return (string) $raw;
    }
}

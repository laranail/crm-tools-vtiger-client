<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Fluent builder for Guzzle/HTTP client configuration.
 *
 * Defaults are seeded once from `config('laranail.toolkit.http.*')` (each key
 * backed by an env override defined in `config/toolkit.php`); the fluent
 * setters then let a caller tweak a single value before building the array.
 * The integer setters reject negative values, and {@see toGuzzleConfig()} emits
 * `base_uri` / `proxy` only when they are set.
 */
class HttpConfigurationService implements HttpConfigurationServiceInterface
{
    private bool $persistConnection;

    private int $requestTimeout;

    private int $maxRetries;

    private int $cacheTtl;

    private ?string $baseUri;

    private ?string $proxy;

    public function __construct(ConfigRepository $config)
    {
        $this->persistConnection = Cast::toBool($config->get('laranail.toolkit.http.persist_connection', true), true);
        $this->requestTimeout = Cast::toInt($config->get('laranail.toolkit.http.request_timeout', 60), 60);
        $this->maxRetries = Cast::toInt($config->get('laranail.toolkit.http.max_retries', 10), 10);
        $this->cacheTtl = Cast::toInt($config->get('laranail.toolkit.http.cache_ttl', 10), 10);
        $this->baseUri = $this->stringOrNull($config->get('laranail.toolkit.http.base_uri'));
        $this->proxy = $this->stringOrNull($config->get('laranail.toolkit.http.proxy'));
    }

    public function setPersistConnection(bool $persist): self
    {
        $this->persistConnection = $persist;

        return $this;
    }

    public function isPersistConnection(): bool
    {
        return $this->persistConnection;
    }

    public function setRequestTimeout(int $timeout): self
    {
        $this->requestTimeout = $this->assertNonNegative($timeout, 'request timeout');

        return $this;
    }

    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = $this->assertNonNegative($retries, 'max retries');

        return $this;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $this->assertNonNegative($ttl, 'cache TTL');

        return $this;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function setBaseUri(?string $baseUri): self
    {
        $this->baseUri = $this->stringOrNull($baseUri);

        return $this;
    }

    public function getBaseUri(): ?string
    {
        return $this->baseUri;
    }

    public function setProxy(?string $proxy): self
    {
        $this->proxy = $this->stringOrNull($proxy);

        return $this;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function toGuzzleConfig(): array
    {
        $config = [
            'persist' => $this->isPersistConnection(),
            'timeout' => $this->getRequestTimeout(),
            'retry' => ['max' => $this->getMaxRetries()],
            'cache_ttl' => $this->getCacheTtl(),
        ];

        if ($this->baseUri !== null) {
            $config['base_uri'] = $this->baseUri;
        }

        if ($this->proxy !== null) {
            $config['proxy'] = $this->proxy;
        }

        return $config;
    }

    private function assertNonNegative(int $value, string $label): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException("HTTP {$label} must be zero or greater, got {$value}.");
        }

        return $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

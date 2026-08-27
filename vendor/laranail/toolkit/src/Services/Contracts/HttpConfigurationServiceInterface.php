<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

/**
 * Fluent builder for Guzzle/HTTP client configuration.
 *
 * Defaults are seeded from `config('laranail.toolkit.http.*')` (each backed by
 * an env override); the fluent setters let a caller tweak a single config
 * before producing the array consumed by the HTTP client factory.
 */
interface HttpConfigurationServiceInterface
{
    public function setPersistConnection(bool $persist): self;

    public function isPersistConnection(): bool;

    public function setRequestTimeout(int $timeout): self;

    public function getRequestTimeout(): int;

    public function setMaxRetries(int $retries): self;

    public function getMaxRetries(): int;

    public function setCacheTtl(int $ttl): self;

    public function getCacheTtl(): int;

    /** Set (or clear, with null) the base URI applied to the client. */
    public function setBaseUri(?string $baseUri): self;

    public function getBaseUri(): ?string;

    /** Set (or clear, with null) the outbound proxy. */
    public function setProxy(?string $proxy): self;

    public function getProxy(): ?string;

    /**
     * Render the current configuration as a Guzzle config array. `base_uri` and
     * `proxy` are present only when set.
     *
     * @return array{persist: bool, timeout: int, retry: array{max: int}, cache_ttl: int, base_uri?: string, proxy?: string}
     */
    public function toGuzzleConfig(): array;
}

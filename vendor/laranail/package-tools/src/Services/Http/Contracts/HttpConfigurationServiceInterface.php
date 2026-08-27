<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Http\Contracts;

/**
 * Contract for the fluent Guzzle/Laravel HTTP-client configuration builder.
 *
 * Returns a plain associative array via `toGuzzleConfig()` that callers
 * spread into `Http::withOptions()` or `new GuzzleHttp\Client([...])`.
 * Holds no Guzzle dependency itself; keys are vendor-neutral.
 */
interface HttpConfigurationServiceInterface
{
    public function setPersistConnection(bool $persist): static;

    public function isPersistConnection(): bool;

    public function setRequestTimeout(int $seconds): static;

    public function getRequestTimeout(): int;

    public function setMaxRetries(int $retries): static;

    public function getMaxRetries(): int;

    public function setCacheTtl(int $seconds): static;

    public function getCacheTtl(): int;

    public function setBaseUri(?string $baseUri): static;

    public function getBaseUri(): ?string;

    public function setProxy(?string $proxy): static;

    public function getProxy(): ?string;

    /**
     * Serialise the current configuration as a vendor-neutral options array.
     *
     * @return array{persist: bool, timeout: int, retry: array{max: int}, cache_ttl: int, base_uri?: string, proxy?: string}
     */
    public function toGuzzleConfig(): array;
}

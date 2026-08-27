<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Concerns;

use Simtabi\Laranail\Package\Tools\Support\ErrorStorage\Contracts\ErrorStorageServiceInterface;

/**
 * Trait that proxies a host class to the container-bound
 * `ErrorStorageService`. Keeps install commands / service providers
 * free of repetitive resolve-and-delegate boilerplate.
 *
 * Bind a fresh ErrorStorageService per command/provider to avoid
 * cross-request bleed (the bag has no eviction).
 */
trait HasErrorStorage
{
    private ?ErrorStorageServiceInterface $errorStorageInstance = null;

    /**
     * Cache the resolved bag per host instance.
     *
     * The default container binding is `bind` (per-resolution), so
     * without this cache every trait method would resolve a fresh
     * empty bag and lose state across `addError()` / `hasErrors()`
     * calls. Tests can swap implementations by binding a singleton
     * before constructing the host.
     */
    protected function errorStorage(): ErrorStorageServiceInterface
    {
        return $this->errorStorageInstance ??= app(ErrorStorageServiceInterface::class);
    }

    /**
     * @param array<string, string|array<int, string>>|string $errors
     */
    protected function setErrors(array|string $errors): static
    {
        $this->errorStorage()->setErrors($errors);

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getErrors(?string $key = null): array
    {
        return $this->errorStorage()->getErrors($key);
    }

    public function hasErrors(): bool
    {
        return $this->errorStorage()->hasErrors();
    }

    public function clearErrors(): static
    {
        $this->errorStorage()->clearErrors();

        return $this;
    }

    public function addError(string $key, string $message): static
    {
        $this->errorStorage()->addError($key, $message);

        return $this;
    }

    public function getErrorCount(): int
    {
        return $this->errorStorage()->getErrorCount();
    }

    public function getFirstError(): ?string
    {
        return $this->errorStorage()->getFirstError();
    }
}

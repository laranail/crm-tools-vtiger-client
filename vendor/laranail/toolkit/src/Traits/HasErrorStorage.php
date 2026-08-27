<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Simtabi\Laranail\Toolkit\Services\Contracts\ErrorStorageServiceInterface;

/**
 * Convenience trait that delegates error management to a single, per-instance
 * {@see ErrorStorageServiceInterface} resolved from the container.
 *
 * All business logic lives in the service; this trait is a thin accessor.
 */
trait HasErrorStorage
{
    private ?ErrorStorageServiceInterface $errorStorage = null;

    /**
     * Resolve (and memoise) the error-storage service for this instance.
     */
    protected function errorStorage(): ErrorStorageServiceInterface
    {
        return $this->errorStorage ??= app(ErrorStorageServiceInterface::class);
    }

    /**
     * @param array<int|string, mixed>|string $errors
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

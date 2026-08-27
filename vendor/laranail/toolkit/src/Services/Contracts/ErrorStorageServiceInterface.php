<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

/**
 * Fluent, key-based error storage and retrieval.
 */
interface ErrorStorageServiceInterface
{
    /**
     * Set errors, merging with any already stored.
     *
     * @param array<int|string, mixed>|string $errors
     */
    public function setErrors(array|string $errors): self;

    /**
     * Get all errors, or a specific error by (dot) key.
     *
     * @return array<int|string, mixed>
     */
    public function getErrors(?string $key = null): array;

    /** Whether any errors are stored. */
    public function hasErrors(): bool;

    /** Clear all stored errors. */
    public function clearErrors(): self;

    /** Add a single keyed error message. */
    public function addError(string $key, string $message): self;

    /** Count the stored errors. */
    public function getErrorCount(): int;

    /** Get the first error message, or null when none are stored. */
    public function getFirstError(): ?string;
}

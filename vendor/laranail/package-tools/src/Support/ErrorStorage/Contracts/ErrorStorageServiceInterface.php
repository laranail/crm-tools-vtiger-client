<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\ErrorStorage\Contracts;

/**
 * Lightweight error bag used by package install commands and
 * service-provider boot routines that want to collect non-fatal
 * problems (bad config, missing optional dependency, soft validation
 * failure) and surface them all at once instead of throwing on first
 * failure.
 *
 * Not a replacement for Laravel's `Validator` errors; those should
 * still flow through the validation pipeline.
 */
interface ErrorStorageServiceInterface
{
    /**
     * Replace or merge errors. When the bag is empty, sets `$errors`;
     * otherwise merges into the existing bag (last write wins per key).
     *
     * @param array<string, string|array<int, string>>|string $errors
     */
    public function setErrors(array|string $errors): static;

    /**
     * Retrieve all errors, or the entry for the supplied key.
     *
     * @return array<int|string, mixed>
     */
    public function getErrors(?string $key = null): array;

    public function hasErrors(): bool;

    public function clearErrors(): static;

    public function addError(string $key, string $message): static;

    public function getErrorCount(): int;

    public function getFirstError(): ?string;
}

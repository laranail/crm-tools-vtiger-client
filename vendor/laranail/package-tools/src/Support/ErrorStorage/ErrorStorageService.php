<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\ErrorStorage;

use Illuminate\Support\Arr;
use Simtabi\Laranail\Package\Tools\Support\ErrorStorage\Contracts\ErrorStorageServiceInterface;

/**
 * In-memory key/message error bag. Bind as a scoped instance per
 * install command; the bag has no eviction policy and is not safe to
 * share across long-lived processes.
 *
 * Adding the same key twice promotes the value to a list of messages,
 * preserving the order they arrived in. `getFirstError()` resolves
 * lists by returning the first element.
 */
final class ErrorStorageService implements ErrorStorageServiceInterface
{
    /** @var array<string, string|array<int, string>> */
    private array $errors = [];

    public static function create(): self
    {
        return new self;
    }

    /**
     * @param array<string, string|array<int, string>>|string $errors
     */
    public static function withErrors(array|string $errors): self
    {
        return self::create()->setErrors($errors);
    }

    public function setErrors(array|string $errors): static
    {
        // A bare string has no natural key, so park it under a sentinel
        // ('_') to keep the bag string-keyed (matching the property
        // type). getFirstError() / getErrorCount() still surface it.
        if (is_string($errors)) {
            $errors = ['_' => $errors];
        }

        $this->errors = $this->errors === []
            ? $errors
            : array_merge($this->errors, $errors);

        return $this;
    }

    public function getErrors(?string $key = null): array
    {
        if ($key === null) {
            return $this->errors;
        }

        $value = Arr::get($this->errors, $key);

        return $value === null ? [] : Arr::wrap($value);
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function clearErrors(): static
    {
        $this->errors = [];

        return $this;
    }

    public function addError(string $key, string $message): static
    {
        if (! isset($this->errors[$key])) {
            $this->errors[$key] = $message;

            return $this;
        }

        $existing = Arr::wrap($this->errors[$key]);
        $existing[] = $message;
        $this->errors[$key] = $existing;

        return $this;
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function getFirstError(): ?string
    {
        if ($this->errors === []) {
            return null;
        }

        $first = Arr::first($this->errors);
        if (is_array($first)) {
            $nested = Arr::first($first);

            return is_string($nested) ? $nested : null;
        }

        return is_string($first) ? $first : null;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Support\Arr;
use Simtabi\Laranail\Toolkit\Services\Contracts\ErrorStorageServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Fluent, key-based error storage.
 *
 * Supports storing/merging errors, keyed access, existence and count checks,
 * and first-error retrieval.
 */
class ErrorStorageService implements ErrorStorageServiceInterface
{
    /**
     * @var array<int|string, mixed>
     */
    private array $errors = [];

    public function setErrors(array|string $errors): self
    {
        $errors = Arr::wrap($errors);

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

    public function clearErrors(): self
    {
        $this->errors = [];

        return $this;
    }

    public function addError(string $key, string $message): self
    {
        if (isset($this->errors[$key])) {
            $existing = Arr::wrap($this->errors[$key]);
            $this->errors[$key] = array_merge($existing, [$message]);
        } else {
            $this->errors[$key] = $message;
        }

        return $this;
    }

    public function getErrorCount(): int
    {
        // Count messages, not keys — a key holding a list of messages
        // (repeated addError()) contributes each of them.
        $count = 0;

        foreach ($this->errors as $messages) {
            $count += is_array($messages) ? count($messages) : 1;
        }

        return $count;
    }

    public function getFirstError(): ?string
    {
        if ($this->errors === []) {
            return null;
        }

        $first = Arr::first($this->errors);

        if (is_array($first)) {
            $first = Arr::first($first);
        }

        return $first === null ? null : Cast::toString($first);
    }

    /** Create a fresh instance. */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create an instance pre-seeded with errors.
     *
     * @param array<int|string, mixed>|string $errors
     */
    public static function withErrors(array|string $errors): self
    {
        return self::create()->setErrors($errors);
    }
}

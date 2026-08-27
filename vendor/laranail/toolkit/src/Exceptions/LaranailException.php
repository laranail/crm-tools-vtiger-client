<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use Exception;
use JsonSerializable;
use Simtabi\Laranail\Toolkit\Support\Cast;
use Stringable;
use Throwable;

/**
 * A rich, structured base exception for the toolkit.
 *
 * Carries an arbitrary structured payload (context), free-form metadata, an
 * optional user-facing message and an optional HTTP status. It renders cleanly
 * to JSON, exposes a PSR-3 friendly log context, and offers fluent `with*`
 * helpers plus static factories (`from()`, `wrap()`, `fromArray()`).
 *
 * @phpstan-consistent-constructor
 */
class LaranailException extends Exception implements JsonSerializable, Stringable
{
    /**
     * @param string               $message     Developer-friendly error message.
     * @param int                  $code        A domain-specific or HTTP-like code.
     * @param Throwable|null       $previous    Previous exception, if wrapping.
     * @param array<string, mixed> $context     Structured payload (arrays allowed).
     * @param array<string, mixed> $meta        Extra metadata (non-PII preferably).
     * @param string|null          $userMessage UI-facing message.
     * @param int|null             $status      Optional HTTP status.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        /**
         * Arbitrary structured payload describing the error (safe for logs).
         */
        protected array $context = [],
        /**
         * Additional metadata such as reference IDs, tags, request info, etc.
         */
        protected array $meta = [],
        protected ?string $userMessage = null,
        protected ?int $status = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Construct from an array payload.
     *
     * Recognised keys: message, code, context, meta, userMessage, status,
     * previous. Any unknown key is folded into `meta`.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): static
    {
        $message = Cast::toString($payload['message'] ?? 'Unexpected error', 'Unexpected error');
        $code = Cast::toInt($payload['code'] ?? 0);
        /** @var array<string, mixed> $context */
        $context = (array) ($payload['context'] ?? []);
        /** @var array<string, mixed> $meta */
        $meta = (array) ($payload['meta'] ?? []);
        $userMessage = isset($payload['userMessage']) ? Cast::toString($payload['userMessage']) : null;
        $status = isset($payload['status']) ? Cast::toInt($payload['status']) : null;
        $previous = ($payload['previous'] ?? null) instanceof Throwable ? $payload['previous'] : null;

        $reserved = ['message', 'code', 'context', 'meta', 'userMessage', 'status', 'previous'];
        foreach ($payload as $key => $value) {
            if (!in_array($key, $reserved, true)) {
                $meta[$key] = $value;
            }
        }

        return new static($message, $code, $previous, $context, $meta, $userMessage, $status);
    }

    /**
     * Wrap a previous Throwable, optionally overriding the message and adding
     * structured context.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $meta
     */
    public static function wrap(
        Throwable $previous,
        string $message = '',
        array $context = [],
        array $meta = [],
        ?string $userMessage = null,
        ?int $status = null,
    ): static {
        $resolvedMessage = $message !== '' ? $message : $previous->getMessage();
        $code = (int) $previous->getCode();

        return new static($resolvedMessage, $code, $previous, $context, $meta, $userMessage, $status);
    }

    /**
     * Build from an existing Throwable (alias for {@see wrap()}).
     */
    public static function from(Throwable $previous): static
    {
        return static::wrap($previous);
    }

    /**
     * Get the structured context payload.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the metadata.
     *
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /** Get the UI-friendly message (if set). */
    public function getUserMessage(): ?string
    {
        return $this->userMessage;
    }

    /** Get the HTTP status (if any). */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /** Add or replace a single context key. */
    public function withContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Merge multiple context keys recursively.
     *
     * @param array<string, mixed> $context
     */
    public function mergeContext(array $context): static
    {
        $this->context = array_replace_recursive($this->context, $context);

        return $this;
    }

    /** Add or replace a single meta key. */
    public function withMeta(string $key, mixed $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /**
     * Merge multiple meta keys recursively.
     *
     * @param array<string, mixed> $meta
     */
    public function mergeMeta(array $meta): static
    {
        $this->meta = array_replace_recursive($this->meta, $meta);

        return $this;
    }

    /** Set the user-friendly message. */
    public function withUserMessage(?string $message): static
    {
        $this->userMessage = $message;

        return $this;
    }

    /** Set or override the HTTP status. */
    public function withStatus(?int $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Render the exception to an array suitable for logging or API responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $withTrace = false): array
    {
        $array = [
            'type' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'userMessage' => $this->userMessage,
            'status' => $this->status,
            'context' => $this->context,
            'meta' => $this->meta,
        ];

        $previous = $this->getPrevious();
        if ($previous instanceof Throwable) {
            $array['previous'] = [
                'type' => $previous::class,
                'message' => $previous->getMessage(),
                'code' => (int) $previous->getCode(),
            ];
        }

        if ($withTrace) {
            $array['file'] = $this->getFile();
            $array['line'] = $this->getLine();
            $array['trace'] = $this->getTrace();
        }

        return $array;
    }

    /**
     * PSR-3 logging context helper.
     *
     * @return array<string, mixed>
     */
    public function toLogContext(bool $withTrace = false): array
    {
        return array_filter([
            'exception' => $this,
            'context' => $this->context,
            'meta' => $this->meta,
            'status' => $this->status,
            'asArray' => $this->toArray($withTrace),
        ], static fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray(false);
    }

    public function __toString(): string
    {
        $summary = sprintf('[%s] %s (code:%d)', static::class, $this->getMessage(), $this->getCode());

        if ($this->status !== null) {
            $summary .= sprintf(' status:%d', $this->status);
        }

        if ($this->context !== []) {
            $summary .= ' context=' . (string) json_encode($this->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($this->meta !== []) {
            $summary .= ' meta=' . (string) json_encode($this->meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $summary;
    }
}

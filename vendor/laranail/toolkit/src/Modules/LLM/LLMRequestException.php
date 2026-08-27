<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\LLM;

use RuntimeException;
use Throwable;

class LLMRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $retryable = false,
        ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}

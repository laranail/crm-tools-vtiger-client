<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Simtabi\Laranail\Toolkit\Enums\LogLevel;
use Simtabi\Laranail\Toolkit\Services\LogService;
use Throwable;

/**
 * Public surface of the toolkit's {@see LogService}.
 *
 * Named `LoggerServiceInterface` to avoid clashing with `Psr\Log\LoggerInterface`:
 * this is the toolkit's enriched (timestamp + environment) channel-aware logging
 * helper, not a PSR-3 logger. Bound interface→{@see LogService}.
 */
interface LoggerServiceInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(LogLevel $level, string $message, array $context = [], ?string $channel = null): void;

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = [], ?string $channel = null): void;

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = [], ?string $channel = null): void;

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = [], ?string $channel = null): void;

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = [], ?string $channel = null): void;

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = [], ?string $channel = null): void;

    /**
     * Log a throwable at error level with structured context (class, message,
     * file, line).
     */
    public function exception(Throwable $e, ?string $channel = null): void;
}

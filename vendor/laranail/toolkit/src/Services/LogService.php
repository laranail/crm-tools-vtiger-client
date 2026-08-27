<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Enums\LogLevel;
use Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;
use Throwable;

/**
 * Thin, injectable logging helper that enriches every record with a timestamp
 * and the current environment before delegating to Laravel's logger.
 *
 * Resolve it from the container (it is bound as a singleton) or inject it by
 * type — it wraps the configured {@see LogManager}, so channel selection and
 * formatting stay owned by the host app's `config/logging.php`.
 */
class LogService implements LoggerServiceInterface
{
    public function __construct(
        private readonly LogManager $logs,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function log(LogLevel $level, string $message, array $context = [], ?string $channel = null): void
    {
        $context += [
            'timestamp' => now()->toDateTimeString(),
            'env' => ToolkitConfig::string('app.env'),
        ];

        $this->logger($channel)->log($level->value, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log(LogLevel::Info, $message, $context, $channel);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log(LogLevel::Debug, $message, $context, $channel);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log(LogLevel::Warning, $message, $context, $channel);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log(LogLevel::Error, $message, $context, $channel);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log(LogLevel::Critical, $message, $context, $channel);
    }

    /**
     * Log a throwable at error level with structured context (class, message,
     * file, line). Merges into the same enriched-context pipeline as {@see log()}.
     */
    public function exception(Throwable $e, ?string $channel = null): void
    {
        $this->error($e->getMessage(), [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], $channel);
    }

    private function logger(?string $channel): LoggerInterface
    {
        return $channel !== null ? $this->logs->channel($channel) : $this->logs;
    }
}

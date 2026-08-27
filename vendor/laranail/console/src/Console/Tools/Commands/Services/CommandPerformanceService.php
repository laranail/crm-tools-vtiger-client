<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

use Illuminate\Support\Carbon;
use Simtabi\Laranail\Console\Tools\Support\FileSize;

/**
 * Command Performance Service
 *
 * Handles execution time tracking, memory monitoring, and performance metrics
 * for console commands. This service can be reused across different command types.
 */
class CommandPerformanceService
{
    /**
     * Command execution time in seconds
     */
    protected float $executionTime = 0.0;

    /**
     * Command start time (microtime)
     */
    protected float $startTime = 0.0;

    /**
     * Memory usage at start (bytes)
     */
    protected int $startMemory = 0;

    /**
     * Peak memory usage during execution (bytes)
     */
    protected int $peakMemory = 0;

    /**
     * Start execution timer
     */
    public function startTimer(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * End execution timer and calculate execution time
     */
    public function endTimer(): void
    {
        $this->executionTime = microtime(true) - $this->startTime;
        $this->peakMemory = memory_get_peak_usage(true);
    }

    /**
     * Get command execution time in seconds
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Get formatted execution time
     */
    public function getFormattedExecutionTime(): string
    {
        $time = $this->getExecutionTime();

        if ($time < 1) {
            return round($time * 1000, 2) . 'ms';
        }

        return round($time, 2) . 's';
    }

    /**
     * Get memory usage information
     */
    public function getMemoryUsage(): array
    {
        return [
            'start_memory' => $this->formatBytes($this->startMemory),
            'current_memory' => $this->formatBytes(memory_get_usage(true)),
            'peak_memory' => $this->formatBytes($this->peakMemory),
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Format bytes to human readable format.
     */
    public function formatBytes(int $bytes): string
    {
        return FileSize::format(max($bytes, 0));
    }

    /**
     * Return the performance summary
     */
    public function getPerformanceSummary(string $commandName, array $metadata = []): array
    {
        return [
            'command' => $commandName,
            'execution_time' => $this->getFormattedExecutionTime(),
            'memory_usage' => $this->getMemoryUsage(),
            'metadata' => $metadata,
            'timestamp' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Reset all performance metrics
     */
    public function reset(): void
    {
        $this->executionTime = 0.0;
        $this->startTime = 0.0;
        $this->startMemory = 0;
        $this->peakMemory = 0;
    }
}

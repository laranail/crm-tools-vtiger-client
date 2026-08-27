<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database\Contracts;

use Exception;
use Illuminate\Console\OutputStyle;

/**
 * Interface SeederConsoleFormatterInterface
 *
 * Defines contract for seeder console output formatting.
 */
interface SeederConsoleFormatterInterface
{
    /**
     * Initialize a new seeding session
     */
    public function initializeSession(): void;

    /**
     * Display group header with seeder count
     *
     * @param string $groupName Group name
     * @param int $seederCount Number of seeders in group
     * @param bool $isLast Whether this is the last group
     */
    public function displayGroupHeader(string $groupName, int $seederCount, bool $isLast = false): void;

    /**
     * Display seeder start status (running)
     *
     * @param string $seederClass Full seeder class name
     * @param bool $isLast Whether this is the last seeder
     */
    public function displaySeederStart(string $seederClass, bool $isLast = false): void;

    /**
     * Display seeder success status
     *
     * @param string $seederClass Full seeder class name
     * @param float $duration Execution duration in seconds
     * @param bool $isLast Whether this is the last seeder
     */
    public function displaySeederSuccess(string $seederClass, float $duration, bool $isLast = false): void;

    /**
     * Display seeder error status
     *
     * @param string $seederClass Full seeder class name
     * @param Exception $exception The exception that occurred
     * @param float $duration Execution duration in seconds
     * @param bool $isLast Whether this is the last seeder
     */
    public function displaySeederError(string $seederClass, Exception $exception, float $duration, bool $isLast = false): void;

    /**
     * Display seeder skipped status
     *
     * @param string $seederClass Full seeder class name
     * @param string $reason Reason for skipping
     * @param bool $isLast Whether this is the last seeder
     */
    public function displaySeederSkipped(string $seederClass, string $reason, bool $isLast = false): void;

    /**
     * Display final summary statistics
     */
    public function displaySummary(): void;

    /**
     * Write info message
     *
     * @param string $message Message to display
     */
    public function writeInfo(string $message): void;

    /**
     * Write discovery message
     *
     * @param string $message Message to display
     */
    public function writeDiscovery(string $message): void;

    /**
     * Write success message
     *
     * @param string $message Message to display
     */
    public function writeSuccess(string $message): void;

    /**
     * Write warning message
     *
     * @param string $message Message to display
     */
    public function writeWarning(string $message): void;

    /**
     * Write error message
     *
     * @param string $message Message to display
     */
    public function writeError(string $message): void;

    /**
     * Reset statistics to initial state
     */
    public function resetStatistics(): void;

    /**
     * Set the output instance for writing
     *
     * @param OutputStyle|null $output Output instance
     */
    public function setOutput(?OutputStyle $output): void;

    /**
     * Get current statistics
     *
     * @return array Statistics array
     */
    public function getStatistics(): array;

    /**
     * Get session duration
     *
     * @return float Duration in seconds
     */
    public function getSessionDuration(): float;
}

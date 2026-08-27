<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Exception;
use Illuminate\Console\OutputStyle;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;

/**
 * Seeder output with no `laranail/console` dependency.
 *
 * `SeederConsoleFormatter` renders through laranail/console's capability-aware symbols and colours,
 * and is the better experience wherever that package is installed. It is also the ONLY thing in
 * package-tools that touches laranail/console -- one file out of roughly 270 -- and making the whole
 * toolkit require a console library for it means every consumer of a package built on
 * `PackageServiceProvider` installs one too, whether or not it ever seeds anything.
 *
 * So the dependency is a suggestion, and this is what runs without it: the same contract, the same
 * bookkeeping, plain lines instead of styled ones.
 */
final class PlainSeederConsoleFormatter implements SeederConsoleFormatterInterface
{
    private ?OutputStyle $output = null;

    /** @var array<string, int> */
    private array $statistics = [];

    private float $startTime = 0.0;

    public function __construct()
    {
        $this->resetStatistics();
    }

    public function initializeSession(): void
    {
        $this->resetStatistics();
        $this->startTime = microtime(true);
    }

    public function displayGroupHeader(string $groupName, int $seederCount, bool $isLast = false): void
    {
        $this->statistics['groups'] = ($this->statistics['groups'] ?? 0) + 1;
        $this->line(sprintf('%s (%d seeder%s)', $groupName, $seederCount, $seederCount === 1 ? '' : 's'));
    }

    public function displaySeederStart(string $seederClass, bool $isLast = false): void
    {
        $this->line(sprintf('  running  %s', $seederClass));
    }

    public function displaySeederSuccess(string $seederClass, float $duration, bool $isLast = false): void
    {
        $this->statistics['successful'] = ($this->statistics['successful'] ?? 0) + 1;
        $this->line(sprintf('  done     %s (%.2fs)', $seederClass, $duration));
    }

    public function displaySeederError(string $seederClass, Exception $exception, float $duration, bool $isLast = false): void
    {
        $this->statistics['failed'] = ($this->statistics['failed'] ?? 0) + 1;
        $this->line(sprintf('  failed   %s (%.2fs): %s', $seederClass, $duration, $exception->getMessage()));
    }

    public function displaySeederSkipped(string $seederClass, string $reason, bool $isLast = false): void
    {
        $this->statistics['skipped'] = ($this->statistics['skipped'] ?? 0) + 1;
        $this->line(sprintf('  skipped  %s: %s', $seederClass, $reason));
    }

    public function displaySummary(): void
    {
        $this->line(sprintf(
            '%d group(s), %d successful, %d failed, %d skipped in %.2fs',
            $this->statistics['groups'] ?? 0,
            $this->statistics['successful'] ?? 0,
            $this->statistics['failed'] ?? 0,
            $this->statistics['skipped'] ?? 0,
            $this->getSessionDuration(),
        ));
    }

    public function writeInfo(string $message): void
    {
        $this->line($message);
    }

    public function writeDiscovery(string $message): void
    {
        $this->line($message);
    }

    public function writeSuccess(string $message): void
    {
        $this->line($message);
    }

    public function writeWarning(string $message): void
    {
        $this->line($message);
    }

    public function writeError(string $message): void
    {
        $this->line($message);
    }

    public function resetStatistics(): void
    {
        $this->statistics = [
            'groups' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];
    }

    public function setOutput(?OutputStyle $output): void
    {
        $this->output = $output;
    }

    /**
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function getSessionDuration(): float
    {
        return $this->startTime === 0.0 ? 0.0 : microtime(true) - $this->startTime;
    }

    /**
     * Silently discards when no output is set, matching the styled formatter: a seeder run driven
     * from a job or a test has nowhere to write, and that is not an error.
     */
    private function line(string $message): void
    {
        $this->output?->writeln($message);
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Commands;

use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Adds progress bars to commands.
 *
 * @api Consumer-facing extension trait — `use`-d by external command classes.
 */
trait HasProgressBar
{
    protected ?ProgressBar $progressBar = null;

    /**
     * Start a progress bar
     *
     * @param int $steps Total number of steps
     * @param string|null $format Custom format string
     */
    public function startProgress(int $steps, ?string $format = null): void
    {
        $this->progressBar = $this->output->createProgressBar($steps);

        $format ??= ' %current%/%max% [%bar%] %percent:3s%% %message%';
        $this->progressBar->setFormat($format);

        $this->progressBar->setBarCharacter('<comment>=</comment>');
        $this->progressBar->setEmptyBarCharacter('<fg=gray>-</>');
        $this->progressBar->setProgressCharacter('<comment>></comment>');
        $this->progressBar->setBarWidth(50);

        $this->progressBar->start();
    }

    /**
     * Advance the progress bar
     *
     * @param int $step Number of steps to advance
     */
    public function advanceProgress(int $step = 1): void
    {
        $this->progressBar?->advance($step);
    }

    /**
     * Set the current progress
     *
     * @param int $step Current step number
     */
    public function setProgress(int $step): void
    {
        $this->progressBar?->setProgress($step);
    }

    /**
     * Set the progress message
     */
    public function setProgressMessage(string $message): void
    {
        $this->progressBar?->setMessage($message);
    }

    /**
     * Finish the progress bar
     *
     * @param string|null $message Optional completion message
     */
    public function finishProgress(?string $message = null): void
    {
        $this->progressBar?->finish();
        $this->output->newLine(2);

        if ($message) {
            $this->info($message);
        }

        $this->progressBar = null;
    }

    /**
     * Clear the progress bar
     */
    public function clearProgress(): void
    {
        $this->progressBar?->clear();
        $this->progressBar = null;
    }

    /**
     * Create a simple progress bar with a task
     *
     * @param array $items Items to process
     * @param callable $callback Callback to process each item
     * @param string $message Progress message template (use {item} placeholder)
     * @return array Results from callbacks
     */
    public function withProgress(array $items, callable $callback, string $message = 'Processing {item}...'): array
    {
        $this->startProgress(count($items));
        $results = [];

        foreach ($items as $key => $item) {
            $currentMessage = str_replace('{item}', (string) $item, $message);
            $this->setProgressMessage($currentMessage);

            $results[$key] = $callback($item, $key);

            $this->advanceProgress();
        }

        $this->finishProgress();

        return $results;
    }
}

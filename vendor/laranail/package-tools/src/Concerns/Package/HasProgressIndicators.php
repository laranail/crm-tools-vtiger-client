<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Utility\ProgressIndicator;

/**
 * Shows CLI progress bars during package operations.
 */
trait HasProgressIndicators
{
    protected ?ProgressIndicator $progressService = null;

    /**
     * Start progress indicator
     *
     * @param int $total Total steps
     * @param string $message Initial message
     */
    public function startProgress(int $total, string $message = 'Processing...'): static
    {
        $this->getProgressService()->start($total, $message);

        return $this;
    }

    /**
     * Advance progress
     *
     * @param int $step Steps to advance
     */
    public function advanceProgress(int $step = 1): static
    {
        $this->getProgressService()->advance($step);

        return $this;
    }

    /**
     * Set progress message
     *
     * @param string $message Message to display
     */
    public function setProgressMessage(string $message): static
    {
        $this->getProgressService()->setMessage($message);

        return $this;
    }

    /**
     * Finish progress indicator
     */
    public function finishProgress(): static
    {
        $this->getProgressService()->finish();

        return $this;
    }

    /**
     * Show spinner with message
     *
     * @param string $message Message to display
     */
    public function showSpinner(string $message): static
    {
        $this->getProgressService()->spin($message);

        return $this;
    }

    /**
     * Execute callback with progress tracking
     *
     * @param array<array-key, mixed> $items Items to process
     * @param callable $callback Callback to execute for each item
     * @param string $message Progress message
     */
    public function withProgress(array $items, callable $callback, string $message = 'Processing...'): static
    {
        $this->startProgress(count($items), $message);

        foreach ($items as $item) {
            $callback($item);
            $this->advanceProgress();
        }

        $this->finishProgress();

        return $this;
    }

    /**
     * Get or create progress service instance
     */
    protected function getProgressService(): ProgressIndicator
    {
        if (! $this->progressService) {
            $this->progressService = app(ProgressIndicator::class);
        }

        return $this->progressService;
    }
}

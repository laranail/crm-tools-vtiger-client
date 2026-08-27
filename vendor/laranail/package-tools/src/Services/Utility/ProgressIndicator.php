<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Utility;

use Symfony\Component\Console\Helper\ProgressBar as SymfonyProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Progress bars and indicators for CLI operations.
 */
class ProgressIndicator
{
    protected ?SymfonyProgressBar $progressBar = null;

    protected ConsoleOutput $output;

    public function __construct()
    {
        $this->output = new ConsoleOutput;
    }

    /**
     * Start a progress bar
     *
     * @param int $total Total steps
     * @param string $message Initial message
     */
    public function start(int $total, string $message = 'Processing...'): void
    {
        $this->progressBar = new SymfonyProgressBar($this->output, $total);
        $this->progressBar->setMessage($message);
        $this->progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $this->progressBar->start();
    }

    /**
     * Advance progress bar
     *
     * @param int $step Steps to advance
     */
    public function advance(int $step = 1): void
    {
        if ($this->progressBar instanceof SymfonyProgressBar) {
            $this->progressBar->advance($step);
        }
    }

    /**
     * Set progress message
     *
     * @param string $message Message to display
     */
    public function setMessage(string $message): void
    {
        if ($this->progressBar instanceof SymfonyProgressBar) {
            $this->progressBar->setMessage($message);
        }
    }

    /**
     * Finish progress bar
     */
    public function finish(): void
    {
        if ($this->progressBar instanceof SymfonyProgressBar) {
            $this->progressBar->finish();
            $this->output->writeln('');
            $this->progressBar = null;
        }
    }

    /**
     * Show a simple spinner
     *
     * @param string $message Message to display
     */
    public function spin(string $message): void
    {
        $this->output->write("\r" . $message . ' ');
    }

    /**
     * Clear current line
     */
    public function clear(): void
    {
        $this->output->write("\r\033[K");
    }
}

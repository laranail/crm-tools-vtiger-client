<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

use Simtabi\Laranail\Console\Tools\Support\FileSize;
use Simtabi\Laranail\Console\Tools\Widgets\ProgressBar as ProgressBarWidget;
use Simtabi\Laranail\Console\Tools\Widgets\StatusLine;
use Simtabi\Laranail\Console\Tools\Widgets\Table as TableWidget;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command Display Service
 *
 * Command-scoped output convenience that renders through the shared widget layer
 * (StatusLine / Table / ProgressBar), so styling stays consistent across the
 * toolkit rather than being re-implemented here.
 */
class CommandDisplayService
{
    private readonly StatusLine $status;

    public function __construct(protected OutputInterface $output)
    {
        $this->status = StatusLine::make();
    }

    /**
     * Display a success status message.
     */
    public function success(string $message): void
    {
        $this->output->writeln($this->status->success($message));
    }

    /**
     * Display a warning status message.
     */
    public function warning(string $message): void
    {
        $this->output->writeln($this->status->warning($message));
    }

    /**
     * Display an error status message.
     */
    public function error(string $message): void
    {
        $this->output->writeln($this->status->error($message));
    }

    /**
     * Display an info status message.
     */
    public function info(string $message): void
    {
        $this->output->writeln($this->status->info($message));
    }

    /**
     * Show a progress bar (the flavoured widget) for long operations.
     */
    public function showProgressBar(int $total, string $title = 'Processing'): ProgressBar
    {
        $bar = ProgressBarWidget::make($this->output, $total)->raw();
        $bar->setMessage($title);

        return $bar;
    }

    /**
     * Display a table with data via the Table widget.
     *
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function displayTable(array $headers, array $rows): void
    {
        TableWidget::make()->headers($headers)->rows($rows)->render($this->output);
    }

    /**
     * Format bytes to human readable format.
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        return FileSize::format($bytes, $precision);
    }

    /**
     * Display a separator line.
     */
    public function separator(string $char = '=', int $length = 50): void
    {
        $this->output->writeln(str_repeat($char, $length));
    }

    /**
     * Display a header with styling.
     */
    public function header(string $title, string $char = '='): void
    {
        $this->separator($char);
        $this->output->writeln("<comment>{$title}</comment>");
        $this->separator($char);
    }

    /**
     * Display a list of items.
     *
     * @param list<string> $items
     */
    public function list(array $items, string $title = ''): void
    {
        if ($title !== '' && $title !== '0') {
            $this->output->writeln("<comment>{$title}</comment>");
        }

        foreach ($items as $item) {
            $this->output->writeln("  • {$item}");
        }
    }

    /**
     * Display key-value pairs.
     *
     * @param array<string, string> $data
     */
    public function keyValue(array $data, string $title = ''): void
    {
        if ($title !== '' && $title !== '0') {
            $this->output->writeln("<comment>{$title}</comment>");
        }

        foreach ($data as $key => $value) {
            $this->output->writeln("  <info>{$key}:</info> {$value}");
        }
    }
}

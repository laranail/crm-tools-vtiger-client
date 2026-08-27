<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Utility;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Formatted console output helpers.
 */
class ConsoleHelper
{
    protected ConsoleOutput $output;

    public function __construct()
    {
        $this->output = new ConsoleOutput;
    }

    /**
     * Display a table
     *
     * @param array<string> $headers Table headers
     * @param array<int, array<int, string>> $rows Table rows
     */
    public function table(array $headers, array $rows): void
    {
        $table = new Table($this->output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->render();
    }

    /**
     * Display info message
     *
     * @param string $message Message to display
     */
    public function info(string $message): void
    {
        $this->output->writeln("<info>{$message}</info>");
    }

    /**
     * Display error message
     *
     * @param string $message Message to display
     */
    public function error(string $message): void
    {
        $this->output->writeln("<error>{$message}</error>");
    }

    /**
     * Display warning message
     *
     * @param string $message Message to display
     */
    public function warning(string $message): void
    {
        $this->output->writeln("<comment>{$message}</comment>");
    }

    /**
     * Display success message
     *
     * @param string $message Message to display
     */
    public function success(string $message): void
    {
        $this->output->writeln("<fg=green>{$message}</>");
    }

    /**
     * Display a section header
     *
     * @param string $title Section title
     */
    public function section(string $title): void
    {
        $this->output->writeln('');
        $this->output->writeln("<fg=yellow;options=bold>{$title}</>");
        $this->output->writeln(str_repeat('=', strlen($title)));
    }

    /**
     * Display a list
     *
     * @param array<string> $items List items
     * @param string $bullet Bullet character
     */
    public function list(array $items, string $bullet = '•'): void
    {
        foreach ($items as $item) {
            $this->output->writeln("  {$bullet} {$item}");
        }
    }

    /**
     * Display a key-value pair
     *
     * @param string $key Key
     * @param mixed $value Value
     */
    public function keyValue(string $key, mixed $value): void
    {
        $this->output->writeln("<info>{$key}:</info> {$value}");
    }

    /**
     * Display a blank line
     *
     * @param int $count Number of blank lines
     */
    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->output->writeln('');
        }
    }
}

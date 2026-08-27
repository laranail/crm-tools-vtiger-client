<?php

declare(strict_types=1);

use Simtabi\Laranail\Console\Prompter\Prompter;
use Simtabi\Laranail\Console\Tools\Services\ConsoleWriter;
use Simtabi\Laranail\Console\Tools\Support\ConsoleWriterFactory;
use Symfony\Component\Console\Output\OutputInterface;

if (! function_exists('prompter')) {
    /**
     * Get a fresh Prompter instance (isolated $result per call).
     */
    function prompter(): Prompter
    {
        return Prompter::create();
    }
}

if (! function_exists('console_writer')) {
    /**
     * Get a fluent ConsoleWriter (styling, context statuses, emoji) for the
     * given output (defaults to a fresh ConsoleOutput).
     */
    function console_writer(?OutputInterface $output = null): ConsoleWriter
    {
        return app(ConsoleWriterFactory::class)->for($output);
    }
}

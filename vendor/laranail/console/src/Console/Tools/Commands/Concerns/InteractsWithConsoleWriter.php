<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Concerns;

use Illuminate\Console\Command;
use Simtabi\Laranail\Console\Tools\Services\ConsoleWriter;
use Simtabi\Laranail\Console\Tools\Support\ConsoleWriterFactory;

/**
 * Gives any Illuminate command a fluent {@see ConsoleWriter} bound to its own
 * output (styling, context statuses, emoji).
 *
 * @mixin Command
 *
 * @api
 */
trait InteractsWithConsoleWriter
{
    protected function consoleWriter(): ConsoleWriter
    {
        return app(ConsoleWriterFactory::class)->for($this->getOutput());
    }
}

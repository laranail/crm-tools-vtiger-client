<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Small helpers for writing styled output to a Symfony console stream.
 *
 * Folded into {@see Helper} — call via the
 * `Helper::` facade, never the trait directly.
 */
trait InteractsWithConsole
{
    /**
     * Write one or more lines to the console wrapped in a Symfony style tag
     * (e.g. `info`, `comment`, `error`). Each line is emitted as
     * `<style>line</style>`.
     */
    public static function write(OutputInterface $output, string $style, string ...$lines): void
    {
        foreach ($lines as $line) {
            $output->writeln(sprintf('<%s>%s</%s>', $style, $line, $style));
        }
    }
}

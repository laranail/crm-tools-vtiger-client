<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Progress;

/**
 * Renderer-agnostic seam for reporting the progress of a multi-step operation.
 *
 * Implementations render however they like (Laravel Prompts by default, the
 * experimental symfony/tui full-screen renderer when opted in and installed),
 * but the calling code stays identical — resolve {@see ProgressReporter} from the
 * container and call {@see run()}.
 */
interface ProgressReporter
{
    /**
     * Run each step while reporting progress.
     *
     * Mirrors the shape of `Laravel\Prompts\progress()`: each step is passed to
     * `$callback`, whose (optional) string return is shown as that step's label.
     *
     * @param iterable<mixed> $steps
     * @param callable(mixed): (string|null) $callback
     */
    public function run(string $label, iterable $steps, callable $callback): void;
}

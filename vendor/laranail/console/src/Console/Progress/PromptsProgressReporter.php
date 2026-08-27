<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Progress;

use function Laravel\Prompts\progress;

/**
 * Default progress reporter backed by laravel/prompts. Works everywhere, including
 * non-interactive sessions and CI (Prompts degrades gracefully).
 */
final class PromptsProgressReporter implements ProgressReporter
{
    public function run(string $label, iterable $steps, callable $callback): void
    {
        progress(
            label: $label,
            steps: $steps,
            callback: static fn (mixed $step): mixed => $callback($step),
        );
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

/**
 * Dispatched after an individual seeder runs successfully.
 */
final readonly class SeederExecuted
{
    /**
     * @param float $durationMs Execution time in milliseconds.
     */
    public function __construct(
        public string $seederClass,
        public float $durationMs,
        public ?string $package = null,
    ) {}
}

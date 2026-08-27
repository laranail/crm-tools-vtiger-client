<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

/**
 * Dispatched immediately before an individual seeder runs.
 */
final readonly class SeederExecuting
{
    public function __construct(
        public string $seederClass,
        public ?string $package = null,
    ) {}
}

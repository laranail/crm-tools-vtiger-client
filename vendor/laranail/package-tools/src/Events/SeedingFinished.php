<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

/**
 * Dispatched once after every registered package seeder has run
 * (regardless of individual seeder success or failure).
 */
final readonly class SeedingFinished
{
    /**
     * @param list<string> $packages Distinct package namespaces that were seeded.
     * @param int $successCount Number of seeders that ran without throwing.
     * @param int $failureCount Number of seeders that threw.
     */
    public function __construct(
        public array $packages,
        public int $successCount,
        public int $failureCount,
    ) {}
}

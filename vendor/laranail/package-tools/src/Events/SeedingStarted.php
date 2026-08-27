<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

/**
 * Dispatched once before any registered package seeder runs.
 */
final readonly class SeedingStarted
{
    /**
     * @param list<string> $packages Distinct package namespaces being seeded.
     */
    public function __construct(public array $packages) {}
}

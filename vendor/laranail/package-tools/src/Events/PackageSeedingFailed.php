<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;

/**
 * Dispatched for each seeder that throws inside a bundle (bundle
 * execution continues unless stopOnFailure() was set). Pairs with the
 * bundle-level PackageSeedingCompleted whose stats aggregate the run.
 */
final readonly class PackageSeedingFailed
{
    public function __construct(
        public string $packageName,
        public string $bundleKey,
        public string $seederClass,
        public string $exceptionClass,
        public string $message,
        public SeederExecutionMode $mode,
    ) {}
}

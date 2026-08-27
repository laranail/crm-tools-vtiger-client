<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;

/**
 * Dispatched when one package's seeder bundle starts executing — for
 * every mode (inline, queued, scheduled). The package never notifies
 * users itself; attach a listener and send whatever Notification/mail/
 * broadcast fits your app.
 */
final readonly class PackageSeedingStarted
{
    public function __construct(
        public string $packageName,
        public string $bundleKey,
        public int $seederCount,
        public SeederExecutionMode $mode,
    ) {}
}

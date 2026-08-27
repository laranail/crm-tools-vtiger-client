<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;

/**
 * Dispatched when one package's seeder bundle finishes (with or without
 * individual failures — inspect the stats). The canonical "notify the
 * user when done" hook: attach a listener and send your own
 * Notification/mail/Slack/broadcast.
 */
final readonly class PackageSeedingCompleted
{
    public function __construct(
        public string $packageName,
        public string $bundleKey,
        public SeederExecutionStats $stats,
        public float $durationMs,
        public SeederExecutionMode $mode,
    ) {}
}

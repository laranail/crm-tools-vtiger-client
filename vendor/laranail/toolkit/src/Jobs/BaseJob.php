<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Simtabi\Laranail\Toolkit\Services\LogService;
use Throwable;

/**
 * Reusable queued-job base.
 *
 * Wires the standard queue traits and sensible retry/back-off/timeout defaults,
 * and logs any final failure through {@see LogService::exception()}. Subclasses
 * implement their own `handle()`; they may override the public retry properties.
 */
abstract class BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Maximum number of attempts before the job is marked failed. */
    public int $tries = 3;

    /** Seconds to wait between retries. */
    public int $backoff = 10;

    /** Seconds the job may run before timing out. */
    public int $timeout = 120;

    /**
     * Handle a job that has exhausted its retries. Logs the failure with full
     * context; override to add bespoke cleanup (then call `parent::failed()`).
     */
    public function failed(Throwable $e): void
    {
        app(LogService::class)->exception($e);
    }
}

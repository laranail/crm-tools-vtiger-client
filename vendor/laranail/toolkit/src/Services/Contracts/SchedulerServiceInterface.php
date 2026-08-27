<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Simtabi\Laranail\Toolkit\Services\SchedulerService;

/**
 * Public surface of the toolkit's {@see SchedulerService}.
 *
 * Inspects the application scheduler — summarising registered events and
 * detecting overdue tasks. Bound interface→{@see SchedulerService}.
 */
interface SchedulerServiceInterface
{
    /**
     * Get a summary of the scheduled tasks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getScheduleSummary(): array;

    /**
     * Whether any scheduled task is due to run now.
     */
    public function hasOverdueTasks(): bool;
}

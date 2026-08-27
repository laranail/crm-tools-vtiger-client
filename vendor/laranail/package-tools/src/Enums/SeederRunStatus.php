<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

/**
 * Lifecycle of one tracked seeder-bundle run (the SeederRunTracker's
 * cache payload stores the backed value and hydrates back to a case).
 */
enum SeederRunStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

/**
 * How a seeder bundle execution was initiated — carried by the
 * PackageSeeding* events and the run tracker so hosts can react
 * differently to a queued run than an inline one.
 */
enum SeederExecutionMode: string
{
    case Inline = 'inline';
    case Queued = 'queued';
    case Scheduled = 'scheduled';
}

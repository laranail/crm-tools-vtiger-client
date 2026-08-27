<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Enums;

enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
}

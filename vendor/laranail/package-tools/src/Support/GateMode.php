<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

/**
 * internal to ConfigGate — how the config value is judged.
 */
enum GateMode: string
{
    case Truthy = 'truthy';
    case NotNull = 'not_null';
}

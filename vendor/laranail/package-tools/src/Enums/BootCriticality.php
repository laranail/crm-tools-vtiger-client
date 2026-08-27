<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

/**
 * The consequence class of a failure, per the failure-handling standard.
 * Classification is by the state left behind if execution continues — never
 * by environment.
 *
 *  - {@see self::Critical}: continuing leaves an unsafe, incorrect, or
 *    insecure state. Fail fast (throw), the same in every environment.
 *  - {@see self::Degradable}: continuing leaves a safe, reduced state. Report
 *    through the central handler, then continue.
 *
 * Unclassified failures default to Critical (fail closed).
 */
enum BootCriticality
{
    case Critical;
    case Degradable;
}

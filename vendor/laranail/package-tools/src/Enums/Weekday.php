<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

/**
 * cron day-of-week values (sunday = 0, matching cron and laravel). weekday
 * numbering is exactly where an enum prevents bugs; calendar months stay
 * plain ints because 1-12 is universally unambiguous.
 */
enum Weekday: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
}

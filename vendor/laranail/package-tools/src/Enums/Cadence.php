<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

/**
 * every standard scheduler frequency as a typed case. values are the
 * scheduler Event method names, so the dispatch pipeline consumes them
 * directly and config strings resolve via tryFrom() before any parsing.
 */
enum Cadence: string
{
    case EverySecond = 'everySecond';
    case EveryTwoSeconds = 'everyTwoSeconds';
    case EveryFiveSeconds = 'everyFiveSeconds';
    case EveryTenSeconds = 'everyTenSeconds';
    case EveryFifteenSeconds = 'everyFifteenSeconds';
    case EveryTwentySeconds = 'everyTwentySeconds';
    case EveryThirtySeconds = 'everyThirtySeconds';
    case EveryMinute = 'everyMinute';
    case EveryTwoMinutes = 'everyTwoMinutes';
    case EveryThreeMinutes = 'everyThreeMinutes';
    case EveryFourMinutes = 'everyFourMinutes';
    case EveryFiveMinutes = 'everyFiveMinutes';
    case EveryTenMinutes = 'everyTenMinutes';
    case EveryFifteenMinutes = 'everyFifteenMinutes';
    case EveryThirtyMinutes = 'everyThirtyMinutes';
    case Hourly = 'hourly';
    case EveryOddHour = 'everyOddHour';
    case EveryTwoHours = 'everyTwoHours';
    case EveryThreeHours = 'everyThreeHours';
    case EveryFourHours = 'everyFourHours';
    case EverySixHours = 'everySixHours';
    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Weekends = 'weekends';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
}

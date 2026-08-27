<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Carbon\Carbon;
use Carbon\Month;
use Carbon\WeekDay;
use DateTimeInterface;
use Simtabi\Laranail\Toolkit\Helpers\Helper;

/**
 * Carbon date-formatting helpers.
 *
 * Folded into {@see Helper} — call via the
 * `Helper::` facade, never the trait directly.
 */
trait InteractsWithDates
{
    /**
     * Parse any Carbon-parseable value and format it with the given pattern.
     *
     * @param DateTimeInterface|WeekDay|Month|string|int|float|null $date   a Carbon-parseable value
     * @param string                                                $format a `date()`-style format string
     */
    public static function carbonParse($date, $format = 'Y-m-d H:i:s'): ?string
    {
        return Carbon::parse($date)->format($format);
    }

    /**
     * A human-readable relative difference (e.g. "3 days ago").
     *
     * @param DateTimeInterface|WeekDay|Month|string|int|float|null $date a Carbon-parseable value
     */
    public static function carbonHumanDiff($date): string
    {
        return Carbon::parse($date)->diffForHumans();
    }
}

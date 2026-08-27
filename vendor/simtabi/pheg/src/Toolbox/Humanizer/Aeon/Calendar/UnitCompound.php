<?php

declare(strict_types=1);

/*
 * This file is part of the PHP Humanizer Library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Simtabi\Pheg\Toolbox\Humanizer\Aeon\Calendar;

use Aeon\Calendar\RelativeTimeUnit;
use Aeon\Calendar\TimeUnit;
use Aeon\Calendar\Unit;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\DateIntervalCompound;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Difference\CompoundResult;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Day;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Hour;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Minute;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Month;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Second;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Year;

final class UnitCompound
{
    private Unit $unit;

    public function __construct(Unit $timeUnit)
    {
        $this->unit = $timeUnit;
    }

    /**
     * @return array<CompoundResult>
     */
    public function components() : array
    {
        $unit = $this->unit;
        $compoundResults = [];

        if ($unit instanceof RelativeTimeUnit) {
            if ($unit->inYears()) {
                $compoundResults[] = new CompoundResult(new Year(), $unit->inYears());
            }

            if ($unit->inCalendarMonths()) {
                $compoundResults[] = new CompoundResult(new Month(), $unit->inCalendarMonths());
            }

            return (new DateIntervalCompound($unit->toDateInterval()))->components();
        }

        if ($unit instanceof TimeUnit) {
            if ($unit->inDaysAbs() > 0) {
                $compoundResults[] = new CompoundResult(new Day(), $unit->inDaysAbs());
            }

            if ($unit->inTimeHours()) {
                $compoundResults[] = new CompoundResult(new Hour(), $unit->inTimeHours());
            }

            if ($unit->inTimeMinutes()) {
                $compoundResults[] = new CompoundResult(new Minute(), $unit->inTimeMinutes());
            }

            if ($unit->inTimeSeconds() || $unit->inTimeMilliseconds()) {
                $seconds = $unit->inTimeSeconds();

                if ($unit->inTimeMilliseconds() > 0) {
                    $seconds += $unit->inTimeMilliseconds() / 1000;
                }

                $compoundResults[] = new CompoundResult(new Second(), $seconds);
            } elseif (!\count($compoundResults)) {
                $compoundResults[] = new CompoundResult(new Second(), 0);
            }

            return $compoundResults;
        }

        throw new \RuntimeException('Unsupported unit type ' . \get_class($unit));
    }
}

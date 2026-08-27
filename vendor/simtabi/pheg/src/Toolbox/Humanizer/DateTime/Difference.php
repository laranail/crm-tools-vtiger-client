<?php

declare(strict_types=1);

/*
 * This file is part of the PHP Humanizer Library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Simtabi\Pheg\Toolbox\Humanizer\DateTime;

use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Day;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Hour;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\JustNow;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Minute;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Month;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Second;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Week;
use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Unit\Year;

final class Difference
{
    private \DateTimeInterface $fromDate;

    private \DateTimeInterface $toDate;

    /**
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private Unit $unit;

    private ?int $quantity = null;

    public function __construct(\DateTimeInterface $fromDate, \DateTimeInterface $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->calculate();
    }

    public function getUnit() : Unit
    {
        return $this->unit;
    }

    public function getQuantity() : ?int
    {
        return $this->quantity;
    }

    public function isPast() : bool
    {
        $diff = $this->toDate->getTimestamp() - $this->fromDate->getTimestamp();

        return ($diff > 0) ? false : true;
    }

    private function calculate() : void
    {
        /* @var $units Unit[] */
        $units = [
            new Year(),
            new Month(),
            new Week(),
            new Day(),
            new Hour(),
            new Minute(),
            new Second(),
            new JustNow(),
        ];

        $absoluteMilliSecondsDiff = \abs($this->toDate->getTimestamp() - $this->fromDate->getTimestamp()) * 1000;

        foreach ($units as $unit) {
            if ($absoluteMilliSecondsDiff >= $unit->getMilliseconds()) {
                $this->unit = $unit;

                break;
            }
        }

        $this->quantity = ($absoluteMilliSecondsDiff == 0)
            ? $absoluteMilliSecondsDiff
            : (int) \round($absoluteMilliSecondsDiff / $this->unit->getMilliseconds());
    }
}

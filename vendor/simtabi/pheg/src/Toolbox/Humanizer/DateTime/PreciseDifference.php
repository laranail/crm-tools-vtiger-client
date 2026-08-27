<?php

declare(strict_types=1);

/*
 * This file is part of the PHP Humanizer Library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Simtabi\Pheg\Toolbox\Humanizer\DateTime;

use Simtabi\Pheg\Toolbox\Humanizer\DateTime\Difference\CompoundResult;

final class PreciseDifference
{
    private \DateTimeInterface $fromDate;

    private \DateTimeInterface $toDate;

    private ?DateIntervalCompound $compoundResults;

    public function __construct(\DateTimeInterface $fromDate, \DateTimeInterface $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->compoundResults = null;
    }

    /**
     * @return array<CompoundResult>
     */
    public function components() : array
    {
        if ($this->compoundResults === null) {
            $this->compoundResults = new DateIntervalCompound($this->fromDate->diff($this->toDate));
        }

        return $this->compoundResults->components();
    }

    public function isPast() : bool
    {
        $diff = $this->toDate->getTimestamp() - $this->fromDate->getTimestamp();

        return ($diff > 0) ? false : true;
    }
}

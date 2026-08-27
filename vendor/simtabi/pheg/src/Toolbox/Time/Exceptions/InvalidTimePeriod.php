<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Time\Exceptions;

use DateTimeInterface;
use Exception;

class InvalidTimePeriod extends Exception
{
    public static function startDateTimeCannotBeAfterEndDateTime(DateTimeInterface $startDateTime, DateTimeInterface $endDateTime): static
    {
        return new static("Start date `{$startDateTime->format('Y-m-d')}` cannot be after end date `{$endDateTime->format('Y-m-d')}`.");
    }
}
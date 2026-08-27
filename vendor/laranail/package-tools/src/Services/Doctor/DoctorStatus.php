<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor;

enum DoctorStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
    case Skip = 'skip';

    public function symbol(): string
    {
        return match ($this) {
            self::Pass => '✓',
            self::Warn => '!',
            self::Fail => '✗',
            self::Skip => '·',
        };
    }

    public function ansiColor(): string
    {
        return match ($this) {
            self::Pass => "\033[32m",  // green
            self::Warn => "\033[33m",  // yellow
            self::Fail => "\033[31m",  // red
            self::Skip => "\033[90m",  // grey
        };
    }
}

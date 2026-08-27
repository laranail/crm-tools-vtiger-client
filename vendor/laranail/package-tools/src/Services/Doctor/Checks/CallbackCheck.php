<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Closure;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Escape hatch: wrap an arbitrary closure that returns a DoctorResult.
 */
final readonly class CallbackCheck implements DoctorCheck
{
    /** @param Closure(): DoctorResult $run */
    public function __construct(
        private string $name,
        private string $description,
        private Closure $run,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function run(): DoctorResult
    {
        return ($this->run)();
    }
}

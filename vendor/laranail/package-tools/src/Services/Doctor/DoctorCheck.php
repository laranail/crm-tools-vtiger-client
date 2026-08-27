<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor;

/**
 * Contract for a single health-check unit.
 *
 * Concrete checks are registered via Package::hasDoctorCheck(). At
 * `php artisan laranail::package-tools.doctor` time, the DoctorService runs every
 * registered check and emits a coloured TTY report.
 */
interface DoctorCheck
{
    /**
     * Short label shown in the report (e.g. "config:published").
     */
    public function name(): string;

    /**
     * One-line human description of what is being checked.
     */
    public function description(): string;

    /**
     * Run the check. Return a DoctorResult; never throw.
     */
    public function run(): DoctorResult;
}

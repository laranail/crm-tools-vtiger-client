<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;

/**
 * Adds `Package::hasDoctorCheck()`, a fluent step to register a health
 * check. The DoctorService collects all registered checks; the
 * `php artisan laranail::package-tools.doctor` command runs them.
 */
trait HasDoctorChecks
{
    /** @var list<class-string<DoctorCheck>|DoctorCheck> */
    protected array $doctorChecks = [];

    /**
     * Register a doctor check by class-string or instance.
     *
     * @param class-string<DoctorCheck>|DoctorCheck $check
     */
    public function hasDoctorCheck(string|DoctorCheck $check): static
    {
        $this->doctorChecks[] = $check;

        return $this;
    }

    /**
     * Register several doctor checks at once.
     *
     * @param iterable<class-string<DoctorCheck>|DoctorCheck> $checks
     */
    public function hasDoctorChecks(iterable $checks): static
    {
        foreach ($checks as $check) {
            $this->hasDoctorCheck($check);
        }

        return $this;
    }

    /** @return list<class-string<DoctorCheck>|DoctorCheck> */
    public function getDoctorChecks(): array
    {
        return $this->doctorChecks;
    }
}

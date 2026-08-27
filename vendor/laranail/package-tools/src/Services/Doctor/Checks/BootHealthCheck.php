<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Surfaces the observable degraded-boot state (rule 7): fails the doctor
 * report when any degradable boot builder failed and was continued past, so
 * `laranail::package-tools.doctor` (and a CI gate over it) catches the
 * degradation that a fail-fast crash would otherwise have to catch. Reports
 * redacted names/types only (rules 11/15).
 */
final readonly class BootHealthCheck implements DoctorCheck
{
    public function __construct(private BootReport $report) {}

    public function name(): string
    {
        return 'boot:health';
    }

    public function description(): string
    {
        return 'No package boot builders degraded during boot';
    }

    public function run(): DoctorResult
    {
        if ($this->report->isHealthy()) {
            return DoctorResult::pass('All boot builders healthy');
        }

        $degraded = $this->report->degraded();

        $detail = [];
        foreach ($degraded as $builder => $info) {
            $detail[$builder] = $info['criticality'] . ' / ' . ($info['cause_type'] ?? 'unknown');
        }

        return DoctorResult::fail(
            count($degraded) . ' boot builder(s) degraded: ' . implode(', ', array_keys($degraded)),
            $detail,
        );
    }
}

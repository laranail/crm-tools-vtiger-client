<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor;

use InvalidArgumentException;
use Throwable;

/**
 * Runs DoctorCheck instances and produces a structured report.
 *
 * Used by `php artisan laranail::package-tools.doctor`. Checks are typically registered via
 * Package::hasDoctorCheck() in a package's service provider.
 */
final class DoctorService
{
    /** @var list<DoctorCheck> */
    private array $checks = [];

    /** @var array<int, string|null> group per check, index-aligned */
    private array $groups = [];

    /**
     * Register a check (FQCN or instance).
     *
     * @param DoctorCheck|class-string<DoctorCheck> $check
     */
    public function register(DoctorCheck|string $check, ?string $group = null): self
    {
        if (is_string($check)) {
            if (! class_exists($check)) {
                throw new InvalidArgumentException(
                    "DoctorService: class does not exist: {$check}",
                );
            }
            $instance = new $check;
            if (! $instance instanceof DoctorCheck) {
                throw new InvalidArgumentException(
                    "DoctorService: {$check} does not implement DoctorCheck",
                );
            }
            $check = $instance;
        }

        // re-registering the same (group, name) replaces instead of
        // duplicating report rows (double boots were silently stacking)
        foreach ($this->checks as $index => $existing) {
            if ($existing->name() === $check->name() && ($this->groups[$index] ?? null) === $group) {
                $this->checks[$index] = $check;

                return $this;
            }
        }

        $this->checks[] = $check;
        $this->groups[count($this->checks) - 1] = $group;

        return $this;
    }

    /**
     * Run every registered check. Order is registration order.
     *
     * @return list<array{check: DoctorCheck, result: DoctorResult, group: string|null}>
     */
    public function run(): array
    {
        $report = [];

        foreach ($this->checks as $index => $check) {
            try {
                $result = $check->run();
            } catch (Throwable $e) {
                $result = DoctorResult::fail(
                    sprintf('Check threw: %s', $e->getMessage()),
                    ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()],
                );
            }

            $report[] = ['check' => $check, 'result' => $result, 'group' => $this->groups[$index] ?? null];
        }

        return $report;
    }

    /**
     * @param list<array{check: DoctorCheck, result: DoctorResult, group: string|null}> $report
     * @return array{pass: int, warn: int, fail: int, skip: int}
     */
    public function summarise(array $report): array
    {
        $counts = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'skip' => 0];

        foreach ($report as $row) {
            $counts[$row['result']->status->value]++;
        }

        return $counts;
    }

    /** @return list<DoctorCheck> */
    public function getChecks(): array
    {
        return $this->checks;
    }

    public function reset(): self
    {
        $this->checks = [];
        $this->groups = [];

        return $this;
    }
}

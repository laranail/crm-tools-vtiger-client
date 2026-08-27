<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Closure;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Throwable;

/**
 * Runs a boolean reachability probe. A probe that throws (or an unreachable
 * result) is a WARN, never a FAIL — connectivity is not a config error.
 */
final readonly class ReachabilityCheck implements DoctorCheck
{
    /** @param Closure(): bool $probe */
    public function __construct(
        private Closure $probe,
        private string $name,
        private ?string $description = null,
        private string $target = 'Target',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description ?? "{$this->target} is reachable";
    }

    public function run(): DoctorResult
    {
        try {
            $reachable = (bool) ($this->probe)();
        } catch (Throwable $e) {
            return DoctorResult::warn("Could not determine {$this->target} reachability.", ['error' => $e->getMessage()]);
        }

        return $reachable
            ? DoctorResult::pass("{$this->target} reachable.")
            : DoctorResult::warn("{$this->target} unreachable.");
    }
}

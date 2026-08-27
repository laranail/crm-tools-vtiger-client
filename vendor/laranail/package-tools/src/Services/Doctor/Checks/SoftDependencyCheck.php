<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Asserts an optional dependency class is present. Absent → fail (required) or
 * warn (optional).
 */
final readonly class SoftDependencyCheck implements DoctorCheck
{
    public function __construct(
        private string $class,
        private string $label,
        private bool $required = true,
        private ?string $name = null,
        private ?string $description = null,
    ) {}

    public function name(): string
    {
        return $this->name ?? 'dependency:' . $this->label;
    }

    public function description(): string
    {
        return $this->description ?? "{$this->label} is installed";
    }

    public function run(): DoctorResult
    {
        if (class_exists($this->class)) {
            return DoctorResult::pass("{$this->label} is installed.");
        }

        return $this->required
            ? DoctorResult::fail("{$this->label} is required but not installed.")
            : DoctorResult::warn("{$this->label} is not installed.");
    }
}

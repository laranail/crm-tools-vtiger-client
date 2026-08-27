<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Asserts the runtime PHP version meets a minimum.
 */
final readonly class PhpVersionCheck implements DoctorCheck
{
    public function __construct(
        private string $minVersion,
        private ?string $name = null,
        private ?string $description = null,
    ) {}

    public function name(): string
    {
        return $this->name ?? 'php:version';
    }

    public function description(): string
    {
        return $this->description ?? "PHP {$this->minVersion} or higher is installed";
    }

    public function run(): DoctorResult
    {
        return version_compare(PHP_VERSION, $this->minVersion, '>=')
            ? DoctorResult::pass('PHP ' . PHP_VERSION . ' >= ' . $this->minVersion . '.')
            : DoctorResult::fail("PHP {$this->minVersion}+ required (running " . PHP_VERSION . ').');
    }
}

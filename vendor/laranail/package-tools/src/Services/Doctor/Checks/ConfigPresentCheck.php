<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Asserts config keys are set. Missing keys fail (required) or warn (optional).
 */
final readonly class ConfigPresentCheck implements DoctorCheck
{
    /** @param array<string, string>|list<string> $keys label => config-key (or a list of config keys) */
    public function __construct(
        private array $keys,
        private bool $required = true,
        private ?string $name = null,
        private ?string $description = null,
    ) {}

    public function name(): string
    {
        return $this->name ?? 'config:present';
    }

    public function description(): string
    {
        return $this->description ?? 'Required configuration is set';
    }

    public function run(): DoctorResult
    {
        $missing = [];

        foreach ($this->keys as $label => $configKey) {
            $value = config($configKey);

            if ($value === null || $value === '') {
                $missing[] = is_string($label) ? $label : $configKey;
            }
        }

        if ($missing === []) {
            return DoctorResult::pass('Configured.');
        }

        return $this->required
            ? DoctorResult::fail('Missing required config: ' . implode(', ', $missing), ['missing' => $missing])
            : DoctorResult::warn('Missing config: ' . implode(', ', $missing), ['missing' => $missing]);
    }
}

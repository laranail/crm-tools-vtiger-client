<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Asserts one or more PHP extensions are loaded.
 */
final readonly class PhpExtensionCheck implements DoctorCheck
{
    /** @param string|list<string> $extensions */
    public function __construct(
        private string|array $extensions,
        private ?string $name = null,
        private ?string $description = null,
    ) {}

    public function name(): string
    {
        return $this->name ?? 'php:extensions';
    }

    public function description(): string
    {
        return $this->description ?? 'Required PHP extensions are loaded';
    }

    public function run(): DoctorResult
    {
        $exts = is_string($this->extensions) ? [$this->extensions] : $this->extensions;
        $missing = array_values(array_filter($exts, static fn (string $e): bool => ! extension_loaded($e)));

        return $missing === []
            ? DoctorResult::pass(implode(', ', $exts) . ' loaded.')
            : DoctorResult::fail('Missing PHP extension(s): ' . implode(', ', $missing), ['missing' => $missing]);
    }
}

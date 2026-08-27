<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

/**
 * Contract for services that validate inputs and configurations.
 */
interface ValidatorInterface
{
    /**
     * Validate input and return array of errors (empty array if valid)
     *
     * @param mixed $input Value to validate
     * @return array<string> Array of validation errors
     */
    public function validate(mixed $input): array;

    /**
     * Check if input is valid
     *
     * @param mixed $input Value to validate
     */
    public function isValid(mixed $input): bool;
}

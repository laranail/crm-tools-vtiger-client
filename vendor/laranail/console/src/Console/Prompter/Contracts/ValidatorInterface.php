<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Contracts;

/**
 * Interface ValidatorInterface
 *
 * Represents a validator for form fields.
 */
interface ValidatorInterface
{
    /**
     * Validate the given value.
     *
     * @param mixed $value The value to validate.
     * @return string|null The error message if validation fails, or null if validation passes.
     */
    public function validate(mixed $value): ?string;
}

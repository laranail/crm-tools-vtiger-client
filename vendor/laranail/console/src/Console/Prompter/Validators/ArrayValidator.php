<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class ArrayValidator
 *
 * Validates array fields.
 */
final class ArrayValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('array');
    }

    public function validate(mixed $value): ?string
    {
        return is_array($value) ? null : $this->resolvedMessage();
    }
}

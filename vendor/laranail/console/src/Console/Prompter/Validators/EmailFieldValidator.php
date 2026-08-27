<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class EmailFieldValidator
 *
 * Validates email fields.
 */
final class EmailFieldValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('email');
    }

    public function validate(mixed $value): ?string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : $this->resolvedMessage();
    }
}

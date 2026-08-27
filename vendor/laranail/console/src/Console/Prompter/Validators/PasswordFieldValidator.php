<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class PasswordFieldValidator
 *
 * Validates password fields.
 */
final class PasswordFieldValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('password');
    }

    public function validate(mixed $value): ?string
    {
        return is_string($value) && mb_strlen($value) >= 8 ? null : $this->resolvedMessage();
    }
}

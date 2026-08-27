<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class NullOrEmptyValidator
 *
 * Validates null or empty fields.
 */
final class NullOrEmptyValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('null_or_empty');
    }

    public function validate(mixed $value): ?string
    {
        return is_null($value) || $value === '' ? null : $this->resolvedMessage();
    }
}

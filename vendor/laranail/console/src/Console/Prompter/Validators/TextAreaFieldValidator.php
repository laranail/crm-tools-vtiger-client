<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class TextAreaFieldValidator
 *
 * Validates textarea fields.
 */
final class TextAreaFieldValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('textarea');
    }

    public function validate(mixed $value): ?string
    {
        return is_string($value) ? null : $this->resolvedMessage();
    }
}

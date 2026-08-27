<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class TextFieldValidator
 *
 * Validates text fields.
 */
final class TextFieldValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('text');
    }

    public function validate(mixed $value): ?string
    {
        return is_string($value) && mb_strlen($value) <= 255 ? null : $this->resolvedMessage();
    }
}

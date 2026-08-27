<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class NumberFieldValidator
 *
 * Validates number fields.
 */
final class NumberFieldValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('number');
    }

    public function validate(mixed $value): ?string
    {
        return is_numeric($value) ? null : $this->resolvedMessage();
    }
}

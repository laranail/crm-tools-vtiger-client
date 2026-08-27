<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class CheckboxFieldValidator
 *
 * Validates checkbox fields.
 */
final class CheckboxFieldValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('checkbox');
    }

    public function validate(mixed $value): ?string
    {
        return is_bool($value) ? null : $this->resolvedMessage();
    }
}

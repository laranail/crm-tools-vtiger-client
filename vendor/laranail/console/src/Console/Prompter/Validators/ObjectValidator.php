<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class ObjectValidator
 *
 * Validates object fields.
 */
final class ObjectValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('object');
    }

    public function validate(mixed $value): ?string
    {
        return is_object($value) ? null : $this->resolvedMessage();
    }
}

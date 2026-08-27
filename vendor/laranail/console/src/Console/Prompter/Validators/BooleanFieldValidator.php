<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class BooleanFieldValidator
 *
 * Validates boolean fields including various string representations.
 */
final class BooleanFieldValidator extends AbstractValidator
{
    protected array $validBooleans = [
        true,
        false,
        'true',
        'false',
        'yes',
        'no',
        '1',
        '0',
        1,
        0,
    ];

    public function __construct()
    {
        parent::__construct('boolean');
    }

    public function validate(mixed $value): ?string
    {
        return in_array($value, $this->validBooleans, true) ? null : $this->resolvedMessage();
    }
}

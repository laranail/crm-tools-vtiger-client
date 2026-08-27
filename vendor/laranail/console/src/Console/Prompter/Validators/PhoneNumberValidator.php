<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class PhoneNumberValidator
 *
 * Validates phone number fields.
 */
final class PhoneNumberValidator extends RegexValidator
{
    public function __construct()
    {
        parent::__construct('/^\+?\d{10,15}$/', 'phone');
    }
}

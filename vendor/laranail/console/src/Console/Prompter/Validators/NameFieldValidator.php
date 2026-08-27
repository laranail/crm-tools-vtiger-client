<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class NameFieldValidator
 *
 * Validates name fields (full name / first and/or last name).
 */
final class NameFieldValidator extends RegexValidator
{
    public function __construct()
    {
        parent::__construct('/^[\p{L} \'-]+$/u', 'name');
    }
}

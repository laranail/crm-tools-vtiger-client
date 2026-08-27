<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class AlphaValidator
 *
 * Validates fields that should contain only alphabetic characters.
 */
final class AlphaValidator extends RegexValidator
{
    public function __construct()
    {
        parent::__construct('/^[a-zA-Z]+$/', 'alpha');
    }
}

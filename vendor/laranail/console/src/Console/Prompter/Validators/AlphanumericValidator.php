<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class AlphanumericValidator
 *
 * Validates fields that should contain only alphanumeric characters.
 */
final class AlphanumericValidator extends RegexValidator
{
    public function __construct()
    {
        parent::__construct('/^[a-zA-Z0-9]+$/', 'alphanumeric');
    }
}

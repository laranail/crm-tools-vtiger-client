<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class UsernameValidator
 *
 * Validates username fields.
 */
final class UsernameValidator extends RegexValidator
{
    public function __construct()
    {
        parent::__construct('/^\w{3,20}$/', 'username');
    }
}

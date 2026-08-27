<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Validates RFC 4122 UUID fields (any version 1–5).
 */
final class UUIDFieldValidator extends RegexValidator
{
    public function __construct()
    {
        parent::__construct(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
            'uuid',
        );
    }
}

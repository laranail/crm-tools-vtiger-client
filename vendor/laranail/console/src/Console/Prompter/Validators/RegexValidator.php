<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Base for validators that accept a string matching a single regex pattern.
 * Subclasses pass their pattern + default message key; the predicate (and the
 * "non-string fails, never throws" totality) lives here once.
 */
abstract class RegexValidator extends AbstractValidator
{
    public function __construct(private readonly string $pattern, string $messageKey = '')
    {
        parent::__construct($messageKey);
    }

    public function validate(mixed $value): ?string
    {
        return is_string($value) && preg_match($this->pattern, $value) === 1 ? null : $this->resolvedMessage();
    }
}

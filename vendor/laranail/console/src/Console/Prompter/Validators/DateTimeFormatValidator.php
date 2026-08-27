<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

use DateTimeImmutable;

/**
 * Shared base for validators that accept a value parseable by at least one of a
 * set of explicit, unambiguous date/time formats. Subclasses supply the format
 * list and the message key.
 */
abstract class DateTimeFormatValidator extends AbstractValidator
{
    /** @var list<string> */
    protected array $formats;

    public function validate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return $this->resolvedMessage();
        }

        foreach ($this->formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return null;
            }
        }

        return $this->resolvedMessage();
    }
}

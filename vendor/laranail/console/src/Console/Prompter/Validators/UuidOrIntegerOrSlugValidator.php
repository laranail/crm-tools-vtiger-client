<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

use Illuminate\Support\Str;

/**
 * Class UuidOrIntegerOrSlugValidator
 *
 * Validates that the input is either a UUID, an integer ID, or a slug.
 */
final class UuidOrIntegerOrSlugValidator extends AbstractValidator
{
    public function __construct(protected string $uuidVersion = 'uuid')
    {
        parent::__construct('uuid_or_integer_or_slug');
    }

    public function validate(mixed $value): ?string
    {
        if ($this->isUuid($value) || $this->isInteger($value) || $this->isSlug($value)) {
            return null;
        }

        return $this->resolvedMessage();
    }

    /**
     * Check if the value is a valid UUID.
     */
    private function isUuid(mixed $value): bool
    {
        if (! is_string($value) || ! Str::isUuid($value)) {
            return false;
        }

        $version = match ($this->uuidVersion) {
            'uuid1' => '1',
            'uuid3' => '3',
            'uuid4' => '4',
            'uuid5' => '5',
            default => null,   // any UUID version
        };

        return $version === null
            || (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-' . $version . '[0-9a-fA-F]{3}-[89ab][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $value);
    }

    /**
     * Check if the value is a valid integer.
     */
    private function isInteger(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false);
    }

    /**
     * Check if the value is a valid slug.
     */
    private function isSlug(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
    }
}

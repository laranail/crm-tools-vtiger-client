<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Validates that a value is a well-formed filesystem path.
 *
 * Validates the path's *shape* only — it never touches the filesystem, so it
 * cannot be used to probe which paths exist. Null bytes and parent-directory
 * traversal segments are rejected.
 */
final class PathFieldValidator extends AbstractValidator
{
    public function __construct()
    {
        parent::__construct('path');
    }

    public function validate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return $this->resolvedMessage();
        }

        if (str_contains($value, "\0")) {
            return $this->resolvedMessage();
        }

        $segments = preg_split('#[/\\\\]#', $value) ?: [];

        if (in_array('..', $segments, true)) {
            return $this->resolvedMessage();
        }

        return null;
    }
}

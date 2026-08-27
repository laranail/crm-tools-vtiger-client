<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Helpers;

/**
 * Internal input-sanitisation helpers for the Prompter.
 *
 * @internal Not part of the public API.
 */
class Helpers
{
    /**
     * Trim non-empty input, otherwise return the default.
     *
     * Note: only whitespace-or-null input falls through to the default; the
     * literal string "0" is treated as a valid value, not as empty.
     */
    public static function sanitizeInput(?string $input, ?string $default = ''): ?string
    {
        return $input !== null && trim($input) !== '' ? trim($input) : $default;
    }
}

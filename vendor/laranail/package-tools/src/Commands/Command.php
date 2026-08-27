<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Illuminate\Console\Command as BaseCommand;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Base Artisan command for laranail packages.
 *
 * Extends Laravel's command and accepts the `laranail::package-tools.<command>`
 * namespace separator (and plain `:`) via {@see SupportsNamespacedNames}. Extend
 * this, or `use` the trait on an existing command, to opt in.
 */
abstract class Command extends BaseCommand
{
    use SupportsNamespacedNames;

    /**
     * Read a single-value console option and coerce it to a string.
     *
     * Symfony's option accessor is typed as a broad
     * `array|bool|string|null` union; for the scalar (`=`) options these
     * commands declare it is always a string or null. Array values (only
     * possible for repeatable options) collapse to their first element so
     * the result is never an "Array to string conversion".
     */
    protected function stringOption(string $key, string $default = ''): string
    {
        $value = $this->option($key);

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if ($value === null || is_bool($value)) {
            return $default;
        }

        return (string) $value;
    }
}

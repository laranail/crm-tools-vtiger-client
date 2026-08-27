<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

/**
 * Loads helper functions during package registration. Files load once to
 * avoid redeclaration errors.
 */
trait LoadsHelpers
{
    /**
     * Load the .php files from the package's helpers/ directory if present.
     */
    protected function loadPackageHelpers(): static
    {
        $this->package->loadHelpers();

        return $this;
    }
}

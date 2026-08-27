<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;

/**
 * Loads all PHP files from the package's helpers/ directory, if present.
 * Helper files hold global function definitions and load when the package
 * is registered, no configuration needed.
 */
trait HasHelpers
{
    /** @var bool Whether helpers have been loaded */
    protected bool $helpersLoaded = false;

    /**
     * Require every .php file in the helpers/ directory once.
     */
    public function loadHelpers(): static
    {
        if ($this->helpersLoaded) {
            return $this;
        }

        $helpersPath = $this->basePath(self::HELPERS_DIR);

        if (! File::isDirectory($helpersPath)) {
            return $this;
        }

        $helperFiles = File::glob($helpersPath . '/*.php');

        foreach ($helperFiles as $helperFile) {
            if (File::isFile($helperFile)) {
                File::requireOnce($helperFile);
            }
        }

        $this->helpersLoaded = true;

        return $this;
    }

    /**
     * Check if helpers directory exists
     */
    public function hasHelpersDirectory(): bool
    {
        $helpersPath = $this->basePath(self::HELPERS_DIR);

        return File::isDirectory($helpersPath);
    }

    /**
     * Get list of helper files
     *
     * @return list<string>
     */
    public function getHelperFiles(): array
    {
        $helpersPath = $this->basePath(self::HELPERS_DIR);

        if (! File::isDirectory($helpersPath)) {
            return [];
        }

        /** @var list<string> $files */
        $files = File::glob($helpersPath . '/*.php');

        return $files;
    }
}

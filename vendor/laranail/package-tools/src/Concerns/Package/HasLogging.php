<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Simtabi\Laranail\Package\Tools\Support\Definitions\LogDefinition;

/**
 * Per-package logging: `$package->log()->info(...)` writes beautifully
 * formatted lines to a logfile named after the package. Works during
 * configurePackage() ("early logging") and at app runtime alike.
 */
trait HasLogging
{
    protected ?LogDefinition $logDefinition = null;

    private ?PackageLogger $packageLogger = null;

    private bool $bufferEarlyLogs = false;

    /**
     * Configure the package's logging defaults (path, rotation, level,
     * format, channel delegation). Host config always overrides these.
     *
     * @example
     * ```php
     * $package->hasLogging(LogDefinition::make()->daily(30)->level('info'));
     * ```
     */
    public function hasLogging(LogDefinition $definition): static
    {
        $this->logDefinition = $definition;

        return $this;
    }

    /**
     * The package's logger — PSR-3 level methods plus success(), each with
     * an optional label to say where the message came from:
     *
     * ```php
     * $package->log()->info('Routes registered');
     * $package->log()->error('CountrySeeder failed', 'Seeder', ['exception' => $e]);
     * $package->log()->success('Migrations published', 'Install', ['count' => 3]);
     * ```
     */
    public function log(): PackageLogger
    {
        if (! $this->packageLogger instanceof PackageLogger) {
            $this->packageLogger = PackageLogger::for($this);

            if ($this->bufferEarlyLogs) {
                $this->packageLogger->bufferUntilReady();
            }
        }

        return $this->packageLogger;
    }

    public function getLogDefinition(): ?LogDefinition
    {
        return $this->logDefinition;
    }

    public function hasInstantiatedLogger(): bool
    {
        return $this->packageLogger instanceof PackageLogger;
    }

    /**
     * Called by the service provider before configurePackage(): records
     * written before the package's config merges are buffered and flushed
     * by markLoggerReady() with their original timestamps.
     */
    public function bufferEarlyLogs(): static
    {
        $this->bufferEarlyLogs = true;
        $this->packageLogger?->bufferUntilReady();

        return $this;
    }

    /**
     * Called by the service provider after the package's config merged:
     * re-resolve settings and flush anything buffered.
     */
    public function markLoggerReady(): static
    {
        $this->bufferEarlyLogs = false;
        $this->packageLogger?->markReady();

        return $this;
    }
}

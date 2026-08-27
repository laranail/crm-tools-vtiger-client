<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Config\ConfigService;

/**
 * Sets, gets, and forgets config values at runtime.
 */
trait HasConfigManipulation
{
    protected ?ConfigService $runtimeConfigService = null;

    /**
     * Set a configuration value
     *
     * @param string $key Config key (supports dot notation)
     * @param mixed $value Value to set
     */
    public function setConfig(string $key, mixed $value): static
    {
        $this->getRuntimeConfigService()->set($key, $value);

        return $this;
    }

    /**
     * Get a configuration value
     *
     * @param string $key Config key (supports dot notation)
     * @param mixed $default Default value if key doesn't exist
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->getRuntimeConfigService()->get($key, $default);
    }

    /**
     * Forget (remove) a configuration value
     *
     * @param string $key Config key (supports dot notation)
     */
    public function forgetConfig(string $key): static
    {
        $this->getRuntimeConfigService()->forget($key);

        return $this;
    }

    /**
     * Check if configuration key exists
     *
     * @param string $key Config key (supports dot notation)
     */
    public function hasConfigKey(string $key): bool
    {
        return $this->getRuntimeConfigService()->has($key);
    }

    /**
     * Set multiple configuration values
     *
     * @param array<string, mixed> $values Key-value pairs
     */
    public function setConfigs(array $values): static
    {
        foreach ($values as $key => $value) {
            $this->setConfig($key, $value);
        }

        return $this;
    }

    /**
     * Get or create runtime config service instance
     */
    protected function getRuntimeConfigService(): ConfigService
    {
        if (! $this->runtimeConfigService) {
            $this->runtimeConfigService = app(ConfigService::class);
        }

        return $this->runtimeConfigService;
    }
}

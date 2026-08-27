<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Config\ConfigService;

/**
 * Merges package config into Laravel's global config array.
 */
trait HasGlobalConfigMerging
{
    protected ?ConfigService $configService = null;

    /**
     * Merge configuration file into global config
     *
     * @param string $path Path to config file
     * @param string $globalKey Global config key to merge into (e.g., 'app', 'database')
     */
    public function mergeConfigGlobal(string $path, string $globalKey): static
    {
        $this->getConfigService()->mergeGlobal($path, $globalKey);

        return $this;
    }

    /**
     * Merge multiple config files into global config
     *
     * @param array<string, string> $configs Array of [path => globalKey]
     */
    public function mergeConfigsGlobal(array $configs): static
    {
        foreach ($configs as $path => $globalKey) {
            $this->mergeConfigGlobal($path, $globalKey);
        }

        return $this;
    }

    /**
     * Get the config service, creating it on first use.
     */
    protected function getConfigService(): ConfigService
    {
        if (! $this->configService) {
            $this->configService = app(ConfigService::class);
        }

        return $this->configService;
    }
}

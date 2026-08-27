<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Simtabi\Laranail\Package\Tools\Services\Package\ComposerService;

/**
 * Runs Composer commands for the package.
 */
trait HasComposerOperations
{
    protected ?ComposerService $composerOperationsService = null;

    /**
     * Run composer install
     *
     * @param bool $dev Include dev dependencies
     * @return array{success: bool, output: string}
     */
    public function composerInstall(bool $dev = true): array
    {
        $composer = $this->getComposerOperationsService();

        $packagePath = $this->packageBasePath();

        return $composer->install($packagePath, $dev);
    }

    /**
     * Run composer update
     *
     * @param string|null $package Specific package to update
     * @return array{success: bool, output: string}
     */
    public function composerUpdate(?string $package = null): array
    {
        $composer = $this->getComposerOperationsService();

        $packagePath = $this->packageBasePath();

        return $composer->update($packagePath, $package);
    }

    /**
     * Require a package
     *
     * @param string $package Package name
     * @param bool $dev Add to require-dev
     * @return array{success: bool, output: string}
     */
    public function composerRequire(string $package, bool $dev = false): array
    {
        $composer = $this->getComposerOperationsService();

        $packagePath = $this->packageBasePath();

        return $composer->require($packagePath, $package, $dev);
    }

    /**
     * Remove a package
     *
     * @param string $package Package name
     * @return array{success: bool, output: string}
     */
    public function composerRemove(string $package): array
    {
        $composer = $this->getComposerOperationsService();

        $packagePath = $this->packageBasePath();

        return $composer->remove($packagePath, $package);
    }

    /**
     * Run composer dump-autoload
     *
     * @param bool $optimize Run with --optimize flag
     * @return array{success: bool, output: string}
     */
    public function composerDumpAutoload(bool $optimize = false): array
    {
        $composer = $this->getComposerOperationsService();

        $packagePath = $this->packageBasePath();

        return $composer->dumpAutoload($packagePath, $optimize);
    }

    /**
     * Validate composer.json
     *
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validateComposerJson(): array
    {
        $composer = $this->getComposerOperationsService();

        $packagePath = $this->packageBasePath();

        return $composer->validate($packagePath);
    }

    /**
     * Get composer.json data
     *
     * @return array<string, mixed>|null
     */
    public function getComposerData(): ?array
    {
        $composer = $this->getComposerOperationsService();

        $packagePath = $this->packageBasePath();

        return $composer->getComposerData($packagePath);
    }

    /**
     * Update composer.json
     *
     * @param array<string, mixed> $data Data to update
     */
    public function updateComposerJson(array $data): bool
    {
        $composer = $this->getComposerOperationsService();

        $packagePath = $this->packageBasePath();

        return $composer->updateComposerJson($packagePath, $data);
    }

    /**
     * Get or create Composer operations service instance
     */
    protected function getComposerOperationsService(): ComposerService
    {
        if (! $this->composerOperationsService) {
            $this->composerOperationsService = app(ComposerService::class);
        }

        return $this->composerOperationsService;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;
}

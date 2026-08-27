<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;

/**
 * Validates paths, directories, and files during package registration so
 * misconfiguration surfaces before runtime.
 */
trait HasEnhancedValidation
{
    /** @var bool Whether to enable strict validation */
    protected bool $strictValidation = false;

    /** @var list<string> List of validation errors */
    protected array $validationErrors = [];

    /**
     * Enable strict validation mode
     *
     * When enabled, validation errors will throw exceptions immediately.
     * When disabled, errors are collected and can be retrieved with getValidationErrors().
     */
    public function withStrictValidation(bool $strict = true): static
    {
        $this->strictValidation = $strict;

        return $this;
    }

    /**
     * Validate package base path exists
     *
     * @throws InvalidPath
     */
    public function validateBasePath(): static
    {
        if (empty($this->basePath)) {
            throw InvalidPath::basePathNotSet();
        }

        if (! File::isDirectory($this->basePath)) {
            throw InvalidPath::basePathDoesNotExist($this->basePath);
        }

        return $this;
    }

    /**
     * Validate config files exist
     *
     * @param bool $throwOnError Whether to throw exception on error
     *
     * @throws InvalidPath
     */
    public function validateConfigFiles(?bool $throwOnError = null): static
    {
        $throwOnError ??= $this->strictValidation;

        foreach ($this->configFileNames as $configFileName) {
            $configPath = $this->basePath(static::CONFIG_DIR . "/{$configFileName}.php");
            $stubPath = $this->basePath(static::CONFIG_DIR . "/{$configFileName}.php.stub");

            if (! File::exists($configPath) && ! File::exists($stubPath)) {
                $error = "Config file '{$configFileName}' not found at: {$configPath}";

                if ($throwOnError) {
                    throw InvalidPath::configFileMissing($configFileName, $configPath);
                }

                $this->validationErrors[] = $error;
            }
        }

        return $this;
    }

    /**
     * Validate migration files exist
     *
     * @param bool $throwOnError Whether to throw exception on error
     *
     * @throws InvalidPath
     */
    public function validateMigrationFiles(?bool $throwOnError = null): static
    {
        $throwOnError ??= $this->strictValidation;

        foreach ($this->migrationFileNames as $migrationFileName) {
            $migrationPath = $this->basePath(static::MIGRATIONS_DIR . "/{$migrationFileName}.php");
            $stubPath = $this->basePath(static::MIGRATIONS_DIR . "/{$migrationFileName}.php.stub");

            if (! File::exists($migrationPath) && ! File::exists($stubPath)) {
                $error = "Migration file '{$migrationFileName}' not found";

                if ($throwOnError) {
                    throw InvalidPath::migrationFileMissing($migrationFileName, $migrationPath);
                }

                $this->validationErrors[] = $error;
            }
        }

        return $this;
    }

    /**
     * Validate view directory exists
     *
     * @param bool $throwOnError Whether to throw exception on error
     *
     * @throws InvalidPath
     */
    public function validateViewDirectory(?bool $throwOnError = null): static
    {
        $throwOnError ??= $this->strictValidation;
        $viewPath = $this->basePath(static::VIEWS_DIR);

        if (! empty($this->viewNamespace) && ! File::isDirectory($viewPath)) {
            $error = "View directory not found at: {$viewPath}";

            if ($throwOnError) {
                throw InvalidPath::viewDirectoryMissing($viewPath);
            }

            $this->validationErrors[] = $error;
        }

        return $this;
    }

    /**
     * Validate routes directory exists
     *
     * @param bool $throwOnError Whether to throw exception on error
     *
     * @throws InvalidPath
     */
    public function validateRouteFiles(?bool $throwOnError = null): static
    {
        $throwOnError ??= $this->strictValidation;

        foreach ($this->routeFileNames as $routeFileName) {
            $routePath = $this->basePath(static::ROUTES_DIR . "/{$routeFileName}.php");

            if (! File::exists($routePath)) {
                $error = "Route file '{$routeFileName}' not found at: {$routePath}";

                if ($throwOnError) {
                    throw InvalidPath::routeFileMissing($routeFileName, $routePath);
                }

                $this->validationErrors[] = $error;
            }
        }

        return $this;
    }

    /**
     * Validate package structure
     *
     * Checks that expected directories exist based on package configuration.
     *
     * @param array<int, string> $expectedDirs Optional list of additional directories to validate
     *
     * @throws InvalidPath
     */
    public function validatePackageStructure(array $expectedDirs = []): static
    {
        $this->validateBasePath();

        $missingItems = [];

        $dirsToCheck = $expectedDirs;

        if (! empty($this->configFileNames)) {
            $dirsToCheck[] = static::CONFIG_DIR;
        }

        if (! empty($this->viewNamespace)) {
            $dirsToCheck[] = static::VIEWS_DIR;
        }

        if (! empty($this->migrationFileNames) || $this->discoversMigrations) {
            $dirsToCheck[] = static::MIGRATIONS_DIR;
        }

        if (! empty($this->routeFileNames)) {
            $dirsToCheck[] = static::ROUTES_DIR;
        }

        foreach (array_unique($dirsToCheck) as $dir) {
            $fullPath = $this->basePath($dir);
            if (! File::isDirectory($fullPath)) {
                $missingItems[] = $dir;
            }
        }

        if ($missingItems !== []) {
            if ($this->strictValidation) {
                throw InvalidPath::invalidPackageStructure($missingItems, $this->basePath);
            }

            $this->validationErrors[] = 'Missing directories: ' . implode(', ', $missingItems);
        }

        return $this;
    }

    /**
     * Validate all configured resources
     *
     * Runs all validation checks based on package configuration.
     *
     * @throws InvalidPath
     */
    public function validateAll(): static
    {
        $this->validateBasePath();

        if (! empty($this->configFileNames)) {
            $this->validateConfigFiles();
        }

        if (! empty($this->migrationFileNames)) {
            $this->validateMigrationFiles();
        }

        if (! empty($this->viewNamespace)) {
            $this->validateViewDirectory();
        }

        if (! empty($this->routeFileNames)) {
            $this->validateRouteFiles();
        }

        return $this;
    }

    /**
     * Check if package has validation errors
     */
    public function hasValidationErrors(): bool
    {
        return ! empty($this->validationErrors);
    }

    /**
     * Get all validation errors
     *
     * @return list<string>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Clear validation errors
     */
    public function clearValidationErrors(): static
    {
        $this->validationErrors = [];

        return $this;
    }

    /**
     * Validate and throw if errors exist
     *
     * @param string|null $customMessage Optional custom message prefix
     *
     * @throws InvalidPath
     */
    public function validateOrFail(?string $customMessage = null): static
    {
        $this->validateAll();

        if ($this->hasValidationErrors()) {
            $message = $customMessage ?? "Package validation failed for '{$this->name}'";
            $errorList = implode("\n  - ", $this->validationErrors);

            throw new InvalidPath(
                "{$message}:\n  - {$errorList}"
            );
        }

        return $this;
    }
}

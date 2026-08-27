<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Exceptions;

use Exception;
use Illuminate\Support\Str;

/**
 * Path validation exceptions with detailed messages for common package
 * setup problems.
 */
class InvalidPath extends Exception
{
    /**
     * Exception when package base path is not set
     */
    public static function basePathNotSet(): static
    {
        return new static(
            'Package base path has not been set. ' .
            'Call $package->setPathFrom($this) or $package->setPathFrom("/path/to/package") before configuring package resources.'
        );
    }

    /**
     * Exception when package base path does not exist
     *
     * @param string $path The invalid path
     */
    public static function basePathDoesNotExist(string $path): static
    {
        return new static(
            "Package base path does not exist: '{$path}'. " .
            'Please verify the path or adjust the levelsUp parameter if using setPathFrom() with file reference.'
        );
    }

    /**
     * Exception when required directory is missing
     *
     * @param string $directory The missing directory (e.g., 'config', 'resources/views')
     * @param string $basePath The package base path
     */
    public static function requiredDirectoryMissing(string $directory, string $basePath): static
    {
        $fullPath = Str::finish($basePath, DIRECTORY_SEPARATOR) . ltrim($directory, DIRECTORY_SEPARATOR);

        return new static(
            "Required directory '{$directory}' not found in package. " .
            "Expected at: '{$fullPath}'. " .
            'Please create the directory or verify your package structure.'
        );
    }

    /**
     * Exception when config file is missing
     *
     * @param string $configFile The missing config file name (e.g., 'myconfig.php')
     * @param string $expectedPath The full expected path
     */
    public static function configFileMissing(string $configFile, string $expectedPath): static
    {
        return new static(
            "Config file '{$configFile}' not found. " .
            "Expected at: '{$expectedPath}'. " .
            'Please create the config file or remove it from your package configuration.'
        );
    }

    /**
     * Exception when a config file exists in listing but cannot be read.
     *
     * @param string $path The unreadable config file path
     */
    public static function configFileNotReadable(string $path): static
    {
        return new static(
            "Config file is missing or not readable: '{$path}'. " .
            'Verify the file exists and its permissions allow reading.'
        );
    }

    /**
     * Exception when a config file does not return an array.
     *
     * @param string $path The offending config file path
     */
    public static function configFileNotArray(string $path): static
    {
        return new static(
            "Config file did not return an array: '{$path}'. " .
            'A config file must `return [ ... ];`.'
        );
    }

    /**
     * Exception when migration file is missing
     *
     * @param string $migrationFile The missing migration file name
     * @param string $expectedPath The full expected path
     */
    public static function migrationFileMissing(string $migrationFile, string $expectedPath): static
    {
        return new static(
            "Migration file '{$migrationFile}' not found. " .
            "Expected at: '{$expectedPath}' or '{$expectedPath}.stub'. " .
            'Please create the migration file or remove it from your package configuration.'
        );
    }

    /**
     * Exception when view directory is missing
     *
     * @param string $viewPath The missing view path
     */
    public static function viewDirectoryMissing(string $viewPath): static
    {
        return new static(
            "View directory not found at: '{$viewPath}'. " .
            "Please create the directory at 'resources/views' in your package root."
        );
    }

    /**
     * Exception when translation directory is missing
     *
     * @param string $translationPath The missing translation path
     */
    public static function translationDirectoryMissing(string $translationPath): static
    {
        return new static(
            "Translation directory not found at: '{$translationPath}'. " .
            "Please create the directory at 'resources/lang' in your package root."
        );
    }

    /**
     * Exception when route file is missing
     *
     * @param string $routeFile The missing route file name (e.g., 'web.php')
     * @param string $expectedPath The full expected path
     */
    public static function routeFileMissing(string $routeFile, string $expectedPath): static
    {
        return new static(
            "Route file '{$routeFile}' not found. " .
            "Expected at: '{$expectedPath}'. " .
            'Please create the route file or remove it from your package configuration.'
        );
    }

    /**
     * Exception when levelsUp value is invalid
     *
     * @param int $levelsUp The invalid levelsUp value
     * @param string $reason Optional reason for invalidity
     */
    public static function invalidLevelsUp(int $levelsUp, string $reason = ''): static
    {
        $message = "Invalid levelsUp value: {$levelsUp}. ";

        if ($levelsUp < 1) {
            $message .= 'Value must be at least 1. ';
        }

        if ($reason !== '' && $reason !== '0') {
            $message .= $reason;
        } else {
            $message .= 'This value determines how many directory levels to traverse from your service provider to find the package root.';
        }

        return new static($message);
    }

    /**
     * Exception when path traversal reaches filesystem root
     *
     * @param string $startPath Where traversal started
     * @param int $levelsUp How many levels were requested
     * @param int $levelsAchieved How many levels were actually traversed
     */
    public static function reachedFilesystemRoot(string $startPath, int $levelsUp, int $levelsAchieved): static
    {
        return new static(
            "Reached filesystem root while traversing from '{$startPath}'. " .
            "Requested to go up {$levelsUp} levels but only achieved {$levelsAchieved} levels. " .
            'Please reduce the levelsUp value. ' .
            "Hint: Count the directories from your service provider file to where 'config/', 'resources/', and 'database/' are located."
        );
    }

    /**
     * Exception when package structure validation fails
     *
     * @param array<int, string> $missingItems List of missing directories/files
     * @param string $basePath The package base path
     */
    public static function invalidPackageStructure(array $missingItems, string $basePath): static
    {
        $itemsList = implode("\n  - ", $missingItems);

        return new static(
            "Package structure validation failed at: '{$basePath}'. " .
            "Missing expected items:\n  - {$itemsList}\n\n" .
            'Please verify your package structure or adjust the levelsUp parameter.'
        );
    }

    /**
     * Exception for general path-related errors with custom message
     *
     * @param string $message Custom error message
     * @param string|null $path Optional path to include in message
     */
    public static function custom(string $message, ?string $path = null): static
    {
        // Always prefix with "Invalid package path:" so messages stay
        // descriptive even when callers pass terse text. Tests assert
        // exception messages are >20 chars; the prefix guarantees that.
        $prefixed = 'Invalid package path: ' . $message;

        if ($path !== null) {
            $prefixed .= " Path: '{$path}'";
        }

        return new static($prefixed);
    }

    /**
     * Exception when path is empty string
     */
    public static function pathIsEmpty(): static
    {
        return new static(
            'Package base path cannot be empty. ' .
            'You must provide a non-empty path using `$package->setPathFrom("/path/to/package")` or `$package->setPathFrom($this)`. ' .
            'The path cannot be an empty string or whitespace only.'
        );
    }

    /**
     * Exception when path is required but not set
     */
    public static function pathIsRequired(): static
    {
        return new static(
            'Package base path is required. ' .
            'Call $package->setPathFrom($this) or $package->setPathFrom("/path/to/package") before configuring package resources.'
        );
    }

    /**
     * Exception when path format is invalid
     *
     * @param string $path The invalid path
     */
    public static function pathIsInvalid(string $path): static
    {
        return new static(
            "Invalid path format: '{$path}'. " .
            'Paths must be non-empty strings and cannot contain only whitespace.'
        );
    }
}

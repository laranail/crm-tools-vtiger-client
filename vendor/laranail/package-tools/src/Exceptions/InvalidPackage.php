<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Exceptions;

use Exception;

class InvalidPackage extends Exception
{
    public static function nameIsRequired(): self
    {
        return new static(
            'A package name is required. This package does not have a name; ' .
            'you can set one with `$package->name("vendor/yourPackage")` ' .
            '(or the equivalent `$package->setName(...)`).'
        );
    }

    public static function nameIsEmpty(): self
    {
        return new static(
            'Package name cannot be empty. ' .
            'You must provide a non-empty name using `$package->setName("yourName")`. ' .
            'The name cannot be an empty string or whitespace only.'
        );
    }

    public static function nameIsInvalid(string $name): self
    {
        return new static(
            "Invalid package name: '{$name}'. " .
            'Package names must contain only alphanumeric characters, dashes (-), slashes (/), and underscores (_). ' .
            'Examples: "vendor/package-name", "acme/widget"'
        );
    }

    /**
     * Package name must be in vendor/package format
     *
     * @param string $name The invalid package name provided
     */
    public static function vendorRequired(string $name): self
    {
        return new static(
            "Package name must be in 'vendor/package' format. " .
            "Provided: '{$name}'. " .
            'BREAKING CHANGE: Legacy single package name format is no longer supported. ' .
            'Please update to vendor/package format (e.g., "acme/widget" instead of "widget"). ' .
            'This ensures proper namespacing and prevents collisions in the ecosystem.'
        );
    }
}

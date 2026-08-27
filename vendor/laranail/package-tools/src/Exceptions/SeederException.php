<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown when a package seeding operation fails.
 */
class SeederException extends Exception
{
    /** @var array<string, mixed> */
    public array $context = [];

    public static function classNotFound(string $seederClass): self
    {
        $e = new self("Seeder class does not exist: {$seederClass}", 4001);
        $e->context = ['seeder' => $seederClass];

        return $e;
    }

    public static function invalidClass(string $seederClass): self
    {
        $e = new self("Class is not a valid seeder: {$seederClass}", 4002);
        $e->context = ['seeder' => $seederClass];

        return $e;
    }

    public static function executionFailed(string $seederClass, Throwable $previous): self
    {
        $e = new self("Seeder execution failed: {$seederClass}", 4003, $previous);
        $e->context = ['seeder' => $seederClass, 'error' => $previous->getMessage()];

        return $e;
    }

    public static function discoveryFailed(string $path, string $reason): self
    {
        $e = new self("Seeder discovery failed at {$path}: {$reason}", 4004);
        $e->context = ['path' => $path, 'reason' => $reason];

        return $e;
    }
}

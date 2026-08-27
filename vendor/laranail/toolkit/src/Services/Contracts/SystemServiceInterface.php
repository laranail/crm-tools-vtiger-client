<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

/**
 * Read-only system / runtime introspection helpers.
 *
 * Every method is side-effect free — no config() mutation, no I/O beyond
 * reading PHP/ini/$_SERVER state. This is the primary, injectable system
 * domain (formerly the static `Helper::*` system helpers); resolve via the
 * container — `app(SystemServiceInterface::class)` / constructor injection.
 */
interface SystemServiceInterface
{
    /**
     * Parse a PHP memory-limit string ("256M", "1G", "512K") into bytes.
     * Returns -1 for the "unlimited" sentinel.
     */
    public function parseMemoryLimit(string $memoryLimit): int;

    /** The configured memory_limit ini string (e.g. "256M", "-1"). */
    public function memoryLimit(): string;

    /**
     * Current and peak memory usage, raw bytes plus human-readable strings.
     *
     * @return array{current: int, peak: int, limit: string, current_formatted: string, peak_formatted: string}
     */
    public function memoryUsage(): array;

    /** The running PHP version string. */
    public function phpVersion(): string;

    /** Whether the running PHP version is >= the given minimum. */
    public function isPhpVersionSupported(string $minimumVersion): bool;

    /** Whether PHP is running under the CLI SAPI. */
    public function isCli(): bool;

    /** Whether the current request is served over HTTPS. */
    public function isHttps(): bool;

    /** Alias of {@see isHttps()} — whether the request is served over TLS/SSL. */
    public function isSslInstalled(): bool;

    /**
     * The application's `composer.json`, decoded to an array. Returns an empty
     * array when the file is missing or unreadable (never throws).
     *
     * @return array<string, mixed>
     */
    public function composer(): array;

    /**
     * The constraint declared for a package in the app's `composer.json`
     * `require` / `require-dev`, or null when it is not a direct dependency.
     */
    public function composerPackageVersion(string $package): ?string;

    /**
     * A consolidated snapshot of the runtime: PHP, OS, SAPI, Laravel version
     * and the current application environment.
     *
     * @return array{php_version: string, os: string, sapi: string, laravel_version: string, env: string}
     */
    public function systemInfo(): array;

    /**
     * A read-only snapshot of server/runtime environment settings.
     *
     * @return array<string, mixed>
     */
    public function serverEnv(): array;
}

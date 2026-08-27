<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\System\Contracts;

/**
 * Contract for the lightweight system inspector exposed by package-tools.
 *
 * Reports details about the PHP runtime, host OS, request-time server
 * variables, and detected `composer.json` for the current Laravel
 * application. Intended for package install commands, doctor checks, and
 * SBOM/diagnostic tooling, not for production hot-paths.
 */
interface SystemServiceInterface
{
    /**
     * Read and decode the application's `composer.json`.
     *
     * @return array<string, mixed> Empty array when the file is missing
     *                              or cannot be parsed.
     */
    public function getComposerArray(): array;

    /**
     * Resolve declared version constraints for the given Composer packages.
     *
     * @param list<string> $packages Package names (e.g. `vendor/name`).
     * @return array<string, array{version: string, type: 'require'|'require-dev'}>
     *                                                                              Keyed by package name. Packages not
     *                                                                              present in composer.json are omitted.
     */
    public function getPackagesAndDependencies(array $packages): array;

    /**
     * High-level PHP + Laravel + request information.
     *
     * @return array<string, mixed>
     */
    public function getSystemEnv(): array;

    /**
     * PHP SAPI + ini settings + disk capacity for the running host.
     *
     * @return array<string, mixed>
     */
    public function getServerEnv(): array;

    /**
     * Detected operating system family.
     *
     * @return 'windows'|'macos'|'linux'|'bsd'|'unknown'
     */
    public function getOsFamily(): string;

    /**
     * Whether the current HTTP request is being served over TLS.
     *
     * Conservative: returns `false` when no `$_SERVER` context exists
     * (CLI). Honours `HTTPS`, `REQUEST_SCHEME`, and the common
     * `HTTP_X_FORWARDED_PROTO` reverse-proxy header.
     */
    public function isSslInstalled(): bool;
}

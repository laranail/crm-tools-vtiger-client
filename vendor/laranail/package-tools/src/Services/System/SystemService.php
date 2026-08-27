<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\System;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\System\Contracts\SystemServiceInterface;

/**
 * Read-only inspector for the host PHP / Laravel / server context.
 *
 * Used by package install commands, doctor checks, and diagnostic
 * tooling. Has no dependencies beyond `illuminate/contracts` and
 * `illuminate/support`, with no shell-outs and no network calls.
 *
 * Composer.json is resolved relative to the Laravel application's base
 * path (`Application::basePath()`). When no application context is
 * available (typically only in unit tests outside Testbench)
 * `getComposerArray()` returns `[]`.
 */
final readonly class SystemService implements SystemServiceInterface
{
    public function __construct(
        private ?Application $app = null,
    ) {}

    public function getComposerArray(): array
    {
        $path = $this->composerPath();
        if ($path === null || ! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getPackagesAndDependencies(array $packages): array
    {
        $composer = $this->getComposerArray();
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $requireDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];

        $result = [];
        foreach ($packages as $package) {
            if (! is_string($package)) {
                continue;
            }
            if ($package === '') {
                continue;
            }
            if (isset($require[$package])) {
                $result[$package] = [
                    'version' => (string) $require[$package],
                    'type' => 'require',
                ];
            } elseif (isset($requireDev[$package])) {
                $result[$package] = [
                    'version' => (string) $requireDev[$package],
                    'type' => 'require-dev',
                ];
            }
        }

        return $result;
    }

    public function getSystemEnv(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_os' => PHP_OS,
            'os_family' => $this->getOsFamily(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? ($this->app?->basePath() ?? ''),
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'server_port' => $_SERVER['SERVER_PORT'] ?? '80',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? '',
            'path_info' => $_SERVER['PATH_INFO'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'laravel_version' => $this->app?->version() ?? 'unknown',
            'environment' => $this->app?->environment() ?? 'unknown',
        ];
    }

    public function getServerEnv(): array
    {
        $basePath = $this->app?->basePath() ?? (getcwd() ?: '.');

        return [
            'ssl_installed' => $this->isSslInstalled(),
            'php_sapi' => PHP_SAPI,
            'php_extensions' => get_loaded_extensions(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => error_reporting(),
            'timezone' => date_default_timezone_get(),
            'disk_free_space' => @disk_free_space($basePath) ?: 0.0,
            'disk_total_space' => @disk_total_space($basePath) ?: 0.0,
        ];
    }

    public function getOsFamily(): string
    {
        return match (true) {
            defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows' => 'windows',
            defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Darwin' => 'macos',
            defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Linux' => 'linux',
            defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'BSD' => 'bsd',
            default => 'unknown',
        };
    }

    public function isSslInstalled(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        $scheme = $_SERVER['REQUEST_SCHEME'] ?? null;
        if (is_string($scheme) && strtolower($scheme) === 'https') {
            return true;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        if (is_string($forwarded) && str_contains(strtolower($forwarded), 'https')) {
            return true;
        }

        $port = $_SERVER['SERVER_PORT'] ?? null;

        return $port !== null && (int) $port === 443;
    }

    private function composerPath(): ?string
    {
        $base = $this->app?->basePath();
        if (is_string($base) && $base !== '') {
            return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';
        }

        return null;
    }
}

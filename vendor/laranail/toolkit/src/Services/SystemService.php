<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;
use Throwable;

/**
 * Read-only system / runtime introspection helpers.
 *
 * Every method is side-effect free — no config() mutation, no I/O beyond
 * reading PHP/ini/$_SERVER state. This is the primary, injectable system
 * domain (formerly the static `Helper::*` system helpers).
 *
 * The human-readable byte formatting in {@see memoryUsage()} delegates to the
 * injected {@see FileServiceInterface} so there is a single byte-formatter
 * implementation (no logic duplication).
 */
final readonly class SystemService implements SystemServiceInterface
{
    public function __construct(
        private FileServiceInterface $files,
    ) {}

    public function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        if ($memoryLimit === '') {
            return -1;
        }

        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;

        // Any negative value (`-1`, `-1G`, …) means unlimited.
        if ($value < 0) {
            return -1;
        }

        return match ($last) {
            'g' => $value * (1024 ** 3),
            'm' => $value * (1024 ** 2),
            'k' => $value * 1024,
            default => $value,
        };
    }

    public function memoryLimit(): string
    {
        $limit = ini_get('memory_limit');

        return $limit === false || $limit === '' ? '-1' : $limit;
    }

    /**
     * Current and peak memory usage, raw bytes plus human-readable strings.
     *
     * @return array{current: int, peak: int, limit: string, current_formatted: string, peak_formatted: string}
     */
    public function memoryUsage(): array
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        return [
            'current' => $current,
            'peak' => $peak,
            'limit' => $this->memoryLimit(),
            'current_formatted' => $this->files->formatFileSize($current),
            'peak_formatted' => $this->files->formatFileSize($peak),
        ];
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function isPhpVersionSupported(string $minimumVersion): bool
    {
        return version_compare(PHP_VERSION, $minimumVersion, '>=');
    }

    public function isCli(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    public function isHttps(): bool
    {
        // Delegate to the framework request so HTTPS behind a TLS-terminating
        // proxy/load balancer is detected (via X-Forwarded-Proto, honoured for
        // configured TrustProxies). Reading $_SERVER['HTTPS'] alone misses that
        // case, and trusting the proxy header directly would be spoofable.
        return request()->isSecure();
    }

    public function isSslInstalled(): bool
    {
        return $this->isHttps();
    }

    /**
     * The application's `composer.json`, decoded to an array. Returns an empty
     * array when the file is missing or unreadable (never throws).
     *
     * @return array<string, mixed>
     */
    public function composer(): array
    {
        try {
            $path = base_path('composer.json');

            if (!File::exists($path)) {
                return [];
            }

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode(File::get($path), true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    public function composerPackageVersion(string $package): ?string
    {
        $composer = $this->composer();

        foreach (['require', 'require-dev'] as $section) {
            $requirements = $composer[$section] ?? null;

            if (is_array($requirements) && isset($requirements[$package]) && is_string($requirements[$package])) {
                return $requirements[$package];
            }
        }

        return null;
    }

    /**
     * A consolidated snapshot of the runtime: PHP, OS, SAPI, Laravel version
     * and the current application environment.
     *
     * @return array{php_version: string, os: string, sapi: string, laravel_version: string, env: string}
     */
    public function systemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
            'sapi' => PHP_SAPI,
            'laravel_version' => app()->version(),
            'env' => app()->environment(),
        ];
    }

    /**
     * A read-only snapshot of server/runtime environment settings.
     *
     * @return array<string, mixed>
     */
    public function serverEnv(): array
    {
        return [
            'https' => $this->isHttps(),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'php_extensions' => get_loaded_extensions(),
            'memory_limit' => $this->memoryLimit(),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => error_reporting(),
            'timezone' => date_default_timezone_get(),
        ];
    }
}

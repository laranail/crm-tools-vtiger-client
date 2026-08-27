<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

/**
 * Thin diagnostics surface for the toolkit's runtime requirements.
 *
 * Exposes the genuinely useful checks from the legacy `RequirementsChecker`:
 * the running PHP version against a floor, presence of required extensions,
 * and writability of directories. The directory-permission probe is the only
 * bespoke logic kept; everything else is a direct read of PHP runtime state.
 *
 * Designed to feed `php artisan about` via {@see toAboutArray()} — it does not
 * reimplement that command.
 */
final class RequirementsDiagnostics
{
    /**
     * The package's minimum supported PHP version (matches composer's `^8.4.1`
     * floor, inherited from the laranail/console dependency).
     */
    public const MINIMUM_PHP_VERSION = '8.4.1';

    /**
     * Extensions the toolkit relies on across its modules.
     *
     * @var list<string>
     */
    public const REQUIRED_EXTENSIONS = ['json', 'mbstring', 'fileinfo'];

    /**
     * Check the running PHP version against the supplied (or default) floor.
     *
     * @return array{current: string, minimum: string, supported: bool}
     */
    public function checkPhpVersion(?string $minimumVersion = null): array
    {
        $minimum = $minimumVersion ?? self::MINIMUM_PHP_VERSION;

        return [
            'current' => PHP_VERSION,
            'minimum' => $minimum,
            'supported' => version_compare(PHP_VERSION, $minimum, '>='),
        ];
    }

    /**
     * Report which of the given extensions are loaded.
     *
     * @param list<string>|null $extensions
     *
     * @return array<string, bool>
     */
    public function checkExtensions(?array $extensions = null): array
    {
        $results = [];

        foreach ($extensions ?? self::REQUIRED_EXTENSIONS as $extension) {
            $results[$extension] = extension_loaded($extension);
        }

        return $results;
    }

    /**
     * Probe whether each of the given directories is writable.
     *
     * This is the bespoke piece retained from the legacy checker: it resolves
     * a path's nearest existing ancestor when the path itself does not yet
     * exist, so "can I create files here" is answered correctly.
     *
     * @param list<string> $paths
     *
     * @return array<string, bool>
     */
    public function checkWritableDirectories(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            $results[$path] = $this->isDirectoryWritable($path);
        }

        return $results;
    }

    /**
     * Determine whether a directory (or the location it would be created in)
     * is writable.
     */
    public function isDirectoryWritable(string $path): bool
    {
        $candidate = $path;

        // Walk up to the nearest existing ancestor so a not-yet-created
        // directory is judged by where it would live.
        while ($candidate !== '' && !file_exists($candidate)) {
            $parent = \dirname($candidate);

            if ($parent === $candidate) {
                break;
            }

            $candidate = $parent;
        }

        return $candidate !== '' && is_dir($candidate) && is_writable($candidate);
    }

    /**
     * Report which of the given extensions are missing (the inverse view of
     * {@see checkExtensions()}), handy for a single "all good?" answer.
     *
     * @param list<string>|null $extensions
     *
     * @return list<string>
     */
    public function missingExtensions(?array $extensions = null): array
    {
        return array_keys(array_filter(
            $this->checkExtensions($extensions),
            static fn (bool $loaded): bool => !$loaded,
        ));
    }

    /**
     * Probe free disk space at the given path against an optional minimum.
     *
     * `disk_total_space` / `disk_free_space` can return false (unreadable path,
     * restricted SAPI); that is reported as `available: false` rather than
     * throwing, so the probe degrades gracefully.
     *
     * @return array{
     *     path: string,
     *     free: int|null,
     *     total: int|null,
     *     minimum: int|null,
     *     available: bool,
     *     sufficient: bool,
     * }
     */
    public function checkDiskSpace(?string $path = null, ?int $minimumBytes = null): array
    {
        $path ??= storage_path();

        $free = is_dir($path) ? disk_free_space($path) : false;
        $total = is_dir($path) ? disk_total_space($path) : false;

        $freeBytes = $free === false ? null : (int) $free;
        $totalBytes = $total === false ? null : (int) $total;
        $available = $freeBytes !== null;

        $sufficient = $available
            && ($minimumBytes === null || $freeBytes >= $minimumBytes);

        return [
            'path' => $path,
            'free' => $freeBytes,
            'total' => $totalBytes,
            'minimum' => $minimumBytes,
            'available' => $available,
            'sufficient' => $sufficient,
        ];
    }

    /**
     * Probe one or more paths for free disk space, judging each against a
     * minimum (hard floor), a recommended target, and a usage warning level.
     *
     * Extends {@see checkDiskSpace()} (single path, raw bytes) with the
     * still-useful policy bits from the legacy `DiskSpaceValidator`: MB-based
     * thresholds, a recommended tier above the minimum, a percent-used warning
     * line, and a fan-out over several paths with an aggregate health bool.
     *
     * `disk_free_space` / `disk_total_space` may return false on an unreadable
     * path or a restricted SAPI; that path is reported as `available: false`
     * and drags `healthy` down rather than throwing.
     *
     * @param list<string> $paths         paths to probe (defaults to the app base path)
     * @param int|null     $minMb         hard minimum free space in MB, or null to skip the floor check
     * @param int|null     $recommendedMb recommended free space in MB, or null to skip the recommendation
     * @param int          $warnAtPercent emit a warning once used space reaches this percentage (0–100)
     *
     * @return array{
     *     healthy: bool,
     *     warn_at_percent: int,
     *     minimum_mb: int|null,
     *     recommended_mb: int|null,
     *     paths: array<string, array{
     *         path: string,
     *         available: bool,
     *         free: int|null,
     *         total: int|null,
     *         used: int|null,
     *         used_percent: float|null,
     *         meets_minimum: bool,
     *         meets_recommended: bool,
     *         warning: bool,
     *         status: string,
     *     }>,
     * }
     */
    public function diskSpace(
        array $paths = [],
        ?int $minMb = null,
        ?int $recommendedMb = null,
        int $warnAtPercent = 90,
    ): array {
        if ($paths === []) {
            $paths = [$this->basePath()];
        }

        if ($warnAtPercent < 0 || $warnAtPercent > 100) {
            throw new \InvalidArgumentException('warnAtPercent must be between 0 and 100.');
        }

        $minimumBytes = $minMb === null ? null : $this->mbToBytes($minMb);
        $recommendedBytes = $recommendedMb === null ? null : $this->mbToBytes($recommendedMb);

        $results = [];
        $healthy = true;

        foreach ($paths as $path) {
            $report = $this->probePath($path, $minimumBytes, $recommendedBytes, $warnAtPercent);
            $results[$path] = $report;

            if (!$report['available'] || !$report['meets_minimum'] || $report['warning']) {
                $healthy = false;
            }
        }

        return [
            'healthy' => $healthy,
            'warn_at_percent' => $warnAtPercent,
            'minimum_mb' => $minMb,
            'recommended_mb' => $recommendedMb,
            'paths' => $results,
        ];
    }

    /**
     * Probe a single path against the supplied byte thresholds.
     *
     * @param int $warnAtPercent the warning line as a percentage (0–100)
     *
     * @return array{
     *     path: string,
     *     available: bool,
     *     free: int|null,
     *     total: int|null,
     *     used: int|null,
     *     used_percent: float|null,
     *     meets_minimum: bool,
     *     meets_recommended: bool,
     *     warning: bool,
     *     status: string,
     * }
     */
    private function probePath(
        string $path,
        ?int $minimumBytes,
        ?int $recommendedBytes,
        int $warnAtPercent,
    ): array {
        $free = is_dir($path) ? disk_free_space($path) : false;
        $total = is_dir($path) ? disk_total_space($path) : false;

        $freeBytes = $free === false ? null : (int) $free;
        $totalBytes = $total === false ? null : (int) $total;
        $available = $freeBytes !== null;

        $usedBytes = null;
        $usedPercent = null;

        if ($freeBytes !== null && $totalBytes !== null) {
            $usedBytes = max(0, $totalBytes - $freeBytes);

            if ($totalBytes > 0) {
                $usedPercent = round(($usedBytes / $totalBytes) * 100, 2);
            }
        }

        $meetsMinimum = $available
            && ($minimumBytes === null || $freeBytes >= $minimumBytes);

        $meetsRecommended = $available
            && ($recommendedBytes === null || $freeBytes >= $recommendedBytes);

        $warning = $usedPercent !== null && $usedPercent >= $warnAtPercent;

        return [
            'path' => $path,
            'available' => $available,
            'free' => $freeBytes,
            'total' => $totalBytes,
            'used' => $usedBytes,
            'used_percent' => $usedPercent,
            'meets_minimum' => $meetsMinimum,
            'meets_recommended' => $meetsRecommended,
            'warning' => $warning,
            'status' => $this->diskStatus($available, $meetsMinimum, $meetsRecommended, $warning),
        ];
    }

    /**
     * Reduce a path's probe flags to a single status label.
     */
    private function diskStatus(
        bool $available,
        bool $meetsMinimum,
        bool $meetsRecommended,
        bool $warning,
    ): string {
        return match (true) {
            !$available => 'unavailable',
            !$meetsMinimum => 'critical',
            $warning => 'warning',
            !$meetsRecommended => 'low',
            default => 'healthy',
        };
    }

    /**
     * Convert a megabyte figure to bytes (binary, 1 MB = 1024^2 bytes).
     */
    private function mbToBytes(int $megabytes): int
    {
        return $megabytes * 1024 ** 2;
    }

    /**
     * Resolve the application base path, falling back to the working directory
     * when the Laravel helper is unavailable.
     */
    private function basePath(): string
    {
        if (function_exists('base_path')) {
            return base_path();
        }

        return (string) getcwd();
    }

    /**
     * Build the array surfaced under `php artisan about`.
     *
     * @return array<string, string>
     */
    public function toAboutArray(): array
    {
        $php = $this->checkPhpVersion();

        $missing = $this->missingExtensions();

        $disk = $this->checkDiskSpace();

        return [
            'PHP Version' => $php['current'],
            'Minimum PHP' => $php['minimum'],
            'PHP Supported' => $php['supported'] ? 'Yes' : 'No',
            'Required Extensions' => $missing === [] ? 'All loaded' : 'Missing: ' . implode(', ', $missing),
            'Storage Writable' => $this->isDirectoryWritable(storage_path()) ? 'Yes' : 'No',
            'Storage Free Space' => $disk['free'] === null ? 'Unknown' : $this->formatBytes($disk['free']),
        ];
    }

    /**
     * Human-readable byte size (binary units).
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / 1024 ** $power, 2) . ' ' . $units[$power];
    }
}

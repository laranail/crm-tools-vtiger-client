<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Human-readable byte sizes (B / KB / MB / GB / TB).
 *
 * The single source of truth for byte formatting across the toolkit. Scales at
 * each 1024 boundary (1024 B → "1 KB", 1 MiB → "1 MB").
 */
final class FileSize
{
    private const array UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    public static function format(int $bytes, int $precision = 2): string
    {
        $value = (float) $bytes;
        $i = 0;

        for (; $value >= 1024 && $i < count(self::UNITS) - 1; $i++) {
            $value /= 1024;
        }

        return round($value, $precision) . ' ' . self::UNITS[$i];
    }
}

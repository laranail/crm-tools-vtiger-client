<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use RuntimeException;

/**
 * Thrown when attempting to read a file that exceeds the configured size limit.
 */
class FileTooLargeException extends RuntimeException
{
    /**
     * Create a new file-too-large exception.
     *
     * @param string $path    The file path.
     * @param int    $size    The actual file size in bytes.
     * @param int    $maxSize The maximum allowed size in bytes.
     */
    public static function create(string $path, int $size, int $maxSize): self
    {
        return new self(sprintf(
            'File "%s" is too large (%s). Maximum allowed size is %s.',
            $path,
            self::formatBytes($size),
            self::formatBytes($maxSize),
        ));
    }

    /**
     * Format a byte count to a human-readable string (e.g. "1.50 MB").
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        if ($bytes <= 0) {
            return '0.00 B';
        }

        $factor = (int) floor(log($bytes, 1024));
        $factor = max(0, min($factor, count($units) - 1));

        return sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor]);
    }
}

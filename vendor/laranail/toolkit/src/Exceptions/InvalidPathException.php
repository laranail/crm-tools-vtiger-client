<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use RuntimeException;

/**
 * Thrown when an invalid or unsafe file path is detected.
 *
 * Covers directory-traversal attempts (`../`), null-byte injection, paths
 * outside an allowed directory, and otherwise invalid characters in a path.
 */
class InvalidPathException extends RuntimeException
{
    /**
     * Create a new invalid-path exception.
     *
     * @param string $path   The offending path.
     * @param string $reason Why the path is invalid.
     */
    public static function create(string $path, string $reason = 'Invalid or unsafe path'): self
    {
        return new self(sprintf('%s: %s', $reason, $path));
    }

    /** Directory-traversal attempt detected. */
    public static function directoryTraversal(string $path): self
    {
        return self::create($path, 'Directory traversal attempt detected');
    }

    /** Path contains null bytes. */
    public static function nullByteDetected(string $path): self
    {
        return self::create($path, 'Null byte detected in path');
    }

    /** Path is outside the allowed directories. */
    public static function outsideAllowedDirectory(string $path): self
    {
        return self::create($path, 'Path is outside allowed directories');
    }

    /** Path contains invalid characters. */
    public static function invalidCharacters(string $path): self
    {
        return self::create($path, 'Path contains invalid characters');
    }
}

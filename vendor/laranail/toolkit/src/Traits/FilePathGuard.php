<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use InvalidArgumentException;

/**
 * Path-safety guard rejecting directory-traversal segments (`..`) and null
 * bytes in arbitrary filesystem paths.
 *
 * This is a thin, dependency-free wrapper — it does not touch the filesystem
 * or re-implement Laravel's Storage abstraction; it only validates the shape
 * of a path string before it is handed to lower-level file APIs.
 */
trait FilePathGuard
{
    /**
     * Determine whether a path is free of traversal segments and null bytes.
     */
    public function isSafePath(string $path): bool
    {
        // Null byte injection terminates C-level path strings early.
        if (str_contains($path, "\0")) {
            return false;
        }

        // Normalise separators, then reject any `..` segment.
        $segments = preg_split('#[\\\\/]+#', $path);

        if ($segments === false) {
            return false;
        }

        return array_all($segments, fn ($segment) => $segment !== '..');
    }

    /**
     * Assert that a path is safe, throwing if it is not.
     *
     * @throws InvalidArgumentException when the path contains a `..` segment or a null byte
     */
    public function assertSafePath(string $path): string
    {
        if (!$this->isSafePath($path)) {
            throw new InvalidArgumentException(
                'Unsafe path rejected: paths may not contain ".." traversal segments or null bytes.'
            );
        }

        return $path;
    }
}

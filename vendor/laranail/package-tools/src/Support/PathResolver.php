<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Support\Path\Path;

/**
 * Cross-platform path resolution for package root detection. Reflects on
 * a service provider's file location, normalizes separators, and walks up
 * the directory tree by a given number of levels. Handles Windows, WSL,
 * Linux, and macOS path conventions.
 *
 * Not to be confused with {@see \Simtabi\Laranail\Package\Tools\Support\Path\PathResolver},
 * which answers a different question: that one resolves a path relative to whichever FILE calls it,
 * from an explicit level count and direction, and is the replacement for hand-counted
 * `__DIR__ . '/../..'` strings. This one resolves a package root from a provider CLASS and offers
 * the path primitives the package's own services are built on.
 *
 * Both defer to {@see Path} for separator and root-prefix handling, so there is one implementation
 * of "what is a root prefix" rather than two that can disagree. They disagreed until 2026-08-26:
 * this class collapsed a UNC root to a local absolute path.
 */
class PathResolver
{
    /**
     * Normalize separators to the native one and collapse duplicates.
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    public static function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Split the root prefix off before collapsing anything. A UNC root is two leading
        // separators, and collapsing duplicates without separating it first turned
        // \\\\server\\share\\pkg into /server/share/pkg -- a network path silently rewritten
        // into a local absolute one, which every containment check downstream then ran against.
        [$prefix, $remainder] = Path::split($path);

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $remainder);

        // Collapse duplicate separators (e.g. path\\\\to\\file).
        $separator = preg_quote(DIRECTORY_SEPARATOR, '/');
        $normalized = preg_replace('/' . $separator . '+/', DIRECTORY_SEPARATOR, $normalized);

        // Resolve `.` and `..` segments. Stack-walk: `.` is a no-op,
        // `..` pops the previous segment unless we're already at the
        // filesystem root (in which case we drop it) or unless the
        // previous segment is itself a leading `..` (relative path).
        $isAbsolute = isset($normalized[0]) && $normalized[0] === DIRECTORY_SEPARATOR;
        $segments = explode(DIRECTORY_SEPARATOR, (string) $normalized);
        $stack = [];
        foreach ($segments as $i => $seg) {
            if ($i === 0 && $seg === '' && $isAbsolute) {
                $stack[] = ''; // preserve the leading slash

                continue;
            }
            if ($seg === '') {
                continue;
            }
            if ($seg === '.') {
                continue;
            }
            if ($seg === '..') {
                $top = end($stack);
                if (! in_array($top, [false, '', '..'], true)) {
                    array_pop($stack);

                    continue;
                }
                if ($isAbsolute) {
                    continue; // can't go above root
                }
            }
            $stack[] = $seg;
        }
        $normalized = implode(DIRECTORY_SEPARATOR, $stack);
        if ($normalized === '' && $isAbsolute) {
            $normalized = DIRECTORY_SEPARATOR;
        }

        // Trim trailing separator (unless it's root like C:\ or /)
        $normalized = rtrim($normalized, DIRECTORY_SEPARATOR);

        // Re-add trailing separator for root paths on Windows (C:) and Unix (/)
        if (self::isRoot($normalized)) {
            $normalized .= DIRECTORY_SEPARATOR;
        }

        if ($prefix !== '') {
            return $prefix . ltrim($normalized, DIRECTORY_SEPARATOR);
        }

        return $normalized;
    }

    /**
     * Reject paths that could escape the package: traversal sequences,
     * absolute paths, Windows drive letters, null bytes, and URL schemes.
     *
     * @param string $path Path to validate (should be relative)
     *
     * @throws RuntimeException If path fails security checks
     */
    public static function validatePathSecurity(string $path): void
    {
        $originalPath = $path;
        $path = trim($path);

        if ($path === '') {
            return; // Empty paths are allowed.
        }

        $errors = [];

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            $errors[] = 'starts with a slash (absolute path)';
        }

        if (preg_match('/^[A-Z]:[\\\\\/]/i', $path)) {
            $errors[] = 'contains a Windows drive letter (e.g., C:)';
        }

        if (str_contains($path, '..')) {
            $errors[] = 'contains parent directory references (..)';
        }

        if (str_contains($path, "\0")) {
            $errors[] = 'contains null bytes';
        }

        if (preg_match('/^[a-z]+:\/\//i', $path)) {
            $errors[] = 'contains URL scheme';
        }

        if ($errors !== []) {
            $reason = implode(', ', $errors);
            throw new RuntimeException(
                "Security violation in path '{$originalPath}': Path {$reason}. " .
                "Use relative paths within the package like 'config/file.php' or 'resources/views'."
            );
        }
    }

    /**
     * Walk up from a class file to the 'src' directory and return its
     * parent as the module root.
     *
     * @param string $classFile Full path to the class file
     * @return string Module root path
     *
     * @throws RuntimeException If 'src' directory not found
     */
    public static function detectModuleRoot(string $classFile): string
    {
        $directory = dirname($classFile);

        $parts = explode(DIRECTORY_SEPARATOR, static::normalizePath($directory));

        $srcIndex = array_search('src', $parts, true);

        if ($srcIndex !== false && $srcIndex > 0) {
            // Path up to (but not including) 'src'. Pure parsing: the
            // directory need not exist on disk, since callers may compute
            // roots from symbolic class files in tests or scaffolding.
            $moduleRoot = implode(DIRECTORY_SEPARATOR, array_slice($parts, 0, $srcIndex));

            return static::normalizePath($moduleRoot);
        }

        throw new RuntimeException(
            "Could not detect module root from class file: '{$classFile}'. " .
            "Expected to find 'src' directory in path hierarchy."
        );
    }

    /**
     * Check if path is a filesystem root: `/`, a Windows drive like `C:\`,
     * or a WSL mount point like `/mnt/c`.
     *
     * @param string $path Path to check
     * @return bool True if path is root
     */
    private static function isRoot(string $path): bool
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = rtrim($path, DIRECTORY_SEPARATOR);

        // Unix root.
        if ($path === '') {
            return true;
        }

        // Windows drive letter (C:, D:, etc.).
        if (preg_match('/^[a-zA-Z]:$/', $path)) {
            return true;
        }

        // WSL mount point (/mnt/c, /mnt/d, etc.).
        return (bool) preg_match('#^' . preg_quote(DIRECTORY_SEPARATOR, '#') . 'mnt' . preg_quote(DIRECTORY_SEPARATOR, '#') . '[a-zA-Z]$#', $path . DIRECTORY_SEPARATOR);
    }

    /**
     * Get parent directory with cross-platform support
     *
     * @param string $path Current path
     * @return string Parent path
     */
    private static function getParentDirectory(string $path): string
    {
        $normalized = static::normalizePath($path);
        $parent = dirname($normalized);

        return static::normalizePath($parent);
    }

    /**
     * Calculate package root from a service provider location.
     *
     * @param string|object $caller The service provider class name or instance
     * @param int $levelsUp Number of directory levels to traverse upward
     * @return string Absolute path to package root (normalized for current platform)
     *
     * @throws RuntimeException If reflection fails or invalid levelsUp value
     */
    public static function packageRootFromProvider(string|object $caller, int $levelsUp = 3): string
    {
        if ($levelsUp < 1 || $levelsUp > 10) {
            throw new RuntimeException(
                "Invalid levelsUp value: {$levelsUp}. Must be between 1 and 10. " .
                'Common values: 2 (from Providers/), 3 (from src/Providers/), 4 (from packages/vendor/package/src/Providers/)'
            );
        }

        // Resolve to a file path. Three cases:
        //   - object: reflect on its class.
        //   - string that exists on disk: use directly (covers __FILE__).
        //   - string class-name: reflect on it.
        if (is_object($caller)) {
            try {
                $filePath = (new ReflectionClass($caller))->getFileName();
            } catch (ReflectionException $e) {
                throw new RuntimeException("Failed to reflect object of class '" . $caller::class . "': {$e->getMessage()}", $e->getCode(), $e);
            }
        } elseif (is_file($caller)) {
            $filePath = $caller;
        } elseif (class_exists($caller)) {
            try {
                $filePath = (new ReflectionClass($caller))->getFileName();
            } catch (ReflectionException $e) {
                throw new RuntimeException("Failed to reflect class '{$caller}': {$e->getMessage()}", $e->getCode(), $e);
            }
        } else {
            throw new RuntimeException(
                "Could not resolve caller '{$caller}': it is neither an existing file nor a loadable class."
            );
        }

        if ($filePath === false) {
            $describe = is_object($caller) ? $caller::class : $caller;
            throw new RuntimeException(
                "Could not determine file path for caller '{$describe}'. " .
                'This may be an internal PHP class or trait.'
            );
        }

        $filePath = static::normalizePath($filePath);
        $currentPath = static::normalizePath(dirname($filePath));

        for ($i = 0; $i < $levelsUp; $i++) {
            $parentPath = self::getParentDirectory($currentPath);

            // Stop if we've hit the filesystem root.
            if (static::normalizePath($parentPath) === static::normalizePath($currentPath) || self::isRoot($currentPath)) {
                $platform = PHP_OS_FAMILY;
                throw new RuntimeException(
                    "Reached filesystem root while traversing up {$levelsUp} levels from '{$filePath}' (Platform: {$platform}). " .
                    "Current level: {$i}. Current path: '{$currentPath}'. " .
                    'You may need to decrease the levelsUp value. ' .
                    "Hint: Count the directories from your service provider file to where 'config/', 'resources/', and 'database/' are located."
                );
            }

            $currentPath = $parentPath;
        }

        return static::normalizePath($currentPath);
    }

    /**
     * Like packageRootFromProvider(), but also checks that the resolved
     * root contains the expected directories, catching misconfiguration
     * early.
     *
     * @param string|object $caller The service provider class name or instance
     * @param int $levelsUp Number of directory levels to traverse upward
     * @param array<int, string> $expectedDirs Directories that should exist in package root (e.g., ['src', 'config'])
     * @return string Absolute path to package root (normalized for current platform)
     *
     * @throws RuntimeException If validation fails
     */
    public static function packageRootFromProviderWithValidation(
        string|object $caller,
        int $levelsUp = 3,
        array $expectedDirs = []
    ): string {
        $packageRoot = static::packageRootFromProvider($caller, $levelsUp);

        if ($expectedDirs !== []) {
            $missingDirs = [];

            foreach ($expectedDirs as $dir) {
                $dirPath = static::normalizePath($dir);
                $fullPath = static::joinPaths($packageRoot, $dirPath);

                if (! is_dir($fullPath)) {
                    $missingDirs[] = $dir;
                }
            }

            if ($missingDirs !== []) {
                $platform = PHP_OS_FAMILY;
                throw new RuntimeException(
                    "Package root validation failed at '{$packageRoot}' (Platform: {$platform}). " .
                    'Missing expected directories: ' . implode(', ', $missingDirs) . '. ' .
                    'You may need to adjust the levelsUp value. ' .
                    "Current levelsUp: {$levelsUp}"
                );
            }
        }

        return $packageRoot;
    }

    /**
     * Join path segments with cross-platform separator handling.
     *
     * @param string ...$segments Path segments to join
     * @return string Joined and normalized path
     */
    public static function joinPaths(string ...$segments): string
    {
        if ($segments === []) {
            return '';
        }

        $path = static::normalizePath($segments[0]);
        $counter = count($segments);

        for ($i = 1; $i < $counter; $i++) {
            $segment = trim($segments[$i], '/\\');
            if ($segment !== '') {
                $path .= DIRECTORY_SEPARATOR . $segment;
            }
        }

        return static::normalizePath($path);
    }

    /**
     * Determine the levelsUp value by searching upward for package markers
     * (composer.json, src/, etc.). Experimental; prefer an explicit
     * levelsUp value in production.
     *
     * @param string|object $caller The service provider class name or instance
     * @param array<int, string> $markers Files/dirs that indicate package root (default: ['composer.json', 'src'])
     * @param int $maxLevels Maximum levels to search (default: 10)
     * @return int Detected levelsUp value
     *
     * @throws RuntimeException If package root cannot be detected
     */
    public static function autoDetectLevelsUp(
        string|object $caller,
        array $markers = ['composer.json', 'src'],
        int $maxLevels = 10
    ): int {
        $className = is_object($caller) ? $caller::class : $caller;

        if (! class_exists($className)) {
            throw new RuntimeException("Failed to reflect class '{$className}': class does not exist.");
        }

        // class_exists() narrows $className to a loadable class-string, so
        // ReflectionClass cannot throw here.
        $reflector = new ReflectionClass($className);

        $filePath = $reflector->getFileName();
        if ($filePath === false) {
            throw new RuntimeException("Could not determine file path for class '{$className}'.");
        }

        $filePath = static::normalizePath($filePath);
        $currentPath = static::normalizePath(dirname($filePath));

        for ($level = 1; $level <= $maxLevels; $level++) {
            $parentPath = self::getParentDirectory($currentPath);

            if (static::normalizePath($parentPath) === static::normalizePath($currentPath) || self::isRoot($currentPath)) {
                break;
            }

            $currentPath = $parentPath;

            foreach ($markers as $marker) {
                $markerPath = static::joinPaths($currentPath, $marker);
                if (file_exists($markerPath)) {
                    return $level;
                }
            }
        }

        $platform = PHP_OS_FAMILY;
        throw new RuntimeException(
            "Could not auto-detect package root for class '{$className}' (Platform: {$platform}). " .
            "Searched up to {$maxLevels} levels from '{$filePath}'. " .
            'Please specify levelsUp manually.'
        );
    }

    /**
     * Calculate the relative path from one absolute path to another.
     *
     * @param string $from Starting path
     * @param string $to Target path
     * @return string Relative path
     */
    public static function getRelativePath(string $from, string $to): string
    {
        $from = static::normalizePath($from);
        $to = static::normalizePath($to);

        $fromParts = explode(DIRECTORY_SEPARATOR, trim($from, DIRECTORY_SEPARATOR));
        $toParts = explode(DIRECTORY_SEPARATOR, trim($to, DIRECTORY_SEPARATOR));

        // Find the common base.
        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));

        for ($i = 0; $i < $minLength; $i++) {
            // Case-insensitive comparison on Windows.
            $fromPart = PHP_OS_FAMILY === 'Windows' ? strtolower($fromParts[$i]) : $fromParts[$i];
            $toPart = PHP_OS_FAMILY === 'Windows' ? strtolower($toParts[$i]) : $toParts[$i];

            if ($fromPart === $toPart) {
                $commonLength++;
            } else {
                break;
            }
        }

        $relativeParts = [];
        $counter = count($fromParts);

        // One '..' for each remaining part in $from.
        for ($i = $commonLength; $i < $counter; $i++) {
            $relativeParts[] = '..';
        }
        $counter = count($toParts);

        // Then the remaining parts of $to.
        for ($i = $commonLength; $i < $counter; $i++) {
            $relativeParts[] = $toParts[$i];
        }

        $result = implode(DIRECTORY_SEPARATOR, $relativeParts);

        return $result ?: '.';
    }

    /**
     * Check if a path is absolute. Handles Unix paths, Windows drive
     * letters and UNC paths, and WSL mounts.
     *
     * @param string $path Path to check
     * @return bool True if path is absolute
     */
    public static function isAbsolutePath(string $path): bool
    {
        if ($path === '' || $path === '0') {
            return false;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Unix absolute path starts with /.
        if ($normalized[0] === DIRECTORY_SEPARATOR) {
            return true;
        }

        // Windows drive letter (C:\, D:\, etc.).
        if (strlen($normalized) >= 2 && preg_match('/^[a-zA-Z]:/', $normalized)) {
            return true;
        }

        // Windows UNC path (\\server\share).
        return strlen($normalized) >= 2 && $normalized[0] === '\\' && $normalized[1] === '\\';
    }

    /**
     * Ensure a path is absolute, resolving relative paths against a base.
     *
     * @param string $path Path to make absolute
     * @param string|null $basePath Base path for relative paths (defaults to getcwd())
     * @return string Absolute path
     */
    public static function toAbsolutePath(string $path, ?string $basePath = null): string
    {
        if (static::isAbsolutePath($path)) {
            return static::normalizePath($path);
        }

        $basePath ??= getcwd();
        if ($basePath === false) {
            throw new RuntimeException('Could not determine current working directory.');
        }

        return static::joinPaths($basePath, $path);
    }

    /**
     * Convert an absolute path to one relative to a given base.
     *
     * Inverse of toAbsolutePath(). Wraps getRelativePath() with a
     * (path, base) argument order: first the path you have, then what you
     * want it relative to.
     *
     * @param string $absolutePath Absolute path to convert.
     * @param string $basePath Base path the result is relative to.
     * @return string Relative path. Returns '.' when paths are identical.
     */
    public static function toRelativePath(string $absolutePath, string $basePath): string
    {
        return static::getRelativePath($basePath, $absolutePath);
    }
}

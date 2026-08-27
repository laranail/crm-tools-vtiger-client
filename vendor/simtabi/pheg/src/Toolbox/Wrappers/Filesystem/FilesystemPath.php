<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Wrappers\Filesystem;

use Symfony\Component\Filesystem\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Exception\RuntimeException;
use Symfony\Component\Filesystem\Path as SymfonyPathUtil;

class FilesystemPath
{
    public function __construct()
    {
    }

    /**
     * Receives a string (probably a file path) and canonicalizes and unifies the slashes for the current filesystem
     *
     * @param   string  $path   The string that's the target to this method
     * @param   string  $slash  By default, the value of DIRECTORY_SEPARATOR, but you can define a slash you"d like here
     *
     * @return string The $string with unified slashes
     * @see FilesystemPath::canonicalize()
     */
    public function unifySlashes(string $path, string $slash = DIRECTORY_SEPARATOR): string
    {
        $result = static::canonicalize($path);
        if ($slash !== '/') {
            return str_replace('/', $slash, $result);
        }

        return $result;
    }

    /**
     * Works similar to unifySlashes() but also makes sure the given path ends with a tailing slash.
     * NOTE: This only makes sense if you know that $path is a directory path
     *
     * @param   string  $path   The string that's the target to this method
     * @param   string  $slash  By default, the value of DIRECTORY_SEPARATOR, but you can define a slash you"d like here
     *
     * @return string
     * @see FilesystemPath::unifySlashes()
     */
    public function unifyPath(string $path, string $slash = DIRECTORY_SEPARATOR): string
    {
        return rtrim(static::unifySlashes(trim($path), $slash), $slash) . $slash;
    }

    /**
     * Can be used to convert a Fully\Qualified\Classname to Classname
     *
     * @param   string  $classname  The classname to get the basename of.
     *
     * @return string
     */
    public function classBasename(string $classname): string
    {
        return basename(str_replace('\\', DIRECTORY_SEPARATOR, $classname));
    }

    /**
     * Can be used to convert a Fully\Qualified\Classname to Fully\Qualified
     * This works the same way dirname() would with a folder path.
     *
     * @param   string  $classname  The classname of to get the namespace of.
     *
     * @return string
     */
    public function classNamespace(string $classname): string
    {
        $result = str_replace(DIRECTORY_SEPARATOR, '\\', dirname(str_replace('\\', DIRECTORY_SEPARATOR, $classname)));
        if ($result === '.') {
            return '';
        }

        return $result;
    }

    /**
     * Canonicalizes the given path.
     *
     * During normalization, all slashes are replaced by forward slashes ("/").
     * Furthermore, all "." and ".." segments are removed as far as possible.
     * ".." segments at the beginning of relative paths are not removed.
     *
     * ```php
     * echo Path::canonicalize("\symfony\puli\..\css\style.css");
     * // => /symfony/css/style.css
     *
     * echo Path::canonicalize("../css/./style.css");
     * // => ../css/style.css
     * ```
     *
     * This method is able to deal with both UNIX and Windows paths.
     */
    public function canonicalize(string $path): string
    {
        return SymfonyPathUtil::canonicalize($path);
    }

    /**
     * Normalizes the given path.
     *
     * During normalization, all slashes are replaced by forward slashes ("/").
     * Contrary to {@link canonicalize()}, this method does not remove invalid
     * or dot path segments. Consequently, it is much more efficient and should
     * be used whenever the given path is known to be a valid, absolute system
     * path.
     *
     * This method is able to deal with both UNIX and Windows paths.
     */
    public function normalize(string $path): string
    {
        return SymfonyPathUtil::normalize($path);
    }

    /**
     * Returns the directory part of the path.
     *
     * This method is similar to PHP's dirname(), but handles various cases
     * where dirname() returns a weird result:
     *
     *  - dirname() does not accept backslashes on UNIX
     *  - dirname("C:/symfony") returns "C:", not "C:/"
     *  - dirname("C:/") returns ".", not "C:/"
     *  - dirname("C:") returns ".", not "C:/"
     *  - dirname("symfony") returns ".", not ""
     *  - dirname() does not canonicalize the result
     *
     * This method fixes these shortcomings and behaves like dirname()
     * otherwise.
     *
     * The result is a canonical path.
     *
     * @return string The canonical directory part. Returns the root directory
     *                if the root directory is passed. Returns an empty string
     *                if a relative path is passed that contains no slashes.
     *                Returns an empty string if an empty string is passed.
     */
    public function getDirectory(string $path): string
    {
        return SymfonyPathUtil::getDirectory($path);
    }

    /**
     * Returns canonical path of the user's home directory.
     *
     * Supported operating systems:
     *
     *  - UNIX
     *  - Windows8 and upper
     *
     * If your operating system or environment isn't supported, an exception is thrown.
     *
     * The result is a canonical path.
     *
     * @throws RuntimeException If your operating system or environment isn't supported
     */
    public function getHomeDirectory(): string
    {
        return SymfonyPathUtil::getHomeDirectory();
    }

    /**
     * Returns the root directory of a path.
     *
     * The result is a canonical path.
     *
     * @return string The canonical root directory. Returns an empty string if
     *                the given path is relative or empty.
     */
    public function getRoot(string $path): string
    {
        return SymfonyPathUtil::getRoot($path);
    }

    /**
     * Returns the file name from a file path.
     *
     * @param   string  $path  The path string.
     *
     * @return string The file name.
     *
     * @since 1.1 Added method.
     * @since 2.0 Method now fails if $path is not a string.
     */
    public function getFilename(string $path): string
    {
        if ('' === $path) {
            return '';
        }

        return basename($path);
    }

    /**
     * Returns the file name without the extension from a file path.
     *
     * @param   string|null  $extension  if specified, only that extension is cut
     *                                   off (may contain leading dot)
     */
    public function getFilenameWithoutExtension(string $path, string $extension = null): string
    {
        return SymfonyPathUtil::getFilenameWithoutExtension($path, $extension);
    }

    /**
     * Returns the extension from a file path (without leading dot).
     *
     * @param   bool  $forceLowerCase  forces the extension to be lower-case
     */
    public function getExtension(string $path, bool $forceLowerCase = false): string
    {
        return SymfonyPathUtil::getExtension($path, $forceLowerCase);
    }

    /**
     * Returns whether the path has an (or the specified) extension.
     *
     * @param   string                $path        the path string
     * @param   string|string[]|null  $extensions  if null or not provided, checks if
     *                                             an extension exists, otherwise
     *                                             checks for the specified extension
     *                                             or array of extensions (with or
     *                                             without leading dot)
     * @param   bool                  $ignoreCase  whether to ignore case-sensitivity
     */
    public function hasExtension(string $path, $extensions = null, bool $ignoreCase = false): bool
    {
        return SymfonyPathUtil::hasExtension($path, $extensions, $ignoreCase);
    }

    /**
     * Changes the extension of a path string.
     *
     * @param   string  $path       The path string with filename.ext to change.
     * @param   string  $extension  new extension (with or without leading dot)
     *
     * @return string the path string with new file extension
     */
    public function changeExtension(string $path, string $extension): string
    {
        return SymfonyPathUtil::changeExtension($path, $extension);
    }

    /**
     * Returns whether a path is absolute.
     *
     * @param   string  $path  A path string.
     *
     * @return bool Returns true if the path is absolute, false if it is
     *              relative or empty.
     *
     * @since 1.0 Added method.
     * @since 2.0 Method now fails if $path is not a string.
     */
    public function isAbsolute(string $path): bool
    {
        return SymfonyPathUtil::isAbsolute($path);
    }

    /**
     * Returns whether a path is relative.
     *
     * @param   string  $path  A path string.
     *
     * @return bool Returns true if the path is relative or empty, false if
     *              it is absolute.
     *
     * @since 1.0 Added method.
     * @since 2.0 Method now fails if $path is not a string.
     */
    public function isRelative(string $path): bool
    {
        return SymfonyPathUtil::isRelative($path);
    }

    /**
     * Turns a relative path into an absolute path in canonical form.
     *
     * Usually, the relative path is appended to the given base path. Dot
     * segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * echo Path::makeAbsolute("../style.css", "/symfony/puli/css");
     * // => /symfony/puli/style.css
     * ```
     *
     * If an absolute path is passed, that path is returned unless its root
     * directory is different than the one of the base path. In that case, an
     * exception is thrown.
     *
     * ```php
     * Path::makeAbsolute("/style.css", "/symfony/puli/css");
     * // => /style.css
     *
     * Path::makeAbsolute("C:/style.css", "C:/symfony/puli/css");
     * // => C:/style.css
     *
     * Path::makeAbsolute("C:/style.css", "/symfony/puli/css");
     * // InvalidArgumentException
     * ```
     *
     * If the base path is not an absolute path, an exception is thrown.
     *
     * The result is a canonical path.
     *
     * @param   string  $basePath  an absolute base path
     *
     * @throws InvalidArgumentException if the base path is not absolute or if
     *                                  the given path is an absolute path with
     *                                  a different root than the base path
     */
    public function makeAbsolute(string $path, string $basePath): string
    {
        return SymfonyPathUtil::makeAbsolute($path, $basePath);
    }

    /**
     * Turns a path into a relative path.
     *
     * The relative path is created relative to the given base path:
     *
     * ```php
     * echo Path::makeRelative("/symfony/style.css", "/symfony/puli");
     * // => ../style.css
     * ```
     *
     * If a relative path is passed and the base path is absolute, the relative
     * path is returned unchanged:
     *
     * ```php
     * Path::makeRelative("style.css", "/symfony/puli/css");
     * // => style.css
     * ```
     *
     * If both paths are relative, the relative path is created with the
     * assumption that both paths are relative to the same directory:
     *
     * ```php
     * Path::makeRelative("style.css", "symfony/puli/css");
     * // => ../../../style.css
     * ```
     *
     * If both paths are absolute, their root directory must be the same,
     * otherwise an exception is thrown:
     *
     * ```php
     * Path::makeRelative("C:/symfony/style.css", "/symfony/puli");
     * // InvalidArgumentException
     * ```
     *
     * If the passed path is absolute, but the base path is not, an exception
     * is thrown as well:
     *
     * ```php
     * Path::makeRelative("/symfony/style.css", "symfony/puli");
     * // InvalidArgumentException
     * ```
     *
     * If the base path is not an absolute path, an exception is thrown.
     *
     * The result is a canonical path.
     *
     * @throws InvalidArgumentException if the base path is not absolute or if
     *                                  the given path has a different root
     *                                  than the base path
     */
    public function makeRelative(string $path, string $basePath): string
    {
        return SymfonyPathUtil::makeRelative($path, $basePath);
    }

    /**
     * Returns whether the given path is on the local filesystem.
     *
     * @param   string  $path  A path string.
     *
     * @return bool Returns true if the path is local, false for a URL.
     *
     * @since 1.0 Added method.
     * @since 2.0 Method now fails if $path is not a string.
     */
    public function isLocal(string $path): bool
    {
        return SymfonyPathUtil::isLocal($path);
    }

    /**
     * Returns the longest common base path in canonical form of a set of paths or
     * `null` if the paths are on different Windows partitions.
     *
     * Dot segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * $basePath = Path::getLongestCommonBasePath(
     *     '/symfony/css/style.css',
     *     '/symfony/css/..'
     * );
     * // => /symfony
     * ```
     *
     * The root is returned if no common base path can be found:
     *
     * ```php
     * $basePath = Path::getLongestCommonBasePath(
     *     '/symfony/css/style.css',
     *     '/puli/css/..'
     * );
     * // => /
     * ```
     *
     * If the paths are located on different Windows partitions, `null` is
     * returned.
     *
     * ```php
     * $basePath = Path::getLongestCommonBasePath(
     *     'C:/symfony/css/style.css',
     *     'D:/symfony/css/..'
     * );
     * // => null
     * ```
     */
    public function getLongestCommonBasePath(string ...$paths): ?string
    {
        return SymfonyPathUtil::getLongestCommonBasePath(...$paths);
    }

    /**
     * Joins two or more path strings into a canonical path.
     */
    public function join(string ...$paths): string
    {
        return SymfonyPathUtil::join(...$paths);
    }

    /**
     * Returns whether a path is a base path of another path.
     *
     * Dot segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * Path::isBasePath('/symfony', '/symfony/css');
     * // => true
     *
     * Path::isBasePath('/symfony', '/symfony');
     * // => true
     *
     * Path::isBasePath('/symfony', '/symfony/..');
     * // => false
     *
     * Path::isBasePath('/symfony', '/puli');
     * // => false
     * ```
     */
    public function isBasePath(string $basePath, string $ofPath): bool
    {
        return SymfonyPathUtil::isBasePath($basePath, $ofPath);
    }
}
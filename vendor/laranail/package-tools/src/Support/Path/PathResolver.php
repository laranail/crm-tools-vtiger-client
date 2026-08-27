<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Path;

use FilesystemIterator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use NoDiscard;
use RuntimeException;
use SplFileInfo;

/**
 * Resolve a path relative to the file that called it, by an explicit number of levels.
 *
 * Replaces the `__DIR__ . '/../../config/db-tools.php'` idiom, whose failure mode is that the dot-dot
 * count is invisible: it is correct only for the file's current depth, nothing checks it, and moving
 * the file leaves a string that still parses, still looks plausible, and resolves to a path that does
 * not exist. That is exactly how moving service providers into Providers/ broke config loading across
 * this family.
 *
 *     PathResolver::resolve(levels: 2, direction: PathDirection::Outer, path: 'config/db-tools.php')
 *
 * Everything a caller passes is treated as untrusted. The `$path` argument frequently originates in
 * package configuration, which an application can override, so it is validated against stream
 * wrappers, null bytes, absolute paths and traversal before the filesystem is touched at all -- a
 * `phar://` path reaching a later `require` is remote code execution, not a wrong directory.
 */
final class PathResolver
{
    /**
     * Anything before "://" -- and the bare "phar:" form, which the stream layer also accepts.
     *
     * Matching the scheme shape rather than a denylist of known wrappers is deliberate: wrappers can
     * be registered at runtime by any extension or by the application itself, so a list of the
     * dangerous ones is a list of the ones known to be dangerous today.
     */
    /**
     * Direction aliases, so a call site reads PathResolver::OUTER without a second import.
     *
     * These are the enum cases, not copies of them: a typed class constant holds the case itself, so
     * the argument stays type-checked and a match() over PathDirection remains exhaustive.
     */
    public const PathDirection OUTER = PathDirection::Outer;

    public const PathDirection INNER = PathDirection::Inner;

    private const string SCHEME_PATTERN = '#^[a-z][a-z0-9+.\-]+:#i';

    /**
     * @param int $levels Directories to walk. Must be at least 1.
     * @param PathDirection $direction Outer climbs toward the root; Inner descends into `$path`.
     * @param string $path Relative path applied after the walk. Required for Inner.
     *
     * @throws InvalidArgumentException When the arguments are malformed or the path is unsafe.
     * @throws RuntimeException When resolution escapes its boundary or the caller is unknown.
     */
    #[NoDiscard('The resolved path is the entire result; discarding it performs no work.')]
    public static function resolve(int $levels, PathDirection $direction = self::OUTER, string $path = ''): string
    {
        // Argument validation runs before any filesystem access, so a malformed call cannot be used
        // to probe for the existence of paths it was never entitled to name.
        if ($levels < 1) {
            throw new InvalidArgumentException(sprintf(
                'Levels must be at least 1, got %d. The calling directory itself is levels: 1 with an empty path.',
                $levels,
            ));
        }

        if ($path !== '') {
            self::guardPath($path);
        }

        $base = self::callerDirectory();

        return match ($direction) {
            PathDirection::Outer => self::climb($base, $levels, $path),
            PathDirection::Inner => self::descend($base, $levels, $path),
        };
    }

    /**
     * Walk up from the calling file until a directory containing `$marker` is found.
     *
     * This is the better answer wherever it applies, because it removes the level count altogether:
     * a file that moves deeper keeps resolving to the same root, which is exactly the failure the
     * level-counted form is only *guarding* against. Prefer it over
     * `resolve(levels: n, path: 'config/x.php')` unless the target genuinely is "n directories up"
     * rather than "the package root".
     *
     *     $config = PathResolver::packageRoot() . '/config/db-tools.php';
     *     $config = Path::join(PathResolver::packageRoot(), 'config/db-tools.php');
     *
     * @param string $marker File or directory that identifies the root. Must be a single segment --
     *                       a path here would be searched for at every level, which is a different
     *                       and much slower question than the one this answers.
     *
     * @throws InvalidArgumentException When the marker is not a single path segment.
     * @throws RuntimeException When no ancestor contains the marker.
     */
    #[NoDiscard('The resolved root is the entire result; discarding it performs no work.')]
    public static function packageRoot(string $marker = 'composer.json'): string
    {
        self::guardPath($marker);

        if (count(Path::segments($marker)) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The marker "%s" must be a single path segment; searching for a nested path at every level is a different question.',
                $marker,
            ));
        }

        $files = self::filesystem();
        $directory = self::callerDirectory();

        // Bounded by depth rather than by a fixed count, so it stops at the root prefix -- the drive
        // or UNC share -- instead of spinning at "/" where dirname() saturates.
        for ($remaining = Path::depth($directory); $remaining >= 0; $remaining--) {
            if ($files->exists(Path::join($directory, $marker))) {
                return $directory;
            }

            $directory = dirname($directory);
        }

        throw new RuntimeException(sprintf(
            'No ancestor of the calling file contains "%s". PathResolver::packageRoot() identifies a root by marker, so the package needs one.',
            $marker,
        ));
    }

    /**
     * Reject every path shape that resolves somewhere other than where it appears to.
     */
    private static function guardPath(string $path): void
    {
        // Null bytes first. PHP's path functions are C strings underneath and truncate at the byte,
        // so "config.php\0.txt" passes an extension check and then opens config.php.
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('The path contains a null byte.');
        }

        if (preg_match('#^[a-z]:#i', $path) === 1) {
            throw new InvalidArgumentException(sprintf('The path "%s" carries a Windows drive letter.', $path));
        }

        if (preg_match(self::SCHEME_PATTERN, $path) === 1) {
            throw new InvalidArgumentException(sprintf(
                'The path "%s" names a stream wrapper. Only plain relative filesystem paths are accepted.',
                $path,
            ));
        }

        // A UNC path is two leading separators, which the absolute check below would also catch on
        // Windows -- naming it separately makes the error say what is actually wrong.
        if (str_starts_with($path, '\\\\') || str_starts_with($path, '//')) {
            throw new InvalidArgumentException(sprintf('The path "%s" is a UNC network path.', $path));
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw new InvalidArgumentException(sprintf(
                'The path "%s" is absolute. PathResolver appends to a resolved directory, so the path must be relative.',
                $path,
            ));
        }

        // Checked per segment rather than with str_contains, so a legitimate filename such as
        // "cache..old" is not mistaken for traversal.
        foreach (Path::segments($path) as $segment) {
            if ($segment === '..') {
                throw new InvalidArgumentException(sprintf(
                    'The path "%s" contains a ".." segment. Express the climb with levels and direction; mixing the two is what makes these paths unreadable.',
                    $path,
                ));
            }
        }
    }

    /**
     * The directory of the file that called resolve().
     *
     * Deliberately the calling *file*, not the calling class: a trait's method, a closure and an
     * inherited method all report a class that lives somewhere other than the file on disk, and it is
     * the file's own location that the caller counts levels from.
     */
    private static function callerDirectory(): string
    {
        // No frame limit: a helper that wraps resolve() pushes the first foreign file past any
        // small bound, and truncating the trace would report the wrapper as the caller.
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = $frame['file'] ?? null;

            if (is_string($file) && $file !== '' && dirname($file) !== __DIR__) {
                // realpath() collapses symlinks, so the boundary checks downstream compare canonical
                // paths. Without it a symlinked package directory makes containment untestable.
                $real = realpath(dirname($file));

                if ($real === false) {
                    throw new RuntimeException(sprintf('The calling directory "%s" does not exist.', dirname($file)));
                }

                return $real;
            }
        }

        throw new RuntimeException(
            'Unable to determine the calling file. PathResolver resolves relative to the file that calls it, so it cannot be used from eval()d code or a native callback with no file frame.',
        );
    }

    /**
     * @param int<1, max> $levels
     */
    private static function climb(string $base, int $levels, string $path): string
    {
        // dirname() saturates at the root instead of failing, so asking for more levels than exist
        // returns "/" and every path built on it is silently wrong. The depth has to be checked up
        // front: the return value cannot distinguish this from a correct climb out of a shallow
        // directory, which legitimately also yields "/".
        $depth = Path::depth($base);

        if ($levels >= $depth) {
            throw new RuntimeException(sprintf(
                'Climbing %d level(s) from "%s" runs past its root (%s); it is only %d level(s) below it.',
                $levels,
                $base,
                Path::split($base)[0] === '' ? 'the working directory' : Path::split($base)[0],
                $depth,
            ));
        }

        $resolved = dirname($base, $levels);

        return $path === '' ? $resolved : Path::join($resolved, $path);
    }

    /**
     * Walk into `$path` one segment at a time, confirming each is a real child of its parent.
     *
     * String concatenation would accept a segment that is a symlink out of the tree; comparing each
     * step against the parent directory's actual entries does not. The iterator is the check -- it
     * reads what is on disk rather than trusting the name it was given.
     *
     * @param int<1, max> $levels
     */
    private static function descend(string $base, int $levels, string $path): string
    {
        if ($path === '') {
            throw new InvalidArgumentException(
                'An Inner resolution needs a path to descend into; levels alone cannot name a child directory.',
            );
        }

        $segments = Path::segments($path);

        // The count and the path have to agree. The redundancy is the point: editing one without the
        // other fails loudly instead of resolving somewhere plausible.
        if (count($segments) !== $levels) {
            throw new InvalidArgumentException(sprintf(
                'Descending %d level(s) needs a path of exactly %d segment(s); "%s" has %d.',
                $levels,
                $levels,
                $path,
                count($segments),
            ));
        }

        $files = self::filesystem();
        $current = $base;

        foreach ($segments as $segment) {
            if (! $files->isDirectory($current)) {
                throw new RuntimeException(sprintf('"%s" is not a directory.', $current));
            }

            $match = null;

            foreach (new FilesystemIterator($current, FilesystemIterator::SKIP_DOTS) as $entry) {
                /** @var SplFileInfo $entry */
                if ($entry->getFilename() === $segment) {
                    $match = $entry;
                    break;
                }
            }

            if (! $match instanceof SplFileInfo) {
                throw new RuntimeException(sprintf('"%s" has no entry named "%s".', $current, $segment));
            }

            $real = $match->getRealPath();

            if ($real === false) {
                throw new RuntimeException(sprintf('"%s" could not be resolved; it may be a broken symlink.', $match->getPathname()));
            }

            $current = $real;
        }

        // A symlinked segment can be a genuine entry of its parent and still land outside the tree,
        // so containment is asserted against the canonical result rather than inferred from the walk.
        self::assertWithin($base, $current);

        return $current;
    }

    /**
     * Containment against the canonical boundary. Separator handling lives in Path::isWithin(), which
     * is where the "/a/bc is not inside /a/b" case is dealt with.
     */
    private static function assertWithin(string $boundary, string $candidate): void
    {
        if (Path::isWithin($boundary, $candidate)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Resolving to "%s" escapes "%s". A path segment is most likely a symlink pointing out of the tree.',
            $candidate,
            $boundary,
        ));
    }

    /**
     * Laravel's Filesystem, through the facade when an application is available so a test's swap or
     * fake is honoured, and a plain instance otherwise. PathResolver is called from service providers,
     * which run before the container is necessarily usable, so it cannot depend on one existing.
     */
    private static function filesystem(): Filesystem
    {
        return Facade::getFacadeApplication() === null ? new Filesystem : File::getFacadeRoot();
    }
}

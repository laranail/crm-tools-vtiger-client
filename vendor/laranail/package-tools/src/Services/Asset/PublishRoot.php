<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Illuminate\Support\Str;
use Simtabi\Laranail\Package\Tools\Exceptions\UnsafeAssetPath;
use Simtabi\Laranail\Package\Tools\Support\Path\Path;
use Stringable;

/**
 * A directory that published assets may be deleted from.
 *
 * Constructing one is the validation. Every check happens in `make()`, so a
 * `PublishRoot` in hand is proof the path passed all of them — there is no way
 * to hold an unvalidated root, and therefore no path where a caller forgets.
 *
 * The ladder, in order, because each step assumes the previous one:
 *
 * 1. Non-empty, no null byte.
 * 2. Relative paths resolve against the project root.
 * 3. **Lexical** normalisation — `.` and `..` collapsed without touching the
 *    filesystem, so a root that does not exist yet can still be validated.
 * 4. Containment in the project. A publish root is always inside the app.
 * 5. A non-overridable deny-list.
 * 6. A minimum depth.
 * 7. Symlink containment, checked against the filesystem when the path exists.
 */
final readonly class PublishRoot implements Stringable
{
    /**
     * Directories that can never be a publish root, whatever config says.
     *
     * Not configurable on purpose: config may narrow the blast radius, never
     * widen it. `public` is on the list because the single most likely typo is
     * dropping the `/vendor` off `public/vendor`, and the cost of that is the
     * whole document root.
     *
     * @var list<string>
     */
    public const array DENY = [
        'app', 'bootstrap', 'config', 'database', 'node_modules', 'public',
        'resources', 'routes', 'src', 'storage', 'tests', 'vendor',
    ];

    private function __construct(
        private string $path,
        private string $basePath,
    ) {}

    /**
     * @throws UnsafeAssetPath
     */
    public static function make(string $configured, string $basePath, int $minimumDepth = 2): self
    {
        $basePath = self::normalise(rtrim($basePath, '/\\'));

        if (trim($configured) === '') {
            throw UnsafeAssetPath::empty();
        }

        if (str_contains($configured, "\0")) {
            throw UnsafeAssetPath::malformed($configured);
        }

        $candidate = self::isAbsolute($configured)
            ? $configured
            : $basePath . '/' . ltrim($configured, '/\\');

        $normalised = self::normalise($candidate);

        if ($normalised === '') {
            throw UnsafeAssetPath::malformed($configured);
        }

        if (! self::isSelfOrUnder($basePath, $normalised)) {
            throw UnsafeAssetPath::outsideProject($configured, $basePath);
        }

        if ($normalised === $basePath) {
            throw UnsafeAssetPath::protectedRoot($configured, 'it is the project root');
        }

        $relative = ltrim(Str::after($normalised, $basePath), '/');
        $segments = $relative === '' ? [] : explode('/', $relative);

        if (count($segments) === 1 && in_array($segments[0], self::DENY, true)) {
            throw UnsafeAssetPath::protectedRoot(
                $configured,
                "[{$segments[0]}] is on the non-overridable deny-list",
            );
        }

        if (count($segments) < $minimumDepth) {
            throw UnsafeAssetPath::rootTooShallow($configured, count($segments), $minimumDepth);
        }

        $root = new self($normalised, $basePath);

        // Only meaningful once the directory exists; a root that has never been
        // published to yet is legitimate.
        if (is_dir($normalised)) {
            $real = realpath($normalised);
            $realBase = realpath($basePath);

            if ($real === false || $realBase === false || ! self::isSelfOrUnder($realBase, $real)) {
                throw UnsafeAssetPath::outsideProject($configured, $basePath);
            }
        }

        return $root;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The filesystem-resolved path, or the lexical one when it does not exist.
     */
    public function realPath(): string
    {
        $real = realpath($this->path);

        return $real === false ? $this->path : $real;
    }

    /**
     * Whether a path is a **strict** descendant.
     *
     * Strict because the root itself is never deletable, and because
     * `str_starts_with($p, $root)` alone would match `public/vendor2` against
     * a root of `public/vendor`. The trailing separator is the whole guard.
     */
    public function contains(string $candidate): bool
    {
        $normalised = self::normalise($candidate);

        return $normalised !== $this->path && self::isUnder($this->path, $normalised);
    }

    public function depth(): int
    {
        $relative = Str::after($this->path, $this->basePath);

        return count(Path::segments($relative));
    }

    public function __toString(): string
    {
        return $this->path;
    }

    /**
     * Collapse `.` and `..` without touching the filesystem.
     *
     * Lexical on purpose: a publish root may not exist yet, and `realpath()`
     * would return false rather than validating it. The filesystem check comes
     * afterwards, separately, where it can be conditional.
     */
    public static function normalise(string $path): string
    {
        // Split the root prefix off first. It is not a segment: "\\\\server\\share" is a UNC root, and
        // treating its host and share as ordinary segments collapsed it to "/server/share" -- a local
        // absolute path -- so every containment check below then ran against the wrong path.
        [$prefix, $rest] = Path::split($path);
        $segments = [];

        foreach (Path::segments($rest) as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $prefix . implode(Path::SEPARATOR, $segments);
    }

    private static function isAbsolute(string $path): bool
    {
        return Path::isAbsolute($path);
    }

    /** Self-or-descendant. */
    private static function isSelfOrUnder(string $root, string $candidate): bool
    {
        return $candidate === $root || self::isUnder($root, $candidate);
    }

    /** Strict descendant. Separator-, root- and case-handling all live in Path::isWithin(). */
    private static function isUnder(string $root, string $candidate): bool
    {
        return $candidate !== $root && Path::isWithin($root, $candidate);
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Path;

/**
 * Separator- and root-agnostic path assembly.
 *
 * Two separate concerns get confused under the name "platform agnostic", and only one of them is a
 * real bug:
 *
 * - **Passing a path to PHP.** Windows accepts "/" in every filesystem function, so
 *   `__DIR__ . '/../config'` works everywhere and is not worth rewriting.
 * - **Comparing or slicing a path.** `realpath()`, `SplFileInfo::getRealPath()` and `__DIR__` all
 *   return the *platform's* separator, so a comparison written against a hardcoded "/" silently
 *   fails on Windows -- and fails by reporting that a path is outside a boundary it is inside, which
 *   in a containment check means a security decision made on a string mismatch.
 *
 * This class exists for the second. Input accepts either separator; output is always the platform's.
 *
 * The other half of the job is the **root prefix**. A path is a prefix plus segments, and the prefix
 * is not a segment: "\\\\server\\share" is a UNC root, "C:\\" is a drive root, "/" is the Unix root,
 * and "" means relative. Treating a prefix as a segment is how "\\\\server\\share\\pkg" collapses to
 * "/server/share/pkg" -- a network path silently rewritten into a local absolute one -- and how a
 * climb out of "C:\\project" produces ".", a *relative* path that then resolves against the working
 * directory.
 *
 * A note on the shape, since Mukora CMS's `DS` constant is the reference: its `switch (PHP_OS)`
 * assigns `DIRECTORY_SEPARATOR` in each named branch and "/" in the default, so every branch already
 * agreed -- and macOS reports "Darwin", which is not one of the named cases, so it took the default.
 * The switch could not change the answer on any platform. What it was reaching for is the rule kept
 * here: never write a separator literal.
 */
final class Path
{
    /** The platform's separator. Named so call sites read as intent rather than as a global. */
    public const string SEPARATOR = DIRECTORY_SEPARATOR;

    /**
     * Join parts with the platform separator, accepting either separator in the input.
     *
     * The first part keeps its root prefix, so joining onto a UNC or drive root stays absolute.
     * Empty parts are dropped rather than producing a doubled separator, so an optional trailing
     * segment can be passed straight through without a conditional at the call site.
     */
    public static function join(string ...$parts): string
    {
        $prefix = '';
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            if ($index === 0) {
                [$prefix, $rest] = self::split($part);
                $part = $rest;
            }

            foreach (self::segments($part) as $segment) {
                $segments[] = $segment;
            }
        }

        return $prefix . implode(self::SEPARATOR, $segments);
    }

    /**
     * Rewrite every separator in a path to the platform's, preserving its root prefix.
     */
    public static function normalise(string $path): string
    {
        [$prefix, $rest] = self::split($path);

        return $prefix . implode(self::SEPARATOR, self::segments($rest));
    }

    /**
     * Split a path into its segments, treating "/" and "\" alike and dropping empties.
     *
     * The root prefix is *not* returned here -- pass a path through split() first if you need it.
     *
     * @return list<string>
     */
    public static function segments(string $path): array
    {
        $parts = preg_split('#[/\\\\]+#', $path);

        return array_values(array_filter(
            $parts === false ? [] : $parts,
            static fn (string $segment): bool => $segment !== '',
        ));
    }

    /**
     * Separate a path's root prefix from the rest.
     *
     * The prefix is returned with a trailing separator where one belongs, so `$prefix . $rest` is
     * always the original path. Recognised roots, in the order they are tested:
     *
     * | Input                    | Prefix              | Meaning        |
     * |--------------------------|---------------------|----------------|
     * | `\\server\share\a`       | `\\server\share\`   | UNC / network  |
     * | `C:\a` or `C:a`          | `C:\` or `C:`       | Windows drive  |
     * | `/a`                     | `/`                 | Unix root      |
     * | `a/b`                    | `` (empty)          | relative       |
     *
     * @return array{0: string, 1: string} The prefix and the remainder.
     */
    public static function split(string $path): array
    {
        // UNC first: it also starts with two separators, which the root test below would otherwise
        // claim, flattening the host and share into ordinary segments.
        if (preg_match('#^[/\\\\]{2}([^/\\\\]+)[/\\\\]+([^/\\\\]+)#', $path, $matches) === 1) {
            $prefix = self::SEPARATOR . self::SEPARATOR . $matches[1] . self::SEPARATOR . $matches[2] . self::SEPARATOR;

            return [$prefix, substr($path, strlen($matches[0]))];
        }

        if (preg_match('#^([a-z]:)([/\\\\]?)#i', $path, $matches) === 1) {
            // "C:rel" is drive-relative rather than drive-absolute -- a real and distinct Windows
            // shape -- so the trailing separator is only added when the input had one.
            $prefix = $matches[1] . ($matches[2] === '' ? '' : self::SEPARATOR);

            return [$prefix, substr($path, strlen($matches[0]))];
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return [self::SEPARATOR, ltrim($path, '/\\')];
        }

        return ['', $path];
    }

    /**
     * Whether `$candidate` is `$boundary` or sits beneath it.
     *
     * Separator- and root-aware on purpose: a plain `str_starts_with` places "/a/bc" inside "/a/b",
     * and a UNC boundary compared without its prefix matches unrelated local paths.
     *
     * On Windows the comparison is case-insensitive, because the filesystem is: rejecting
     * "C:\Project\src" as outside "C:\project" would be a security decision made on letter case.
     * Elsewhere it is exact -- Linux is case-sensitive, and macOS *can* be, so assuming otherwise
     * would be the unsafe direction.
     *
     * Both arguments should already be canonical; this compares strings and resolves nothing.
     */
    public static function isWithin(string $boundary, string $candidate): bool
    {
        $boundary = self::normalise($boundary);
        $candidate = self::normalise($candidate);

        if (self::SEPARATOR === '\\') {
            $boundary = mb_strtolower($boundary);
            $candidate = mb_strtolower($candidate);
        }

        if ($candidate === $boundary) {
            return true;
        }

        return str_starts_with($candidate, rtrim($boundary, self::SEPARATOR) . self::SEPARATOR);
    }

    /**
     * How many segments deep a path is, below its root prefix.
     *
     * Counted below the prefix because the prefix is the floor: "C:\project" is one level deep, not
     * two, and climbing two from it yields "." -- a relative path that then resolves against the
     * working directory rather than failing.
     */
    public static function depth(string $path): int
    {
        [, $rest] = self::split($path);

        return count(self::segments($rest));
    }

    /**
     * Whether a path is rooted -- Unix root, Windows drive, or UNC share.
     */
    public static function isAbsolute(string $path): bool
    {
        return self::split($path)[0] !== '';
    }

    /**
     * Whether a path names a UNC network share.
     *
     * Legitimate for a package installed on a network drive; never legitimate for a caller-supplied
     * relative path, where it would read from an attacker-named host.
     */
    public static function isNetworkPath(string $path): bool
    {
        return preg_match('#^[/\\\\]{2}[^/\\\\]+[/\\\\]+[^/\\\\]+#', $path) === 1;
    }
}

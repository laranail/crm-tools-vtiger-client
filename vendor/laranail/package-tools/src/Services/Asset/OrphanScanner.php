<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use SplFileInfo;

/**
 * Finds published files that nothing publishes any more.
 *
 * The gap this fills: every cleanup in this package is **destination-registry
 * driven**, so it can only remove targets something registered in the current
 * process. A package that has been uninstalled registers nothing, and its files
 * sit in `public/vendor` forever. So does the old copy of an asset whose
 * publish path changed.
 *
 * ## How it decides
 *
 * A plain set difference. Everything actually on disk under a prune root, minus
 * everything the currently-booted application would publish there.
 *
 * The expected set is built from **every** publish group, not only the ones
 * this package registered. That is the correctness hinge: Livewire, Horizon,
 * Filament and anything else publishing into `public/vendor` are legitimate
 * occupants, and a scan that only knew about laranail tags would report all of
 * them as orphans and offer to delete them.
 *
 * ## Why it is stateless
 *
 * No manifest, no ledger. A manifest would need to have existed before the
 * files did, which is exactly wrong for the case this exists to solve — the
 * files that are already there, from an install nobody has a record of. The
 * diff needs no prior state and is correct on its first run.
 */
final class OrphanScanner
{
    /**
     * A tree deeper than this is pathological; the report says it was truncated
     * rather than pretending it saw everything.
     */
    private const int MAX_DEPTH = 12;

    /**
     * Memoised `realpath()` results, keyed by lexical path.
     *
     * @var array<string, string>
     */
    private array $resolved = [];

    public function __construct(
        private readonly PublishPathGuard $guard,
        private readonly PublishTagRegistry $registry,
        private readonly int $maxDepth = self::MAX_DEPTH,
    ) {}

    /**
     * @param list<string>|null $tags narrow the expected set to these tags
     */
    public function scan(?array $tags = null): OrphanReport
    {
        $roots = $this->guard->roots();

        if ($roots === []) {
            return new OrphanReport(rootsScanned: []);
        }

        $expected = $this->expectedPaths($tags);

        $orphans = [];
        $truncated = false;
        $bytes = 0;
        $files = 0;

        foreach ($roots as $root) {
            [$found, $hitCeiling] = $this->walk($root->realPath());
            $truncated = $truncated || $hitCeiling;

            $unexpected = array_values(array_filter(
                $found,
                static fn (string $path): bool => ! isset($expected[$path]),
            ));

            foreach ($this->collapse($unexpected, $expected, $root) as $entry) {
                $orphans[] = $entry;
                $bytes += $entry->bytes;
                $files += $entry->isDirectory ? $this->countFiles($entry->path) : 1;
            }
        }

        return new OrphanReport(
            entries: $orphans,
            rootsScanned: array_map(static fn (PublishRoot $r): string => $r->path(), $roots),
            truncated: $truncated,
            fileCount: $files,
            bytes: $bytes,
        );
    }

    /**
     * Every path the booted application would publish into a prune root.
     *
     * A source that no longer exists contributes nothing, which is deliberate:
     * its destinations then fall out as orphans, and that is precisely the
     * "package uninstalled, files left behind" case.
     *
     * @param list<string>|null $tags
     * @return array<string, true>
     */
    private function expectedPaths(?array $tags): array
    {
        $groups = $tags ?? array_values(array_filter(
            ServiceProvider::publishableGroups(),
            is_string(...),
        ));

        $expected = [];

        foreach ($groups as $group) {
            /** @var array<string, string> $paths */
            $paths = ServiceProvider::pathsToPublish(null, $group);

            foreach ($paths as $source => $destination) {
                $this->expandInto($expected, $source, $this->canonicalise($destination));
            }
        }

        // Registry destinations too. A tag recorded here but absent from
        // Laravel's static — a provider that booted then had its group cleared
        // in a long-running process — should not turn its files into orphans.
        foreach ($this->registry->all() as $entry) {
            if ($tags !== null && ! in_array($entry->tag, $tags, true)) {
                continue;
            }

            foreach ($entry->paths as $source => $destination) {
                $this->expandInto($expected, $source, $this->canonicalise($destination));
            }
        }

        return $expected;
    }

    /**
     * Record a destination and, for a directory source, everything under it.
     *
     * Intermediate directories are recorded too, or a nested destination's
     * parent would look unexpected and take the whole branch with it.
     *
     * @param array<string, true> $expected
     */
    private function expandInto(array &$expected, string $source, string $destination): void
    {
        $expected[$destination] = true;

        foreach ($this->ancestorsOf($destination) as $ancestor) {
            $expected[$ancestor] = true;
        }

        if (! is_dir($source)) {
            return;
        }

        foreach (File::allFiles($source) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $path = $destination . '/' . $relative;

            $expected[$path] = true;

            foreach ($this->ancestorsOf($path) as $ancestor) {
                if (str_starts_with($ancestor, $destination)) {
                    $expected[$ancestor] = true;
                }
            }
        }
    }

    /**
     * Put a path into the same form the walk produces.
     *
     * Both sides of the diff have to be spelled the same way or nothing
     * matches, and they are not spelled the same way by default: destinations
     * come from `public_path()` and friends and are purely lexical, while the
     * walk descends from a `realpath()`-resolved root. Any application whose
     * base path sits under a symlink — every deploy that keeps a
     * `current -> releases/…` link, which is most of them — would otherwise see
     * its entire published tree reported as orphaned.
     *
     * Resolving the longest existing ancestor rather than the path itself is
     * what makes this work for destinations that do not exist yet, which is
     * exactly the interesting case.
     */
    private function canonicalise(string $path): string
    {
        $normalised = PublishRoot::normalise($path);

        if (isset($this->resolved[$normalised])) {
            return $this->resolved[$normalised];
        }

        $suffix = [];
        $current = $normalised;
        $real = false;

        while (! in_array($current, ['', '/', '.'], true)) {
            if (isset($this->resolved[$current])) {
                $real = $this->resolved[$current];

                break;
            }

            $real = realpath($current);

            if ($real !== false) {
                $real = PublishRoot::normalise($real);
                $this->resolved[$current] = $real;

                break;
            }

            $real = false;
            $suffix[] = basename($current);
            $parent = dirname($current);

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        $canonical = $real === false
            ? $normalised
            : rtrim($real . '/' . implode('/', array_reverse($suffix)), '/');

        return $this->resolved[$normalised] = $canonical;
    }

    /**
     * @return list<string>
     */
    private function ancestorsOf(string $path): array
    {
        $ancestors = [];
        $current = $path;

        while (($parent = dirname($current)) !== $current && $parent !== '/' && $parent !== '.') {
            $ancestors[] = $parent;
            $current = $parent;
        }

        return $ancestors;
    }

    /**
     * Every path under a root, without following symlinks.
     *
     * A link is recorded as a leaf and never descended: following one would
     * report the contents of somewhere outside the root as orphaned, and then
     * offer to delete them.
     *
     * @return array{0: list<string>, 1: bool}
     */
    private function walk(string $root): array
    {
        if (! is_dir($root)) {
            return [[], false];
        }

        $found = [];
        $truncated = false;
        $queue = [[$root, 0]];

        while ($queue !== []) {
            [$directory, $depth] = array_shift($queue);

            if ($depth >= $this->maxDepth) {
                $truncated = true;

                continue;
            }

            $iterator = new FilesystemIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
            );

            foreach ($iterator as $item) {
                if (! $item instanceof SplFileInfo) {
                    continue;
                }

                $path = PublishRoot::normalise($item->getPathname());
                $found[] = $path;

                if ($item->isDir() && ! $item->isLink()) {
                    $queue[] = [$item->getPathname(), $depth + 1];
                }
            }
        }

        return [$found, $truncated];
    }

    /**
     * Collapse a fully-orphaned directory into a single entry.
     *
     * Reporting 400 files individually when their whole parent directory is
     * stale is technically accurate and useless to read.
     *
     * @param list<string> $unexpected
     * @param array<string, true> $expected
     * @return list<OrphanEntry>
     */
    private function collapse(array $unexpected, array $expected, PublishRoot $root): array
    {
        sort($unexpected);

        $entries = [];
        $covered = [];

        foreach ($unexpected as $path) {
            foreach ($covered as $parent) {
                if (str_starts_with($path, $parent . '/')) {
                    continue 2;
                }
            }

            $isDirectory = is_dir($path) && ! is_link($path);

            if ($isDirectory && $this->hasExpectedDescendant($path, $expected)) {
                // Something under here is still published, so the directory
                // itself stays and only its stale children are reported.
                continue;
            }

            if ($isDirectory) {
                $covered[] = $path;
            }

            $relative = ltrim(substr($path, strlen($root->realPath())), '/');

            $entries[] = new OrphanEntry(
                // Reported in the root's own vocabulary, not the resolved one.
                // Resolution is an implementation detail of the diff; an entry
                // that came back spelled `/private/var/…` when the root was
                // configured as `/var/…` is both confusing to read and — worse
                // — a path the guard would refuse, since it does its
                // containment check lexically.
                path: $root->path() . '/' . $relative,
                relativePath: $relative,
                isDirectory: $isDirectory,
                bytes: $isDirectory ? $this->sizeOf($path) : (int) @filesize($path),
                attributedTag: $this->attribute($path),
            );
        }

        return $entries;
    }

    /**
     * @param array<string, true> $expected
     */
    private function hasExpectedDescendant(string $directory, array $expected): bool
    {
        $prefix = rtrim($directory, '/') . '/';

        return array_any(array_keys($expected), fn (string $path): bool => str_starts_with($path, $prefix));
    }

    private function attribute(string $path): ?string
    {
        foreach ($this->registry->all() as $entry) {
            foreach ($entry->destinations() as $destination) {
                $normalised = $this->canonicalise($destination);

                if ($path === $normalised || str_starts_with($path, $normalised . '/')) {
                    return $entry->tag;
                }
            }
        }

        return null;
    }

    private function sizeOf(string $directory): int
    {
        $bytes = 0;

        foreach (File::allFiles($directory) as $file) {
            $bytes += $file->getSize();
        }

        return $bytes;
    }

    private function countFiles(string $directory): int
    {
        return count(File::allFiles($directory));
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Exceptions\UnsafeAssetPath;

/**
 * The one place in this package that deletes anything.
 *
 * Every destructive path routes through {@see assertDeletable()}, so the rules
 * live in one file rather than being restated — and forgotten once — at each
 * call site. Nothing here deletes without asserting first; `delete()` calls the
 * assertion itself rather than trusting its caller to have done so.
 *
 * ## The failure this exists to prevent
 *
 * A publish helper that computed its destination as
 * `$target === '' ? public_path() : public_path($target)` and handed that to a
 * cleanup registry, which a `--force` publish then `deleteDirectory()`d — for
 * every module. One module with an empty target config was one command away
 * from deleting the entire document root.
 */
final readonly class PublishPathGuard
{
    /**
     * @param list<PublishRoot> $roots
     * @param list<string> $protected basename globs never deleted
     */
    public function __construct(
        private array $roots = [],
        private array $protected = [],
    ) {}

    /**
     * @throws UnsafeAssetPath when a configured root is not usable
     */
    public static function fromConfig(Repository $config, string $basePath): self
    {
        $prune = $config->get('laranail.package-tools.assets.prune', []);
        $prune = is_array($prune) ? $prune : [];

        $configured = is_array($prune['roots'] ?? null) ? $prune['roots'] : ['public/vendor'];
        $minDepth = is_numeric($prune['min_depth'] ?? null) ? (int) $prune['min_depth'] : 2;
        $protected = is_array($prune['protect'] ?? null) ? $prune['protect'] : ['.gitignore', '.gitkeep'];

        $roots = [];

        foreach ($configured as $root) {
            if (is_string($root) && trim($root) !== '') {
                $roots[] = PublishRoot::make($root, $basePath, $minDepth);
            }
        }

        return new self(
            $roots,
            array_values(array_filter($protected, is_string(...))),
        );
    }

    /** @return list<PublishRoot> */
    public function roots(): array
    {
        return $this->roots;
    }

    public function rootFor(string $path): ?PublishRoot
    {
        foreach ($this->roots as $root) {
            if ($root->contains($path)) {
                return $root;
            }
        }

        return null;
    }

    public function isDeletable(string $path): bool
    {
        try {
            $this->assertDeletable($path);

            return true;
        } catch (UnsafeAssetPath) {
            return false;
        }
    }

    /**
     * @throws UnsafeAssetPath
     */
    public function assertDeletable(string $path): void
    {
        if (trim($path) === '' || str_contains($path, "\0")) {
            throw UnsafeAssetPath::malformed($path);
        }

        $normalised = PublishRoot::normalise($path);

        // Checked before the containment lookup, which uses STRICT descendancy
        // and so would report the root as "not inside a root" — true, and
        // useless as a message on a destructive command.
        foreach ($this->roots as $candidate) {
            if ($normalised === $candidate->path()) {
                throw UnsafeAssetPath::isTheRootItself($path);
            }
        }

        $root = $this->rootFor($normalised);

        // No roots configured means nothing is deletable. Fail closed: an empty
        // list is far more likely to be a misconfiguration than an instruction
        // to delete from everywhere.
        if (! $root instanceof PublishRoot) {
            throw UnsafeAssetPath::notInsideRoot($path, $this->roots === []
                ? '(no roots configured)'
                : implode(', ', array_map(strval(...), $this->roots)));
        }

        foreach ($this->protected as $pattern) {
            if (fnmatch($pattern, basename($normalised))) {
                throw UnsafeAssetPath::protectedName($path, $pattern);
            }
        }

        $this->assertResolvesInsideRoot($normalised, $root);
    }

    /**
     * Delete a path, after proving it may be deleted.
     *
     * Dispatches on `is_link()` **before** anything else. `File::delete()` on a
     * directory symlink does not do what you want on every platform, and
     * `deleteDirectory()` on one would recurse into the target — deleting the
     * contents of somewhere the guard never approved.
     */
    public function delete(string $path): bool
    {
        $this->assertDeletable($path);

        if (is_link($path)) {
            return @unlink($path) || (is_dir($path) && @rmdir($path));
        }

        if (File::isDirectory($path)) {
            return File::deleteDirectory($path);
        }

        return File::exists($path) ? File::delete($path) : true;
    }

    /**
     * Confirm the path still lands inside the root once symlinks are followed.
     *
     * A symlink inside a root does not put its target inside the root, and an
     * intermediate directory can be swapped for one. `realpath()` is what
     * settles it — but only where the path exists, since a candidate for
     * deletion that is already gone is not a containment problem.
     *
     * The parent is checked too: a path may not exist while its directory does,
     * and that directory is what a swap would target.
     *
     * The leaf is exempt when it is **itself** a symlink, because `delete()`
     * unlinks those rather than following them, and unlinking never touches the
     * target. Resolving it here would refuse the one operation that removes a
     * stray link from a publish root — and leave it there permanently, since
     * every other route to deleting it goes through this same check. The parent
     * is still resolved, so an intermediate swap is still caught.
     */
    private function assertResolvesInsideRoot(string $normalised, PublishRoot $root): void
    {
        $realRoot = $root->realPath();
        $subjects = is_link($normalised) ? [dirname($normalised)] : [$normalised, dirname($normalised)];

        foreach ($subjects as $subject) {
            $real = realpath($subject);

            if ($real === false) {
                continue;
            }

            if ($real !== $realRoot && ! str_starts_with($real, rtrim($realRoot, '/') . '/')) {
                throw UnsafeAssetPath::escapingSymlink($normalised, $real, $realRoot);
            }
        }
    }
}

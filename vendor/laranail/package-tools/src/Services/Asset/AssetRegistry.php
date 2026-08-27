<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Contracts\RegistryInterface;
use Simtabi\Laranail\Package\Tools\Exceptions\UnsafeAssetPath;

/**
 * Tracks published assets and manages cleanup.
 */
class AssetRegistry implements RegistryInterface
{
    /** @var array<string, array<int, mixed>> */
    protected array $registered = [];

    /** @var array<string, array<int, mixed>> */
    protected array $cleanupTargets = [];

    /**
     * {@inheritDoc}
     */
    public function register(string $key, mixed $value, bool $shouldCleanup = false): void
    {
        if (! isset($this->registered[$key])) {
            $this->registered[$key] = [];
        }

        $this->registered[$key][] = $value;

        if ($shouldCleanup) {
            $this->cleanupTargets[$key][] = $value;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRegistered(): array
    {
        return $this->registered;
    }

    /**
     * Get registered assets for a specific tag
     *
     * @param string $tag Publish tag
     * @return array<string>
     */
    public function getByTag(string $tag): array
    {
        return $this->registered[$tag] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset($this->registered[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->registered[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(string $key): void
    {
        unset($this->registered[$key], $this->cleanupTargets[$key]);
    }

    /**
     * Delete the registered cleanup targets for a tag.
     *
     * Routed through {@see PublishPathGuard}, which is the one place in this
     * package that deletes anything. It used to call `File::deleteDirectory()`
     * directly with no containment check and no `is_link()` dispatch — the
     * exact failure the guard's own docblock describes: a registered
     * destination of `''` resolves to the document root, and one that escapes
     * with `..` resolves to wherever it points.
     *
     * A target outside every configured prune root is **skipped and reported**,
     * not deleted. Packages publish into `config/` and `database/migrations/`
     * as well as `public/vendor/`, and silently removing a published config
     * file is a worse surprise than declining to.
     *
     * @return list<string> the targets that were refused, for the caller to report
     */
    public function cleanup(string $tag): array
    {
        if (! isset($this->cleanupTargets[$tag])) {
            return [];
        }

        $guard = $this->guard();
        $refused = [];

        foreach ($this->cleanupTargets[$tag] as $target) {
            if (! File::exists($target)) {
                continue;
            }

            if (! $guard->isDeletable($target)) {
                $refused[] = $target;

                continue;
            }

            $guard->delete($target);
        }

        return $refused;
    }

    /**
     * A guard with no usable roots refuses everything, which is the right
     * outcome for a misconfigured root: cleaning nothing beats cleaning the
     * wrong thing.
     */
    private function guard(): PublishPathGuard
    {
        try {
            return PublishPathGuard::fromConfig(
                app('config'),
                app()->basePath(),
            );
        } catch (UnsafeAssetPath) {
            return new PublishPathGuard;
        }
    }

    /**
     * Check if a tag should be cleaned up
     *
     * @param string $tag Publish tag
     */
    public function shouldCleanup(string $tag): bool
    {
        return isset($this->cleanupTargets[$tag]) && (isset($this->cleanupTargets[$tag]) && $this->cleanupTargets[$tag] !== []);
    }

    /**
     * Get all cleanup targets
     *
     * @return array<string, array<string>>
     */
    public function getCleanupTargets(): array
    {
        return $this->cleanupTargets;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * What each laranail package publishes, and which of its tags the package
 * author asked to be cleaned before publishing.
 *
 * Laravel's own `ServiceProvider::$publishGroups` records tag => paths, but it
 * has no notion of which package owns a tag and no notion of cleaning. This
 * holds both.
 *
 * ## Why this exists
 *
 * A `clean: true` declaration used to be honoured by deleting the destination
 * during `boot()`. That ran on **every** console command, gated only on
 * `runningInConsole()` — so `php artisan route:list` deleted the published
 * assets of any package that had asked for a clean, and the files did not come
 * back until someone re-published. Recording the intent here instead lets it be
 * honoured at the moment publishing actually happens, which is the only moment
 * a destination should ever be removed.
 *
 * @see PackageServiceProvider::bootPackageCustomPublishes()
 */
final class PublishTagRegistry
{
    /** @var array<string, PublishTagEntry> */
    private array $entries = [];

    /**
     * Record a tag, merging into an existing entry when the tag repeats.
     *
     * @param array<string, string> $paths source => destination
     */
    public function record(string $tag, string $package, array $paths, bool $cleanable = false): void
    {
        $this->entries[$tag] = isset($this->entries[$tag])
            ? $this->entries[$tag]->merge($paths, $cleanable)
            : new PublishTagEntry($tag, $package, $paths, $cleanable);
    }

    public function get(string $tag): ?PublishTagEntry
    {
        return $this->entries[$tag] ?? null;
    }

    public function has(string $tag): bool
    {
        return isset($this->entries[$tag]);
    }

    /**
     * @return array<string, PublishTagEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return array_keys($this->entries);
    }

    /**
     * @return array<string, PublishTagEntry>
     */
    public function forPackage(string $package): array
    {
        return array_filter(
            $this->entries,
            static fn (PublishTagEntry $entry): bool => $entry->package === $package,
        );
    }

    /**
     * @return list<string>
     */
    public function packages(): array
    {
        return array_values(array_unique(
            array_map(static fn (PublishTagEntry $entry): string => $entry->package, $this->entries),
        ));
    }

    /**
     * Entries whose package asked for the destination to be cleaned first.
     *
     * @return array<string, PublishTagEntry>
     */
    public function cleanable(): array
    {
        return array_filter(
            $this->entries,
            static fn (PublishTagEntry $entry): bool => $entry->cleanable,
        );
    }

    public function isCleanable(string $tag): bool
    {
        return $this->entries[$tag]->cleanable ?? false;
    }

    public function forget(string $tag): void
    {
        unset($this->entries[$tag]);
    }

    public function flush(): void
    {
        $this->entries = [];
    }
}

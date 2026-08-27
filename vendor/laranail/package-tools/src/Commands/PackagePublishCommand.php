<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Package\Tools\Exceptions\UnsafeAssetPath;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishPathGuard;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagEntry;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;

/**
 * Publish laranail package assets, one tag or all of them.
 *
 * ## `--force` overwrites. `--clean` deletes. They are not the same flag.
 *
 * The command this replaces conflated them: `--force` meant "delete the publish
 * destinations, then republish", and it was invoked for every module in the
 * application. That conflation *is* the bug — an operator reaching for the flag
 * that means "yes, overwrite, I know" got a recursive delete they never asked
 * for.
 *
 * So `--force` does what it does in `vendor:publish` and nothing more. Deleting
 * is a separate, separately-confirmed flag, and even then only inside a
 * configured prune root.
 *
 * Publishing itself delegates to `vendor:publish` rather than reimplementing
 * it, and rather than subclassing `VendorPublishCommand` — whose `publishTag()`
 * is protected and free to change in any minor release.
 */
final class PackagePublishCommand extends Command
{
    /** @var string */
    protected $signature = 'laranail::package-tools.publish
        {--tag=*    : Publish only these tags (repeatable)}
        {--package= : Publish every tag belonging to this package}
        {--all      : Publish every known laranail publish tag}
        {--external : Publish every tag this package did not register (Livewire, Horizon, …)}
        {--list     : List the publish tags this application exposes, then exit}
        {--clean    : Delete each tag\'s destinations first (guarded, confirmed)}
        {--force    : Overwrite existing files; with --clean, skip the confirmation}
        {--dry-run  : Show what would happen and change nothing}
        {--json     : Machine-readable output for --list and --dry-run}';

    /** @var string */
    protected $description = 'Publish laranail package assets by tag, package, or all at once.';

    public function handle(PublishTagRegistry $registry): int
    {
        if ($this->option('list')) {
            return $this->listTags($registry);
        }

        $tags = $this->resolveTags($registry);

        if ($tags === []) {
            $this->error(
                $this->option('external')
                    ? 'No external publish tags found: every registered tag belongs to a laranail package.'
                    : 'Nothing to publish. Pass --tag=, --package=, --external or --all; --list shows what is available.',
            );

            return self::FAILURE;
        }

        $unknown = array_values(array_diff($tags, $this->knownTags($registry)));

        if ($unknown !== []) {
            $this->error('Unknown publish tag(s): ' . implode(', ', $unknown) . '.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->describePlan($registry, $tags);
        }

        if ($this->option('clean') && ! $this->confirmClean($tags)) {
            return self::FAILURE;
        }

        return $this->publishTags($registry, $tags);
    }

    /**
     * @param list<string> $tags
     */
    private function publishTags(PublishTagRegistry $registry, array $tags): int
    {
        $guard = $this->guard();
        $failed = 0;

        foreach ($tags as $tag) {
            if ($this->option('clean')) {
                $this->cleanDestinations($guard, $registry->get($tag));
            }

            $exit = $this->callSilently('vendor:publish', array_filter([
                '--tag' => $tag,
                '--force' => (bool) $this->option('force'),
            ]));

            if ($exit !== self::SUCCESS) {
                $this->error("Publishing [{$tag}] failed.");
                $failed++;

                continue;
            }

            $this->line("  published {$tag}");
        }

        if ($failed > 0) {
            return self::FAILURE;
        }

        $this->info('Published ' . count($tags) . ' tag(s).');

        return self::SUCCESS;
    }

    /**
     * Delete a tag's destinations, skipping anything outside a prune root.
     *
     * Skipped rather than deleted, and said out loud. A package publishes to
     * `config/` and `database/migrations/` as well as `public/vendor/`, and a
     * clean that silently removed a published config file would be a much worse
     * surprise than one that says it left it alone.
     */
    private function cleanDestinations(PublishPathGuard $guard, ?PublishTagEntry $entry): void
    {
        if (! $entry instanceof PublishTagEntry) {
            return;
        }

        foreach ($entry->destinations() as $destination) {
            try {
                $guard->assertDeletable($destination);
            } catch (UnsafeAssetPath $e) {
                $this->warn("  skipped cleaning {$destination} — {$e->getMessage()}");

                continue;
            }

            $guard->delete($destination);
            $this->line("  cleaned {$destination}");
        }
    }

    /**
     * @param list<string> $tags
     */
    private function confirmClean(array $tags): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->warn(
                'Refusing to clean in a non-interactive shell without --force. '
                . 'Nobody would have been asked.'
            );

            return false;
        }

        return $this->confirm(
            'About to DELETE the destinations of: ' . implode(', ', $tags) . '. Continue?',
            false,
        );
    }

    /**
     * @param list<string> $tags
     */
    private function describePlan(PublishTagRegistry $registry, array $tags): int
    {
        $guard = $this->guard();
        $plan = [];

        foreach ($tags as $tag) {
            $entry = $registry->get($tag);
            $destinations = $entry?->destinations() ?? [];

            $plan[] = [
                'tag' => $tag,
                'package' => $entry?->package,
                'destinations' => $destinations,
                'would_clean' => $this->option('clean')
                    ? array_values(array_filter($destinations, $guard->isDeletable(...)))
                    : [],
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($plan as $row) {
            $this->line("[dry-run] would publish {$row['tag']}");

            foreach ($row['would_clean'] as $path) {
                $this->line("            and delete {$path}");
            }
        }

        return self::SUCCESS;
    }

    private function listTags(PublishTagRegistry $registry): int
    {
        $guard = $this->guard();
        $known = $registry->all();
        $rows = [];

        foreach ($known as $tag => $entry) {
            $rows[] = [
                $tag,
                $entry->package,
                (string) count($entry->destinations()),
                $this->anyDeletable($guard, $entry) ? 'yes' : 'no',
                $entry->cleanable ? 'yes' : 'no',
            ];
        }

        // Tags this package did not register — Livewire, Horizon, anything else
        // the application publishes. Listed so `--list` answers "what is
        // publishable" rather than "what did laranail register".
        //
        // publishableGroups() already returns the tag NAMES. Wrapping it in
        // array_keys() yielded 0, 1, 2 …, every one of which failed the
        // is_string() guard — so this block rendered nothing and --list had
        // never actually shown an external tag.
        foreach (ServiceProvider::publishableGroups() as $tag) {
            if (is_string($tag) && ! isset($known[$tag])) {
                $rows[] = [$tag, '(external)', '—', '—', '—'];
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_map(static fn (PublishTagEntry $e): array => [
                    'tag' => $e->tag,
                    'package' => $e->package,
                    'destinations' => $e->destinations(),
                    'cleanable' => $e->cleanable,
                ], array_values($known)),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->warn('No publish tags are registered.');

            return self::SUCCESS;
        }

        $this->table(['Tag', 'Package', 'Destinations', 'In prune root?', 'Asked to clean?'], $rows);

        return self::SUCCESS;
    }

    private function anyDeletable(PublishPathGuard $guard, PublishTagEntry $entry): bool
    {
        return array_any($entry->destinations(), fn (string $destination): bool => $guard->isDeletable($destination));
    }

    /**
     * @return list<string>
     */
    private function resolveTags(PublishTagRegistry $registry): array
    {
        if ($this->option('all')) {
            return $this->option('external')
                ? [...$registry->tags(), ...$this->externalTags($registry)]
                : $registry->tags();
        }

        if ($this->option('external')) {
            return $this->externalTags($registry);
        }

        $package = $this->option('package');

        if (is_string($package) && $package !== '') {
            return array_keys($registry->forPackage($package));
        }

        $tags = $this->option('tag');

        return is_array($tags) ? array_values(array_filter($tags, is_string(...))) : [];
    }

    /**
     * Every publish tag some other package registered.
     *
     * Read from Laravel's own registry rather than from a list of provider
     * class names. The command this generalises hardcoded
     * `Livewire\LivewireServiceProvider` and `Laravel\Horizon\HorizonServiceProvider`,
     * which meant it published exactly the two packages someone thought of, in
     * an application that had grown others — and `class_exists` guards around
     * each so a missing one failed silently rather than being reported.
     *
     * `publishableGroups()` is what `vendor:publish --tag` itself reads, so
     * this publishes what the application actually exposes.
     *
     * @return list<string>
     */
    private function externalTags(PublishTagRegistry $registry): array
    {
        $known = $registry->all();
        $external = [];

        foreach (ServiceProvider::publishableGroups() as $tag) {
            if (is_string($tag) && ! isset($known[$tag])) {
                $external[] = $tag;
            }
        }

        sort($external);

        return $external;
    }

    /**
     * @return list<string>
     */
    private function knownTags(PublishTagRegistry $registry): array
    {
        // Same array_keys() mistake as listTags() had, and worse here: the
        // filtered list was always empty, so `--tag=livewire:assets` was
        // rejected as an unknown tag even though the application published it.
        return [
            ...$registry->tags(),
            ...array_values(array_filter(ServiceProvider::publishableGroups(), is_string(...))),
        ];
    }

    /**
     * A guard with no usable roots refuses everything, which is the correct
     * outcome for a misconfigured root — a clean that deletes nothing beats one
     * that deletes the wrong thing.
     */
    private function guard(): PublishPathGuard
    {
        try {
            return PublishPathGuard::fromConfig(
                $this->laravel->make('config'),
                $this->laravel->basePath(),
            );
        } catch (UnsafeAssetPath $e) {
            $this->warn('Prune roots are misconfigured, so nothing will be cleaned: ' . $e->getMessage());

            return new PublishPathGuard;
        }
    }
}

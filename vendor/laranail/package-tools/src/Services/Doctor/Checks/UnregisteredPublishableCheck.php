<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Reports directories that look like packages but registered no publish tags.
 *
 * ## What this catches
 *
 * An application with a `platform/modules/` or `packages/` tree adds a
 * directory, writes a provider, and forgets `->setPublishTagId()` or the
 * `hasAssets()` call. Nothing fails: the module works, and its assets simply
 * never publish. The symptom arrives later and somewhere else — a missing
 * stylesheet on one page, a config the operator "already published".
 *
 * The command this idea comes from tried to solve the same problem by scanning
 * those directories and **publishing** each one under a guessed tag name. That
 * is the wrong shape twice over: it invents a tag that may not exist, and it
 * writes files to answer a question.
 *
 * **This never publishes and never deletes.** It reads the directory listing,
 * reads the registry, and reports the difference.
 */
final readonly class UnregisteredPublishableCheck implements DoctorCheck
{
    /**
     * @param list<string> $directories absolute paths whose children are packages
     */
    public function __construct(
        private PublishTagRegistry $registry,
        private array $directories,
    ) {}

    public function name(): string
    {
        return 'publish:coverage';
    }

    public function description(): string
    {
        return 'Every package directory registered at least one publish tag';
    }

    public function run(): DoctorResult
    {
        if ($this->directories === []) {
            return DoctorResult::skip(
                'No package directories configured to scan',
                ['hint' => 'Pass the directories whose children are packages, e.g. base_path(\'platform/modules\').'],
            );
        }

        $registered = $this->registeredSlugs();
        $missing = [];
        $scanned = 0;

        foreach ($this->directories as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::directories($directory) as $path) {
                $scanned++;
                $slug = basename((string) $path);

                if (! isset($registered[strtolower($slug)])) {
                    $missing[] = $slug;
                }
            }
        }

        if ($scanned === 0) {
            return DoctorResult::skip('None of the configured directories exist');
        }

        if ($missing === []) {
            return DoctorResult::pass(sprintf('All %d package directories registered a publish tag', $scanned));
        }

        sort($missing);

        // A warning, not a failure. A directory with nothing to publish is a
        // perfectly ordinary thing — a module that is all routes and
        // controllers has no assets — so this reports a discrepancy for a human
        // to judge rather than deciding it is a defect.
        return DoctorResult::warn(
            sprintf('%d package directories registered no publish tag', count($missing)),
            [
                'directories' => $missing,
                'hint' => 'Each of these exists on disk but publishes nothing. That is fine for a package '
                    . 'with no assets; otherwise the provider is missing setPublishTagId() or a has*() call.',
            ],
        );
    }

    /**
     * Slugs that have registered at least one tag, lower-cased for comparison.
     *
     * Matched on the package's short name rather than the whole `vendor/name`,
     * because a directory is named `blog` while the package is `acme/blog`.
     *
     * @return array<string, true>
     */
    private function registeredSlugs(): array
    {
        $slugs = [];

        foreach ($this->registry->all() as $entry) {
            $package = $entry->package;

            if ($package === '') {
                continue;
            }

            $short = str_contains($package, '/') ? substr($package, (int) strrpos($package, '/') + 1) : $package;

            $slugs[strtolower($short)] = true;
            $slugs[strtolower($package)] = true;
        }

        return $slugs;
    }
}

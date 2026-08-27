<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Simtabi\Laranail\Package\Tools\Exceptions\UnsafeAssetPath;
use Simtabi\Laranail\Package\Tools\Services\Asset\OrphanEntry;
use Simtabi\Laranail\Package\Tools\Services\Asset\OrphanReport;
use Simtabi\Laranail\Package\Tools\Services\Asset\OrphanScanner;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishPathGuard;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;

/**
 * Report — and optionally remove — published files nothing publishes any more.
 *
 * **It reports by default and deletes only when asked.** A command whose
 * default action is deleting files it inferred were unwanted is one wrong
 * inference away from an incident, and the inference here is a set difference
 * over a booted application's state: a package that failed to boot publishes
 * nothing, and everything it ever published looks orphaned.
 *
 * So `--prune` is the delete verb, `--force` skips the confirmation, production
 * refuses without `--force`, and a run that would remove more than
 * `max_deletions` aborts before removing anything.
 */
final class PackageAssetsPruneCommand extends Command
{
    /** @var string */
    protected $signature = 'laranail::package-tools.assets-prune
        {--tag=*  : Limit the expected set to these tags}
        {--prune  : Actually delete the orphans (default: report only)}
        {--force  : Skip confirmation and allow running in production}
        {--strict : Exit non-zero when orphans exist, for a CI gate}
        {--json   : Emit the report as JSON}';

    /** @var string */
    protected $description = 'Find published files that no package publishes any more.';

    public function handle(PublishTagRegistry $registry): int
    {
        try {
            $guard = PublishPathGuard::fromConfig(
                $this->laravel->make('config'),
                $this->laravel->basePath(),
            );
        } catch (UnsafeAssetPath $e) {
            $this->error('Prune roots are misconfigured: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($guard->roots() === []) {
            $this->warn('No prune roots are configured, so there is nothing to scan.');

            return self::SUCCESS;
        }

        $tags = $this->option('tag');
        $tags = is_array($tags) && $tags !== []
            ? array_values(array_filter($tags, is_string(...)))
            : null;

        $report = (new OrphanScanner($guard, $registry))->scan($tags);

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($report);
        }

        if ($report->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('prune')) {
            if (! $this->option('json')) {
                $this->line('');
                $this->comment('Nothing was deleted. Re-run with --prune to remove these.');
            }

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        return $this->prune($guard, $report);
    }

    private function prune(PublishPathGuard $guard, OrphanReport $report): int
    {
        $ceiling = $this->maxDeletions();

        // Checked before anything is removed. A prune that wants to delete four
        // thousand files is a misconfiguration, and finding that out halfway
        // through is finding it out too late.
        if ($ceiling > 0 && $report->count() > $ceiling) {
            $this->error(
                "Refusing to delete {$report->count()} entries, over the {$ceiling} limit. "
                . 'Raise package-tools.assets.prune.max_deletions if this is expected.'
            );

            return self::FAILURE;
        }

        if ($report->truncated) {
            $this->warn('The scan hit its depth ceiling, so this is not the whole picture.');
        }

        if (! $this->confirmPrune($report)) {
            return self::FAILURE;
        }

        $deleted = 0;

        foreach ($report->entries as $entry) {
            try {
                $guard->delete($entry->path);
            } catch (UnsafeAssetPath $e) {
                $this->warn("  skipped {$entry->path} — {$e->getMessage()}");

                continue;
            }

            $this->line("  deleted {$entry->relativePath}");
            $deleted++;
        }

        $this->info("Pruned {$deleted} entr(y|ies).");

        return self::SUCCESS;
    }

    private function confirmPrune(OrphanReport $report): bool
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to prune in production without --force.');

            return false;
        }

        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->warn('Refusing to prune in a non-interactive shell without --force. Nobody would have been asked.');

            return false;
        }

        return $this->confirm("About to DELETE {$report->count()} orphaned entr(y|ies). Continue?", false);
    }

    private function render(OrphanReport $report): void
    {
        if ($report->isEmpty()) {
            $this->info('No orphaned published files found.');

            return;
        }

        $this->table(
            ['Path', 'Kind', 'Size', 'Probably from'],
            array_map(fn (OrphanEntry $e): array => [
                $e->relativePath,
                $e->isDirectory ? 'directory' : 'file',
                $this->humanBytes($e->bytes),
                $e->attributedTag ?? '—',
            ], $report->entries),
        );

        $this->line(
            "{$report->count()} orphaned entr(y|ies), {$report->fileCount} file(s), "
            . $this->humanBytes($report->bytes) . '.'
        );

        if ($report->truncated) {
            $this->warn('The scan hit its depth ceiling, so there may be more than this.');
        }
    }

    private function maxDeletions(): int
    {
        $value = $this->laravel->make('config')->get('laranail.package-tools.assets.prune.max_deletions', 500);

        return is_numeric($value) ? (int) $value : 500;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return round($size, 1) . ' ' . $units[$index];
    }
}

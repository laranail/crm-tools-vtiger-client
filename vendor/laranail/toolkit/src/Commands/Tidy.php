<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Commands;

use Illuminate\Console\ConfirmableTrait;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Toolkit\Enums\LogLevel;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface;
use Simtabi\Laranail\Toolkit\Traits\FilePathGuard;
use SplFileInfo;
use Throwable;

/**
 * Unified, security-hardened maintenance/cleanup command.
 *
 * Replaces the legacy `Laravel\Commands\TidyCommand`, which deleted files under
 * `sys_get_temp_dir()` and other roots with no containment check (a symlink in a
 * swept dir could redirect a delete anywhere) and ran `migrate:fresh` as part of
 * `all`. This rewrite:
 *
 *  - confines EVERY file deletion to `storage_path()` (resolved real path) with
 *    {@see FilePathGuard} + a realpath-containment check that rejects `..` and
 *    symlink escapes;
 *  - keeps the destructive `db` action (`migrate:fresh`) OUT of `all`, gated
 *    behind --force AND the production-safety `confirmToProceed()`, and a no-op
 *    under --dry-run;
 *  - refuses an unfiltered sweep of the roots that hold user files — see
 *    {@see USER_DATA_ACTIONS} and {@see maySweepUnfiltered()};
 *  - injects its collaborators (no facades in the core logic).
 *
 * The containment guard answers "can this delete something outside
 * `storage_path()`", which is not the same question as "should this delete
 * this". Every root swept by the `storage` action is inside storage and passes
 * containment; they are also where user uploads live.
 */
class Tidy extends Command
{
    use ConfirmableTrait;
    use FilePathGuard;
    use SupportsNamespacedNames;

    /** @var list<string> */
    protected array $commandAliases = ['tidy'];

    protected $signature = 'laranail::toolkit.tidy
        {action? : What to tidy (cache|logs|temp|storage|db|all) — defaults to all}
        {--days= : Only delete files older than this many days}
        {--size= : Only delete files larger than this many MB}
        {--seed : (db) also run db:seed after migrate:fresh}
        {--optimize : (cache) also run optimize:clear}
        {--dry-run : Show what would be removed without deleting anything}
        {--unfiltered : (storage) sweep user files with no age/size filter — refused in production}
        {--force : Skip confirmation prompts (required for the db action)}';

    protected $description = 'Unified, path-confined maintenance: cache|logs|temp|storage|db|all';

    /** Storage-relative roots swept by each file-deleting action. */
    private const ROOTS = [
        'logs' => ['logs'],
        'temp' => ['app/temp', 'app/tmp', 'framework/cache/data'],
        'storage' => ['app/public', 'app/uploads', 'app/exports'],
    ];

    /**
     * Actions whose roots hold data the application cannot regenerate.
     *
     * `logs` and `temp` sweep log files and `framework/cache/data` — losing
     * those costs nothing, which is the whole point of the command. `storage`
     * sweeps `app/public` (the disk behind `storage:link`), `app/uploads` and
     * `app/exports`: user uploads. With no `--days`/`--size` every file matched,
     * and `--force` skipped the prompt, so `tidy storage --force` — and
     * `tidy all --force`, which used to sweep the same roots — deleted every
     * file a user had ever uploaded, in one non-interactive command with a name
     * that reads like housekeeping.
     *
     * @var list<string>
     */
    private const USER_DATA_ACTIONS = ['storage'];

    private int $filesProcessed = 0;

    private int $spaceFreed = 0;

    public function __construct(
        private readonly FileServiceInterface $files,
        private readonly CacheRepositoryInterface $cache,
        private readonly LoggerServiceInterface $log,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $argument = $this->argument('action');
        $action = is_string($argument) && $argument !== '' ? $argument : 'all';

        // Wire SIGTERM/SIGINT graceful-stop handlers (no-op without ext-pcntl)
        // so the file sweep can stop between files on Ctrl-C. Time the whole run
        // so the completion summary carries a real execution-time figure.
        $this->services->signals()->setupSignalHandling();
        $this->services->performance()->startTimer();
        $this->services->metadata()->addMany([
            'action' => $action,
            'dry_run' => $this->isDryRun(),
        ]);

        $result = match ($action) {
            'cache' => $this->tidyCache(),
            'logs', 'temp', 'storage' => $this->tidyFiles($action),
            'db' => $this->tidyDatabase(),
            'all' => $this->tidyAll(),
            default => $this->invalidAction($action),
        };

        $this->logSummary($action, $result);

        return $result;
    }

    /**
     * Record a structured completion summary (files processed, bytes freed,
     * execution time) via the logger service after every run.
     */
    private function logSummary(string $action, int $exitCode): void
    {
        $performance = $this->services->performance();
        $performance->endTimer();

        $this->services->metadata()->addMany([
            'files_processed' => $this->filesProcessed,
            'space_freed' => $this->services->display()->formatBytes($this->spaceFreed),
        ]);

        $this->services->logger()->logCompletion($exitCode, [
            'execution_time' => $performance->getFormattedExecutionTime(),
        ], $this->services->metadata()->all());
    }

    // -----------------------------------------------------------------------
    // cache
    // -----------------------------------------------------------------------

    private function tidyCache(): int
    {
        $writer = $this->consoleWriter();

        if ($this->isDryRun()) {
            $writer->info('[dry-run] Would flush the application cache' . ($this->boolOption('optimize') ? ' and run optimize:clear' : '') . '.');

            return self::SUCCESS;
        }

        if (!$this->confirmAction('Flush the application cache?')) {
            return self::SUCCESS;
        }

        $this->cache->forget('__laranail_tidy_probe__');

        try {
            // Flush via the framework's own command so every store/driver is
            // honoured; the injected cache repo is exercised above for DI parity.
            $this->call('cache:clear');
        } catch (Throwable $e) {
            $this->log->error('Cache flush failed during tidy', ['error' => $e->getMessage()]);
        }

        if ($this->boolOption('optimize')) {
            $this->call('optimize:clear');
        }

        $writer->success('Cache tidied.');

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // logs / temp / storage (path-confined deletion)
    // -----------------------------------------------------------------------

    private function tidyFiles(string $action, bool $confirm = true): int
    {
        $this->filesProcessed = 0;
        $this->spaceFreed = 0;

        $base = realpath(storage_path());

        if ($base === false) {
            $this->consoleWriter()->error('storage_path() does not resolve — nothing to tidy.');

            return self::FAILURE;
        }

        if (!$this->maySweepUnfiltered($action)) {
            return self::FAILURE;
        }

        // The `all` action confirms once up front, so its per-root sweeps skip
        // the prompt (passing $confirm = false) to avoid re-asking per action.
        if ($confirm && !$this->isDryRun() && !$this->confirmAction("Clean {$action} files under storage?")) {
            return self::SUCCESS;
        }

        $signals = $this->services->signals();

        foreach (self::ROOTS[$action] as $relative) {
            // Stop sweeping further roots if a termination signal arrived.
            // shouldKeepRunning() defaults true (and without pcntl), so a normal
            // run sweeps every root.
            if (!$signals->shouldKeepRunning()) {
                break;
            }

            $this->sweep($base, $relative);
        }

        $verb = $this->isDryRun() ? 'Would free' : 'Freed';
        $this->consoleWriter()->success(sprintf(
            '%s: %d file(s), %s%s.',
            $this->isDryRun() ? '[dry-run] ' . $verb : $verb,
            $this->filesProcessed,
            $this->files->formatFileSize($this->spaceFreed),
            $this->isDryRun() ? ' (nothing deleted)' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Sweep one storage-relative directory, deleting (or previewing) the files
     * that pass the age/size filters. Each candidate is realpath-resolved and
     * proven to sit inside the swept root before any delete — a `..` path or a
     * symlink pointing outside storage is skipped, never followed.
     */
    private function sweep(string $base, string $relative): void
    {
        if (!$this->isSafePath($relative)) {
            return;
        }

        $root = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($root === false || !is_dir($root) || !$this->isWithin($root, $base)) {
            return;
        }

        $cutoff = $this->ageCutoff();
        $minBytes = $this->minBytes();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $signals = $this->services->signals();

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            // Signal-safe sweep: stop deleting mid-directory on Ctrl-C/SIGTERM.
            // Defaults true (and without pcntl), so a normal run is unaffected.
            if (!$signals->shouldKeepRunning()) {
                break;
            }

            if (!$file->isFile()) {
                continue;
            }

            $real = realpath($file->getPathname());

            // Containment re-check on the resolved path: a symlink that escapes
            // the swept root is never deleted.
            if ($real === false || !$this->isWithin($real, $root)) {
                continue;
            }

            if (str_ends_with($file->getFilename(), '.gitignore')) {
                continue;
            }

            $size = $file->getSize();

            if (!$this->matchesFilters($real, $size, $cutoff, $minBytes)) {
                continue;
            }

            $this->filesProcessed++;

            if ($this->isDryRun()) {
                $this->consoleWriter()->line("  <fg=gray>would delete</> {$real}");

                continue;
            }

            if (@unlink($real)) {
                $this->spaceFreed += $size;
            }
        }
    }

    private function matchesFilters(string $path, int $size, ?int $cutoff, ?int $minBytes): bool
    {
        // With no filters, every (non-gitignore) file qualifies.
        if ($cutoff === null && $minBytes === null) {
            return true;
        }

        // Each supplied filter must pass — with both `--days` and `--size` a file
        // must be older AND larger, so a huge but fresh file is not swept.
        if ($cutoff !== null) {
            $mtime = @filemtime($path);

            if ($mtime === false || $mtime >= $cutoff) {
                return false;
            }
        }

        if ($minBytes !== null && $size < $minBytes) {
            return false;
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // db (migrate:fresh — excluded from all, hard-gated)
    // -----------------------------------------------------------------------

    private function tidyDatabase(): int
    {
        $writer = $this->consoleWriter();

        if ($this->isDryRun()) {
            $writer->info('[dry-run] Would run migrate:fresh' . ($this->boolOption('seed') ? ' --seed' : '') . ' (skipped in dry-run).');

            return self::SUCCESS;
        }

        if (!$this->boolOption('force')) {
            $writer->error('The db action runs migrate:fresh and DROPS ALL TABLES — re-run with --force.');

            return self::FAILURE;
        }

        // Production-safety prompt (no-op when --force is honoured outside prod).
        if (!$this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);

        if ($this->boolOption('seed')) {
            $this->call('db:seed', ['--force' => true]);
        }

        $writer->success('Database refreshed.');

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // all (cache + file roots; NEVER db)
    // -----------------------------------------------------------------------

    /**
     * `all` sweeps the regenerable roots unconditionally and the user-file
     * roots only when scoped.
     *
     * Skipping rather than refusing, because `logs` and `temp` are what `all`
     * is for and failing the whole run over `storage` would make the useful
     * part unreachable. The skip is printed — an omission this consequential
     * should not be inferred from a file count.
     */
    private function tidyAll(): int
    {
        if (!$this->isDryRun() && !$this->confirmAction('Tidy cache, logs, temp and storage? (the db action is excluded.)')) {
            return self::SUCCESS;
        }

        $this->tidyCacheQuietly();

        $signals = $this->services->signals();
        $skipped = [];

        foreach (['logs', 'temp', 'storage'] as $action) {
            if (!$signals->shouldKeepRunning()) {
                break;
            }

            if (in_array($action, self::USER_DATA_ACTIONS, true) && !$this->maySweepUnfiltered($action)) {
                $skipped[] = $action;

                continue;
            }

            $this->tidyFiles($action, confirm: false);
        }

        $this->consoleWriter()->success(sprintf(
            'All tidied (db excluded — run the db action explicitly)%s.',
            $skipped === [] ? '' : ', ' . implode(' and ', $skipped) . ' skipped',
        ));

        return self::SUCCESS;
    }

    private function tidyCacheQuietly(): void
    {
        if ($this->isDryRun()) {
            $this->consoleWriter()->info('[dry-run] Would flush the application cache.');

            return;
        }

        try {
            $this->call('cache:clear');
        } catch (Throwable $e) {
            $this->log->log(LogLevel::Error, 'Cache flush failed during tidy all', ['error' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------------------
    // filters / helpers
    // -----------------------------------------------------------------------

    private function ageCutoff(): ?int
    {
        $days = $this->option('days');

        if (!is_string($days) || trim($days) === '') {
            return null;
        }

        return time() - (max(0, (int) $days) * 86400);
    }

    private function minBytes(): ?int
    {
        $size = $this->option('size');

        if (!is_string($size) || trim($size) === '') {
            return null;
        }

        return max(0, (int) $size) * 1024 * 1024;
    }

    private function isDryRun(): bool
    {
        return $this->boolOption('dry-run');
    }

    /**
     * Refuse an unfiltered sweep of the roots that hold user files.
     *
     * `--force` is habitual — it is in every CI invocation and most of this
     * command's own test suite — so it cannot be what stands between a
     * maintenance command and every user upload. The gate is therefore a flag
     * that exists for nothing else and cannot be typed by muscle memory.
     *
     * A filter makes the sweep intentional on its own: `--days=30` deletes what
     * is stale, `--size=100` what is oversized, and both together only what is
     * stale AND oversized. Those pass unchanged. What is refused is the
     * no-filter case, which matches every file in `app/public`.
     *
     * In production the acknowledgement is not offered at all. There is no
     * legitimate reason to empty a production public disk through a housekeeping
     * command, and `--force` bypasses Laravel's own `confirmToProceed()` by
     * design, so a prompt would not have been a gate either.
     */
    private function maySweepUnfiltered(string $action): bool
    {
        if (!in_array($action, self::USER_DATA_ACTIONS, true)) {
            return true;
        }

        if ($this->ageCutoff() !== null || $this->minBytes() !== null) {
            return true;
        }

        $roots = implode(', ', array_map(
            static fn (string $relative): string => "storage/{$relative}",
            self::ROOTS[$action],
        ));

        $writer = $this->consoleWriter();

        if ($this->getLaravel()->environment('production')) {
            $writer->error("Refusing to sweep {$roots} unfiltered in production — these hold user files.");
            $writer->line('  Pass <fg=yellow>--days=</> or <fg=yellow>--size=</> to delete only what is stale or oversized.');

            return false;
        }

        if (!$this->boolOption('unfiltered')) {
            $writer->error("Refusing to sweep {$roots} with no filter — these hold user files, and every one would match.");
            $writer->line('  Pass <fg=yellow>--days=30</> or <fg=yellow>--size=100</> to scope it,');
            $writer->line('  or <fg=yellow>--unfiltered</> to delete all of it anyway (never in production).');

            return false;
        }

        return true;
    }

    private function boolOption(string $key): bool
    {
        return $this->option($key) === true;
    }

    private function confirmAction(string $message): bool
    {
        if ($this->boolOption('force')) {
            return true;
        }

        // Routed through the interaction service: Laravel Prompts on a TTY, and
        // the default (false) in non-interactive mode — a piped/CI run never
        // silently proceeds with a destructive delete.
        return $this->services->interaction()->confirmAction($message, false);
    }

    private function isWithin(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $path === rtrim($root, DIRECTORY_SEPARATOR) || str_starts_with($path, $root);
    }

    private function invalidAction(string $action): int
    {
        $this->consoleWriter()->error("Invalid action [{$action}]. Available: cache, logs, temp, storage, db, all.");

        return self::FAILURE;
    }
}

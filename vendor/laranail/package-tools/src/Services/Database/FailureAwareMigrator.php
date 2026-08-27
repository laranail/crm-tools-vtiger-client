<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Override;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;
use Throwable;

/**
 * A drop-in {@see Migrator} that reports the full package-action lifecycle
 * for every migration it runs — Laravel itself dispatches no
 * migration-failure event, so a failed `up`/`down` would otherwise be
 * invisible to the reporter.
 *
 * It overrides the per-migration {@see Migrator::runUp()} /
 * {@see Migrator::runDown()} (rather than the batch-level runPending /
 * rollback) so both the specific migration name AND the exception are
 * captured in one place. `--pretend` runs are passed straight through
 * (nothing actually executes, so there is no lifecycle to report).
 *
 * Wired via `app()->extend('migrator', …)` only when this migrator is the
 * sole/first decorator (see {@see PackageToolsServiceProvider});
 * when another package already subclassed the migrator, the conflict-free
 * {@see MigrationFailureDetector} is used instead so we never clobber them.
 */
final class FailureAwareMigrator extends Migrator
{
    public function __construct(
        private readonly PackageActionReporter $reporter,
        MigrationRepositoryInterface $repository,
        Resolver $resolver,
        Filesystem $files,
        ?Dispatcher $dispatcher = null,
    ) {
        parent::__construct($repository, $resolver, $files, $dispatcher);
    }

    /**
     * @param string $file
     * @param int $batch
     * @param bool $pretend
     */
    #[Override]
    protected function runUp($file, $batch, $pretend): void
    {
        if ($pretend) {
            parent::runUp($file, $batch, $pretend);

            return;
        }

        $this->observe($file, 'up', function () use ($file, $batch, $pretend): void {
            parent::runUp($file, $batch, $pretend);
        });
    }

    /**
     * @param string $file
     * @param object $migration
     * @param bool $pretend
     */
    #[Override]
    protected function runDown($file, $migration, $pretend): void
    {
        if ($pretend) {
            parent::runDown($file, $migration, $pretend);

            return;
        }

        $this->observe($file, 'down', function () use ($file, $migration, $pretend): void {
            parent::runDown($file, $migration, $pretend);
        });
    }

    /**
     * @param Closure(): void $run
     */
    private function observe(string $file, string $direction, Closure $run): void
    {
        $name = $this->getMigrationName($file);

        $this->reporter->migrationStarting($name, $direction);
        $start = microtime(true);

        try {
            $run();
        } catch (Throwable $e) {
            $this->reporter->migrationFailed($name, $direction, $e);

            throw $e;
        }

        $this->reporter->migrationSucceeded($name, $direction, (microtime(true) - $start) * 1000);
    }
}

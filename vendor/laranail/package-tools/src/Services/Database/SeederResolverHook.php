<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

/**
 * Runs every registered package seeder bundle when the host app's ROOT
 * seeder resolves — i.e. a plain `php artisan db:seed`.
 *
 * The hook binds ONLY to exact root-seeder classes
 * (`Database\Seeders\DatabaseSeeder` plus any FQCNs listed in
 * `package-tools.seeders.root_seeders`). It deliberately does NOT bind to
 * the abstract `Seeder` type: a type-wide callback fired for
 * `db:seed --class=SomeSpecificSeeder`, for web-request resolutions, and
 * even for the executor's own `make()` calls — running every package's
 * seeders as a side effect. Root-only binding kills all three footguns.
 *
 * Bundles already executed this process (see {@see SeederAutorun}'s ledger)
 * are skipped, so `migrate --seed` never double-runs an autorun bundle.
 */
final class SeederResolverHook
{
    public const string DEFAULT_ROOT_SEEDER = 'Database\\Seeders\\DatabaseSeeder';

    private bool $attached = false;

    public function __construct(
        private readonly Application $app,
        private readonly SeederRegistry $registry,
        private readonly SeederExecutor $executor,
        private readonly SeederAutorun $autorun,
    ) {}

    /**
     * Attach resolving callbacks for the given root seeders (defaults to
     * `Database\Seeders\DatabaseSeeder` + the `root_seeders` config list).
     * Idempotent — repeated calls never stack duplicate listeners.
     */
    public function attach(string ...$rootSeeders): self
    {
        if ($this->attached) {
            return $this;
        }
        $this->attached = true;

        if ($rootSeeders === []) {
            $configured = config('laranail.package-tools.seeders.root_seeders', []);
            $rootSeeders = [self::DEFAULT_ROOT_SEEDER, ...(is_array($configured) ? $configured : [])];
        }

        $listener = function (): void {
            $this->runPendingBundles();
        };

        foreach (array_unique($rootSeeders) as $rootSeeder) {
            $this->app->resolving($rootSeeder, $listener);
        }

        return $this;
    }

    public function isAttached(): bool
    {
        return $this->attached;
    }

    /**
     * Execute every registered bundle not already run this process, mark
     * them in the shared ledger, and surface failures on the console
     * (db:seed previously exited 0 with silent failures).
     */
    private function runPendingBundles(): void
    {
        $scoped = new SeederRegistry;
        $keys = [];

        foreach ($this->registry->all() as $key => $bundle) {
            if ($this->autorun->hasExecuted($key)) {
                continue;
            }

            $scoped->registerBundle($bundle);
            $keys[] = $key;
        }

        if ($keys === []) {
            return;
        }

        $this->autorun->markExecuted(...$keys);

        try {
            $stats = $this->executor->run($scoped);
        } catch (Throwable $e) {
            $this->consoleFormatter()?->writeError("Package seeding failed: {$e->getMessage()}");

            // Per-seeder failures already surface through the executor; this
            // catch is the batch-level safety net (an executor-level throw)
            // that db:seed previously exited 0 on, silently.
            $this->app->make(PackageActionReporter::class)->seederFailed(
                'db:seed',
                null,
                $e,
                context: ['bundles' => $keys, 'trigger' => 'resolver'],
                mode: SeederExecutionMode::Inline,
            );

            return;
        }

        if ($stats->hasFailures()) {
            $this->consoleFormatter()?->writeWarning($stats->getSummary());

            foreach ($stats->errors as $error) {
                $this->consoleFormatter()?->writeError(sprintf(
                    '  %s: %s',
                    $error['class'],
                    $error['message'],
                ));
            }
        }
    }

    private function consoleFormatter(): ?SeederConsoleFormatterInterface
    {
        if (! $this->app->runningInConsole()) {
            return null;
        }

        try {
            $formatter = $this->app->make(SeederConsoleFormatterInterface::class);
            $formatter->setOutput(new OutputStyle(new ArrayInput([]), new ConsoleOutput));

            return $formatter;
        } catch (Throwable) {
            return null;
        }
    }
}

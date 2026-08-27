<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationStarted;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;

/**
 * Event-based, conflict-free migration-lifecycle reporter — the fallback
 * used when another package has ALREADY decorated the migrator, so
 * rebuilding it as a {@see FailureAwareMigrator} would clobber theirs.
 *
 * It never touches the migrator: it listens to the per-migration
 * `MigrationStarted` / `MigrationEnded` events (which carry the real
 * migration name + direction) to emit started/succeeded, and flushes any
 * still-in-flight migration as a failure on app termination — Laravel fires
 * `MigrationStarted` but never `MigrationEnded` when a migration throws, so
 * a lingering in-flight entry IS the failure signal. Best-available
 * fidelity (real name, generic exception) without breaking another
 * package's decoration.
 *
 * Only ever attached when {@see FailureAwareMigrator} is NOT active, so the
 * two can never double-report.
 */
final class MigrationFailureDetector
{
    /** @var array{name: string, direction: string, start: float}|null */
    private ?array $inFlight = null;

    private bool $registered = false;

    public function __construct(private readonly PackageActionReporter $reporter) {}

    public function register(Dispatcher $events, Application $app): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $events->listen(MigrationStarted::class, function (MigrationStarted $event): void {
            $this->onStarted($event);
        });
        $events->listen(MigrationEnded::class, function (MigrationEnded $event): void {
            $this->onEnded($event);
        });

        // A migration that threw fired Started but never Ended; on shutdown
        // a lingering in-flight entry is reported as the failure.
        $app->terminating(function (): void {
            $this->flush();
        });
    }

    private function onStarted(MigrationStarted $event): void
    {
        // A migration starting while another is still in flight means the
        // previous one never emitted MigrationEnded — i.e. it failed. Report
        // that failure now (flush() is a no-op when nothing is in flight), so
        // a second migration in the same process can never swallow it.
        $this->flush();

        $name = $this->nameOf($event);
        $direction = is_string($event->method) ? $event->method : 'up';

        $this->inFlight = ['name' => $name, 'direction' => $direction, 'start' => microtime(true)];

        $this->reporter->migrationStarting($name, $direction);
    }

    private function onEnded(MigrationEnded $event): void
    {
        $name = $this->nameOf($event);
        $direction = is_string($event->method) ? $event->method : 'up';
        $durationMs = $this->inFlight !== null ? (microtime(true) - $this->inFlight['start']) * 1000 : null;

        $this->inFlight = null;

        $this->reporter->migrationSucceeded($name, $direction, $durationMs);
    }

    private function flush(): void
    {
        if ($this->inFlight === null) {
            return;
        }

        $inFlight = $this->inFlight;
        $this->inFlight = null;

        $this->reporter->migrationFailed(
            $inFlight['name'],
            $inFlight['direction'],
            new RuntimeException("Migration '{$inFlight['name']}' did not complete (failed or interrupted)."),
        );
    }

    /**
     * The migration name carried by the event (e.g.
     * `2024_01_01_000000_create_users_table`), falling back to the migration
     * object's class when absent.
     */
    private function nameOf(MigrationStarted|MigrationEnded $event): string
    {
        if (is_string($event->name) && $event->name !== '') {
            return $event->name;
        }

        return is_object($event->migration) ? $event->migration::class : 'migration';
    }
}

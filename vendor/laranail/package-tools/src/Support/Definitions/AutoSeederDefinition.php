<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Seeder;

use function Illuminate\Support\enum_value;

use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Contracts\CronExpressible;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;
use Simtabi\Laranail\Package\Tools\Enums\Environment;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederPathDiscoverer;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\TimeOfDay;

/**
 * a package's seeder bundle for db:seed-time execution: an explicit,
 * order-guaranteed list, or discovery over a directory — either way
 * combinable with an ignore list. gating uses the shared ConfigGate.
 *
 * @implements Arrayable<string, mixed>
 */
final class AutoSeederDefinition implements Arrayable, Jsonable, JsonSerializable
{
    /** @var list<class-string<Seeder>> */
    private array $seeders = [];

    /** @var list<class-string<Seeder>> */
    private array $ignored = [];

    private ?string $discoveryPath = null;

    private bool $recursiveDiscovery = false;

    private ?string $namespace = null;

    private ?ConfigGate $gate = null;

    private ?int $priority = null;

    private bool $autorun = false;

    private bool $stopOnFailure = false;

    /** @var list<string> */
    private array $autorunEnvironments = [];

    private bool $background = false;

    private ?string $queue = null;

    private ?string $queueConnection = null;

    private bool $notify = true;

    private Cadence|CronExpressible|string|Closure|null $cadence = null;

    private ?int $overlapExpiresAt = null;

    /** @var array<string, mixed> */
    private array $options = [];

    private function __construct(
        private readonly string $key,
    ) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    /**
     * explicit list — execution order is the array order. empty (or never
     * called) switches to discovery mode over the package's seeders dir.
     *
     * @param list<class-string<Seeder>> $seeders
     */
    public function seeders(array $seeders = []): self
    {
        $this->seeders = array_values($seeders);

        return $this;
    }

    /**
     * discovery-source override; without it, discovery falls back to the
     * package's database/seeders directory at boot. `$recursive` descends
     * into nested directories.
     */
    public function discoverIn(string $path, bool $recursive = false): self
    {
        $this->discoveryPath = $path;
        $this->recursiveDiscovery = $recursive;

        return $this;
    }

    /**
     * exclusion, applied to both explicit and discovered lists.
     *
     * @param list<class-string<Seeder>> $seeders
     */
    public function ignoreSeeders(array $seeders): self
    {
        $this->ignored = array_values($seeders);

        return $this;
    }

    public function inNamespace(?string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Append seeders to the explicit list without replacing it — the
     * incremental counterpart to seeders(). Duplicates are ignored.
     *
     * @param class-string<Seeder> ...$seeders
     */
    public function addSeeders(string ...$seeders): self
    {
        $this->seeders = array_values(array_unique([...$this->seeders, ...$seeders]));

        return $this;
    }

    /**
     * Opt in to automatic execution after a successful `php artisan
     * migrate` run. Seeders NEVER run on their own without this flag; even
     * with it, the SeederAutorun safety gates apply (console-only, config
     * kill-switches, environment gate, once per process).
     */
    public function autorunAfterMigrations(bool $autorun = true): self
    {
        $this->autorun = $autorun;

        return $this;
    }

    /**
     * Alias of autorunAfterMigrations().
     */
    public function autorunNow(bool $autorun = true): self
    {
        return $this->autorunAfterMigrations($autorun);
    }

    /**
     * Restrict autorun to these environments. A non-empty list REPLACES
     * the production config gate for this bundle — the author consciously
     * chose where it may run.
     */
    public function autorunInEnvironments(Environment|BackedEnum|string ...$environments): self
    {
        $this->autorunEnvironments = array_values(array_map(
            static fn (Environment|BackedEnum|string $environment): string => (string) enum_value($environment),
            $environments,
        ));

        return $this;
    }

    /**
     * Skip the bundle's remaining seeders after the first failure.
     */
    public function stopOnFailure(bool $stop = true): self
    {
        $this->stopOnFailure = $stop;

        return $this;
    }

    /**
     * Execute this bundle via a queued job instead of inline — for large
     * datasets that would block a request/console session. The job carries
     * only the bundle key; the worker's own boot re-registers the bundle.
     */
    public function runsInBackground(bool $background = true): self
    {
        $this->background = $background;

        return $this;
    }

    /**
     * Alias of runsInBackground().
     */
    public function queued(bool $background = true): self
    {
        return $this->runsInBackground($background);
    }

    /**
     * Queue name and/or connection for background execution. Accepts any
     * backed enum (your own AppQueue::Seeding, the shipped QueueConnection
     * cases) or a raw string; null defers to package-tools.seeders.queue.*.
     */
    public function onQueue(BackedEnum|string|null $queue = null, BackedEnum|string|null $connection = null): self
    {
        $this->queue = $queue === null ? null : (string) enum_value($queue);
        $this->queueConnection = $connection === null ? null : (string) enum_value($connection);

        return $this->runsInBackground();
    }

    /**
     * Schedule this bundle daily at the given time — sugar for
     * `cadence("dailyAt:HH:MM")`. Last cadence call wins.
     */
    public function scheduledAt(TimeOfDay|string $time): self
    {
        $timeOfDay = $time instanceof TimeOfDay ? $time : TimeOfDay::parse($time);

        return $this->cadence('dailyAt:' . $timeOfDay->format24());
    }

    /**
     * Schedule recurring execution — the same union the scheduled-command
     * definition accepts: a Cadence enum case, a CronExpressible, a
     * scheduler-method string ('daily', 'dailyAt:02:00', a raw cron), or a
     * closure receiving the schedule Event. Last call wins (single slot).
     */
    public function cadence(Cadence|CronExpressible|string|Closure $cadence): self
    {
        $this->cadence = $cadence;

        return $this;
    }

    /**
     * Guard every execution path with a cache lock (plus schedule-level
     * withoutOverlapping for scheduled runs) so two triggers can't seed
     * the same bundle concurrently.
     */
    public function withoutOverlapping(int $expiresAtMinutes = 1440): self
    {
        $this->overlapExpiresAt = $expiresAtMinutes;

        return $this;
    }

    /**
     * PackageSeeding* events fire for every run by default — pass false
     * to opt this bundle out (the global kill-switch is
     * package-tools.seeders.events.enabled).
     */
    public function notifiesOnCompletion(bool $notify = true): self
    {
        $this->notify = $notify;

        return $this;
    }

    public function whenConfig(string $key, bool $default = true): self
    {
        $this->gate = ConfigGate::make($key, $default)->truthy();

        return $this;
    }

    public function whenConfigNotNull(string $key): self
    {
        $this->gate = ConfigGate::make($key)->notNull();

        return $this;
    }

    /**
     * lower runs first; ties keep registration order.
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * legacy string-keyed SeederRegistry options passthrough.
     *
     * @param array<string, mixed> $options
     */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function namespace(): ?string
    {
        return $this->namespace;
    }

    public function shouldRegister(): bool
    {
        return ! $this->gate instanceof ConfigGate || $this->gate->passes();
    }

    public function priorityValue(): int
    {
        return $this->priority ?? 0;
    }

    /**
     * Whether priority() was explicitly called — lets the boot merge keep
     * an options(['priority' => …]) value when the fluent one was never set.
     */
    public function hasExplicitPriority(): bool
    {
        return $this->priority !== null;
    }

    public function isAutorun(): bool
    {
        return $this->autorun;
    }

    public function shouldStopOnFailure(): bool
    {
        return $this->stopOnFailure;
    }

    /**
     * @return list<string>
     */
    public function autorunEnvironmentsValue(): array
    {
        return $this->autorunEnvironments;
    }

    public function isBackground(): bool
    {
        return $this->background;
    }

    public function queueValue(): ?string
    {
        return $this->queue;
    }

    public function queueConnectionValue(): ?string
    {
        return $this->queueConnection;
    }

    public function shouldNotify(): bool
    {
        return $this->notify;
    }

    public function cadenceValue(): Cadence|CronExpressible|string|Closure|null
    {
        return $this->cadence;
    }

    public function hasCadence(): bool
    {
        return $this->cadence !== null;
    }

    public function overlapExpiresAtValue(): ?int
    {
        return $this->overlapExpiresAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function optionsValue(): array
    {
        return $this->options;
    }

    /**
     * boot-time resolution: explicit list, else discovery over the given
     * (or default) directory — minus the ignore list. an empty result
     * registers nothing, which is not an error.
     *
     * @return list<class-string<Seeder>>
     */
    public function resolveSeeders(string $defaultDiscoveryPath): array
    {
        if ($this->seeders !== []) {
            // dedupe the explicit list: a seeder listed twice must not run
            // twice (first occurrence keeps its position)
            $seeders = array_values(array_unique($this->seeders));
        } else {
            $path = $this->discoveryPath ?? $defaultDiscoveryPath;

            // a package without a seeders directory has nothing to discover
            // — not a boot error (the discoverer itself stays strict for
            // direct callers)
            $seeders = is_dir($path) ? $this->discoverer()->discover($path, $this->recursiveDiscovery) : [];
        }

        return array_values(array_diff($seeders, $this->ignored));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'seeders' => $this->seeders,
            'ignored' => $this->ignored,
            'discovery_path' => $this->discoveryPath,
            'namespace' => $this->namespace,
            'gate' => $this->gate?->toArray(),
            'priority' => $this->priorityValue(),
            'autorun' => $this->autorun,
            'autorun_environments' => $this->autorunEnvironments,
            'stop_on_failure' => $this->stopOnFailure,
            'background' => $this->background,
            'queue' => $this->queue,
            'queue_connection' => $this->queueConnection,
            'notify' => $this->notify,
            'cadence' => $this->cadence instanceof Closure ? 'closure' : (
                $this->cadence instanceof CronExpressible ? $this->cadence->toExpression() : (
                    $this->cadence instanceof Cadence ? $this->cadence->value : $this->cadence
                )
            ),
            'without_overlapping' => $this->overlapExpiresAt,
            'options' => $this->options,
        ];
    }

    private function discoverer(): SeederPathDiscoverer
    {
        if (function_exists('app') && app()->bound(SeederPathDiscoverer::class)) {
            return app(SeederPathDiscoverer::class);
        }

        return new SeederPathDiscoverer;
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

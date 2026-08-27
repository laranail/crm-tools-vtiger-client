<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Seeder;

/**
 * one package's registered seeder bundle: typed fields and fluent setters
 * instead of magic option-string keys. options are scoped to the bundle —
 * one package's fk/event/parameter choices never leak into another's run.
 *
 * @implements Arrayable<string, mixed>
 */
final class SeederBundle implements Arrayable
{
    private ?string $namespace = null;

    private int $priority = 0;

    private bool $disableForeignKeyChecks = true;

    private bool $fireEvents = false;

    private bool $autorun = false;

    private bool $stopOnFailure = false;

    /** @var list<string> */
    private array $autorunEnvironments = [];

    private bool $background = false;

    private ?string $queue = null;

    private ?string $connection = null;

    private bool $notify = true;

    private ?int $withoutOverlapping = null;

    /** @var array<string, mixed> */
    private array $parameters = [];

    /**
     * @param list<class-string<Seeder>> $seeders
     */
    private function __construct(
        private readonly string $key,
        private readonly array $seeders,
    ) {}

    /**
     * @param list<class-string<Seeder>> $seeders execution order = array order
     */
    public static function make(string $key, array $seeders): self
    {
        return new self($key, array_values($seeders));
    }

    /**
     * build from the legacy string-keyed options shape (the escape-hatch
     * path used by SeederRegistry::register()).
     *
     * @param list<class-string<Seeder>> $seeders
     * @param array<string, mixed> $options
     */
    public static function fromOptions(string $key, array $seeders, ?string $namespace, array $options): self
    {
        $bundle = self::make($key, $seeders)->inNamespace($namespace);

        $bundle->disableForeignKeyChecks = (bool) ($options['disable_foreign_key_checks'] ?? true);
        $bundle->fireEvents = (bool) ($options['fire_events'] ?? false);
        $bundle->parameters = is_array($options['parameters'] ?? null) ? $options['parameters'] : [];
        $bundle->priority = (int) ($options['priority'] ?? 0);
        $bundle->autorun = (bool) ($options['autorun'] ?? false);
        $bundle->stopOnFailure = (bool) ($options['stop_on_failure'] ?? false);
        $bundle->autorunEnvironments = is_array($options['autorun_environments'] ?? null)
            ? array_values($options['autorun_environments'])
            : [];
        $bundle->background = (bool) ($options['background'] ?? false);
        $bundle->queue = isset($options['queue']) ? (string) $options['queue'] : null;
        $bundle->connection = isset($options['connection']) ? (string) $options['connection'] : null;
        $bundle->notify = (bool) ($options['notify'] ?? true);
        $bundle->withoutOverlapping = isset($options['without_overlapping'])
            ? (int) $options['without_overlapping']
            : null;

        return $bundle;
    }

    public function inNamespace(?string $namespace): self
    {
        $this->namespace = $namespace;

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

    public function withoutForeignKeyChecks(bool $disable = true): self
    {
        $this->disableForeignKeyChecks = $disable;

        return $this;
    }

    public function firesEvents(bool $fire = true): self
    {
        $this->fireEvents = $fire;

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters passed to seeders accepting ctor args
     */
    public function parameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Opt this bundle into automatic execution after a successful
     * `migrate` run (subject to the SeederAutorun safety gates).
     */
    public function autoruns(bool $autorun = true): self
    {
        $this->autorun = $autorun;

        return $this;
    }

    /**
     * Skip the bundle's remaining seeders after the first failure
     * (cross-bundle isolation is always preserved regardless).
     */
    public function stopsOnFailure(bool $stop = true): self
    {
        $this->stopOnFailure = $stop;

        return $this;
    }

    /**
     * Restrict autorun to these environment names. A non-empty list
     * REPLACES the production config gate for this bundle.
     *
     * @param list<string> $environments
     */
    public function autorunsInEnvironments(array $environments): self
    {
        $this->autorunEnvironments = array_values($environments);

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return list<class-string<Seeder>>
     */
    public function seeders(): array
    {
        return $this->seeders;
    }

    public function namespace(): ?string
    {
        return $this->namespace;
    }

    public function priorityValue(): int
    {
        return $this->priority;
    }

    public function disablesForeignKeyChecks(): bool
    {
        return $this->disableForeignKeyChecks;
    }

    public function shouldFireEvents(): bool
    {
        return $this->fireEvents;
    }

    /**
     * @return array<string, mixed>
     */
    public function parametersValue(): array
    {
        return $this->parameters;
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
    public function autorunEnvironments(): array
    {
        return $this->autorunEnvironments;
    }

    public function isBackground(): bool
    {
        return $this->background;
    }

    public function queue(): ?string
    {
        return $this->queue;
    }

    public function connection(): ?string
    {
        return $this->connection;
    }

    public function shouldNotify(): bool
    {
        return $this->notify;
    }

    /**
     * Cache-lock TTL in minutes, or null when overlapping runs are allowed.
     */
    public function withoutOverlappingMinutes(): ?int
    {
        return $this->withoutOverlapping;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'seeders' => $this->seeders,
            'namespace' => $this->namespace,
            'priority' => $this->priority,
            'disable_foreign_key_checks' => $this->disableForeignKeyChecks,
            'fire_events' => $this->fireEvents,
            'parameters' => $this->parameters,
            'autorun' => $this->autorun,
            'stop_on_failure' => $this->stopOnFailure,
            'autorun_environments' => $this->autorunEnvironments,
            'background' => $this->background,
            'queue' => $this->queue,
            'connection' => $this->connection,
            'notify' => $this->notify,
            'without_overlapping' => $this->withoutOverlapping,
        ];
    }
}

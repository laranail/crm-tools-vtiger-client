<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Commands\InstallCommand;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;

/**
 * a fluent install-command definition: named steps executed in declaration
 * order (built-ins and custom steps interleave freely — no fixed pipeline),
 * built lazily so no console command is constructed on web requests. the
 * legacy callable form of hasInstallCommand() keeps working unchanged.
 *
 * @implements Arrayable<string, mixed>
 */
final class InstallCommandDefinition implements Arrayable, Jsonable, JsonSerializable
{
    /** @var list<array{label: string, run: Closure(InstallCommand): void}> */
    private array $steps = [];

    private ?string $signature = null;

    private bool $hidden = true;

    public static function make(): self
    {
        return new self;
    }

    /**
     * override the default `{short-name}:install` signature.
     */
    public function named(string $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * list the command in `php artisan list` (hidden by default).
     */
    public function visible(bool $visible = true): self
    {
        $this->hidden = ! $visible;

        return $this;
    }

    /**
     * publish one or more of the package's tags. both the namespaced tag
     * (`vendor::pkg-{tag}`) and the legacy `{short-name}-{tag}` form are
     * attempted, so publishing works whichever way the package registered
     * its publishables.
     */
    public function publishes(string ...$tags): self
    {
        foreach ($tags as $tag) {
            $this->step("publish {$tag}", static function (InstallCommand $command) use ($tag): void {
                $command->comment('Publishing ' . str_replace('-', ' ', $tag) . '...');

                $command->callSilently('vendor:publish', [
                    '--tag' => $command->getPackage()->getNamespacedPublishTag($tag),
                ]);
                $command->callSilently('vendor:publish', [
                    '--tag' => "{$command->getPackage()->shortName()}-{$tag}",
                ]);
            });
        }

        return $this;
    }

    /**
     * run the migrations without prompting.
     *
     * note: autorun-flagged seeder bundles run automatically after this
     * step when migrations were pending (the Migrator fires MigrationsEnded
     * even for nested `call('migrate')`). when nothing is pending, add
     * runsSeeders() explicitly.
     */
    public function runsMigrations(): self
    {
        return $this->step('run migrations', static function (InstallCommand $command): void {
            $command->comment('Running migrations...');
            $command->call('migrate');
        });
    }

    /**
     * execute the package's registered seeder bundles now (those not
     * already run this process), with console output. covers the install
     * case where the database is already migrated so MigrationsEnded never
     * fires.
     */
    public function runsSeeders(): self
    {
        return $this->step('run seeders', static function (InstallCommand $command): void {
            $command->comment('Running package seeders...');

            /** @var SeederManager $manager */
            $manager = $command->getLaravel()->make(SeederManager::class);
            $stats = $manager->runAutorun($command->getOutput());

            if ($stats->isEmpty()) {
                $command->comment('No pending package seeders.');
            } else {
                $command->info($stats->getSummary());
            }
        });
    }

    /**
     * prompt, then run the migrations on confirmation.
     */
    public function asksToRunMigrations(): self
    {
        return $this->step('ask to run migrations', static function (InstallCommand $command): void {
            if ($command->confirm('Would you like to run the migrations now?')) {
                $command->comment('Running migrations...');
                $command->call('migrate');
            }
        });
    }

    public function copiesServiceProvider(): self
    {
        return $this->step('copy service provider', static function (InstallCommand $command): void {
            $command->comment('Publishing service provider...');
            $command->copyProviderNow();
        });
    }

    public function asksToStarRepo(string $vendorSlashRepoName, bool $defaultAnswer = false): self
    {
        return $this->step('ask to star repo', static function (InstallCommand $command) use ($vendorSlashRepoName, $defaultAnswer): void {
            $command->askToStarRepoOnGitHub($vendorSlashRepoName, $defaultAnswer);
            $command->starRepoNow();
        });
    }

    /**
     * an arbitrary named step, executed in declaration order like every
     * built-in.
     *
     * @param Closure(InstallCommand): void $run
     */
    public function step(string $label, Closure $run): self
    {
        $this->steps[] = ['label' => $label, 'run' => $run];

        return $this;
    }

    public function signature(?string $default = null): ?string
    {
        return $this->signature ?? $default;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * @return list<array{label: string, run: Closure(InstallCommand): void}>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'signature' => $this->signature,
            'hidden' => $this->hidden,
            'steps' => array_map(static fn (array $step): string => $step['label'], $this->steps),
        ];
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

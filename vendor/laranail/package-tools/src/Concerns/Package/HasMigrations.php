<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasMigrations
{
    public bool $hasMigrations = false;

    public bool $runsMigrations = false;

    public bool $discoversMigrations = false;

    public ?string $migrationsPath = null;

    /** @var list<string> */
    public array $migrationFileNames = [];

    public function runsMigrations(bool $runsMigrations = true): static
    {
        $this->runsMigrations = $runsMigrations;
        $this->hasMigrations = true;

        return $this;
    }

    public function hasMigration(string $migrationFileName): static
    {
        $this->migrationFileNames[] = $migrationFileName;
        $this->hasMigrations = true;

        return $this;
    }

    /**
     * @param string|array<int, string> ...$migrationFileNames
     */
    public function hasMigrations(...$migrationFileNames): static
    {
        /** @var list<string> $flattened */
        $flattened = collect($migrationFileNames)->flatten()->toArray();
        $this->migrationFileNames = array_merge(
            $this->migrationFileNames,
            $flattened
        );
        $this->hasMigrations = true;

        return $this;
    }

    public function discoversMigrations(bool $discoversMigrations = true, string $path = '/database/migrations'): static
    {
        $this->discoversMigrations = $discoversMigrations;
        $this->migrationsPath = $path;

        return $this;
    }
}

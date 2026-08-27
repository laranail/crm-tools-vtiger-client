<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Simtabi\Laranail\Package\Tools\Package;

trait ProcessMigrations
{
    protected function bootPackageMigrations(): self
    {
        if ($this->package->discoversMigrations) {
            $this->discoverPackageMigrations();

            return $this;
        }

        $now = Carbon::now();

        foreach ($this->package->migrationFileNames as $migrationFileName) {
            $vendorMigration = $this->package->basePath(Package::MIGRATIONS_DIR . "/{$migrationFileName}.php");

            // Fall back to a .stub file.
            if (! File::exists($vendorMigration)) {
                $vendorMigration .= '.stub';
            }

            if ($this->app->runningInConsole()) {
                $appMigration = $this->generateMigrationName($migrationFileName, $now->addSecond());

                $publishTag = $this->package->getNamespacedPublishTag('migrations');

                $this->publishes(
                    [$vendorMigration => $appMigration],
                    $publishTag
                );
            }

            if ($this->package->runsMigrations) {
                $this->loadMigrationsFrom($vendorMigration);
            }
        }

        return $this;
    }

    protected function discoverPackageMigrations(): void
    {
        $now = Carbon::now();
        $migrationsPath = trim((string) $this->package->migrationsPath, '/');

        $files = (new Filesystem)->files($this->package->basePath($migrationsPath));

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            $migrationFileName = Str::replace(['.stub', '.php'], '', $file->getFilename());

            // Only timestamp actual migration files.
            if (Str::endsWith($filePath, ['.php', '.php.stub'])) {
                $appMigration = $this->generateMigrationName($migrationFileName, $now->addSecond());
            } else {
                $appMigration = database_path("migrations/{$file->getFilename()}");
            }

            if ($this->app->runningInConsole()) {
                $publishTag = $this->package->getNamespacedPublishTag('migrations');

                $this->publishes(
                    [$filePath => $appMigration],
                    $publishTag
                );
            }

            if ($this->package->runsMigrations && Str::endsWith($filePath, ['.php', '.php.stub'])) {
                $this->loadMigrationsFrom($filePath);
            }
        }
    }

    protected function generateMigrationName(string $migrationFileName, Carbon|CarbonImmutable $now): string
    {
        $migrationsPath = 'migrations/' . dirname($migrationFileName) . '/';
        $migrationFileName = basename($migrationFileName);

        $len = strlen($migrationFileName) + 4;

        if (Str::contains($migrationFileName, '/')) {
            $migrationsPath .= Str::of($migrationFileName)->beforeLast('/')->finish('/');
            $migrationFileName = (string) Str::of($migrationFileName)->afterLast('/');
        }

        foreach (glob(database_path("{$migrationsPath}*.php")) ?: [] as $filename) {
            if ((substr($filename, -$len) === $migrationFileName . '.php')) {
                return $filename;
            }
        }

        $migrationFileName = self::stripTimestampPrefix($migrationFileName);
        $timestamp = $now->format('Y_m_d_His');
        $formattedFileName = Str::of($migrationFileName)->snake()->finish('.php');

        return database_path("{$migrationsPath}{$timestamp}_{$formattedFileName}");
    }

    private static function stripTimestampPrefix(string $filename): string
    {
        return preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename) ?? $filename;
    }
}

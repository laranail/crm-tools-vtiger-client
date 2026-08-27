<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Orchestra\Testbench\TestCase as Testbench;

/**
 * Opinionated Testbench wrapper for laranail/package-tools consumers.
 *
 * Defaults:
 *   - In-memory SQLite (DB_CONNECTION=testing, DB_DATABASE=:memory:).
 *   - APP_KEY pre-seeded (so encrypted helpers don't error in tests).
 *   - sync queue, array cache, array session.
 *
 * Helpers:
 *   - assertTableExists(table)
 *   - assertColumnExists(table, column)
 *   - assertCommandExists(signature)
 *   - createTempPath(suffix): a unique tmp path that auto-cleans at tearDown.
 *   - assertPublishedConfigOverride(...) via {@see AssertsPublishedConfigOverrides}.
 *
 * Subclasses register their package's service provider via
 * getPackageProviders($app). The base class does not auto-discover any
 * provider; consumers state the entry-point explicitly, which keeps the
 * failure mode obvious (missing provider = error at setUp, not silent skip).
 */
abstract class IsolatedTestCase extends Testbench
{
    use AssertsPublishedConfigOverrides;

    /** @var list<string> Tmp paths created by createTempPath(); removed at tearDown. */
    private array $tempPathsToCleanup = [];

    /**
     * Subclasses override to register their package's service provider(s).
     *
     * @param Application $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [];
    }

    /**
     * Configure the in-memory test environment. Subclasses can extend by
     * calling parent::defineEnvironment() and then setting their own keys.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempPathsToCleanup as $path) {
            $this->recursiveRemove($path);
        }
        $this->tempPathsToCleanup = [];

        parent::tearDown();
    }

    /**
     * Create a unique temp directory that auto-cleans at tearDown.
     */
    protected function createTempPath(string $suffix = ''): string
    {
        $base = sys_get_temp_dir() . '/laranail-test-' . bin2hex(random_bytes(4));
        if ($suffix !== '') {
            $base .= '-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $suffix);
        }
        mkdir($base, 0o755, true);
        $this->tempPathsToCleanup[] = $base;

        return $base;
    }

    protected function assertTableExists(string $table, string $message = ''): void
    {
        self::assertTrue(
            Schema::hasTable($table),
            $message !== '' ? $message : "Failed asserting that table '{$table}' exists.",
        );
    }

    protected function assertTableMissing(string $table, string $message = ''): void
    {
        self::assertFalse(
            Schema::hasTable($table),
            $message !== '' ? $message : "Failed asserting that table '{$table}' does not exist.",
        );
    }

    protected function assertColumnExists(string $table, string $column, string $message = ''): void
    {
        self::assertTrue(
            Schema::hasColumn($table, $column),
            $message !== ''
                ? $message
                : "Failed asserting that column '{$column}' exists on table '{$table}'.",
        );
    }

    protected function assertCommandExists(string $signature, string $message = ''): void
    {
        $app = $this->app ?? throw new LogicException('Test application has not been booted yet.');
        $registered = array_keys($app->make(Kernel::class)->all());
        self::assertContains(
            $signature,
            $registered,
            $message !== ''
                ? $message
                : "Failed asserting that artisan command '{$signature}' is registered.",
        );
    }

    private function recursiveRemove(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $this->recursiveRemove($path . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($path);
    }
}

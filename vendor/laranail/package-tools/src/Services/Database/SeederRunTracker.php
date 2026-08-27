<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Support\Facades\Cache;
use Simtabi\Laranail\Package\Tools\Enums\SeederRunStatus;
use Throwable;

/**
 * Cache-backed status store for seeder-bundle runs — the poll surface
 * behind `laranail::package-tools.seed --status`. Written from every
 * execution path (inline, autorun, queued job); a queued run's progress
 * is therefore observable from any other process sharing the cache.
 *
 * Write-locked with a never-regress guard on the processed counter, so a
 * lost update can't make progress appear to move backwards. TTLs: 24h
 * while processing, 7 days once terminal. All operations are best-effort
 * — tracking failures never break seeding.
 */
final class SeederRunTracker
{
    private const string KEY_PREFIX = 'package-tools:seeding:status:';

    private const int PROCESSING_TTL = 86_400;

    private const int TERMINAL_TTL = 604_800;

    public function start(string $key, int $total): void
    {
        $this->put($key, [
            'status' => SeederRunStatus::Processing->value,
            'total' => $total,
            'processed' => 0,
            'failed' => 0,
            'message' => null,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
        ], self::PROCESSING_TTL);
    }

    public function advance(string $key, bool $failed = false): void
    {
        $this->update($key, static function (array $state) use ($failed): array {
            $state['processed'] = max($state['processed'] + 1, $state['processed']);
            if ($failed) {
                $state['failed']++;
            }

            return $state;
        }, self::PROCESSING_TTL);
    }

    public function complete(string $key): void
    {
        $this->update($key, static function (array $state): array {
            $state['status'] = ($state['failed'] ?? 0) > 0
                ? SeederRunStatus::Failed->value
                : SeederRunStatus::Completed->value;
            $state['finished_at'] = now()->toIso8601String();

            return $state;
        }, self::TERMINAL_TTL);
    }

    public function fail(string $key, string $message): void
    {
        $this->update($key, static function (array $state) use ($message): array {
            $state['status'] = SeederRunStatus::Failed->value;
            $state['message'] = $message;
            $state['finished_at'] = now()->toIso8601String();

            return $state;
        }, self::TERMINAL_TTL);
    }

    /**
     * @return array{status: SeederRunStatus, total: int, processed: int, failed: int, message: ?string, started_at: ?string, finished_at: ?string}|null
     */
    public function get(string $key): ?array
    {
        try {
            $state = Cache::get(self::KEY_PREFIX . $key);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($state)) {
            return null;
        }

        return [
            'status' => SeederRunStatus::tryFrom((string) ($state['status'] ?? '')) ?? SeederRunStatus::Pending,
            'total' => (int) ($state['total'] ?? 0),
            'processed' => (int) ($state['processed'] ?? 0),
            'failed' => (int) ($state['failed'] ?? 0),
            'message' => isset($state['message']) ? (string) $state['message'] : null,
            'started_at' => isset($state['started_at']) ? (string) $state['started_at'] : null,
            'finished_at' => isset($state['finished_at']) ? (string) $state['finished_at'] : null,
        ];
    }

    public function clear(string $key): void
    {
        try {
            Cache::forget(self::KEY_PREFIX . $key);
        } catch (Throwable) {
            // best-effort
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function put(string $key, array $state, int $ttl): void
    {
        try {
            Cache::put(self::KEY_PREFIX . $key, $state, $ttl);
        } catch (Throwable) {
            // best-effort
        }
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutate
     */
    private function update(string $key, callable $mutate, int $ttl): void
    {
        try {
            $lock = Cache::lock(self::KEY_PREFIX . $key . ':lock', 5);

            $lock->block(2, function () use ($key, $mutate, $ttl): void {
                $state = Cache::get(self::KEY_PREFIX . $key);
                if (! is_array($state)) {
                    return;
                }

                Cache::put(self::KEY_PREFIX . $key, $mutate($state), $ttl);
            });
        } catch (Throwable) {
            // best-effort — a lost status update never breaks seeding
        }
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Simtabi\Laranail\Toolkit\Services\Contracts\SettingsStoreInterface;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;

/**
 * A small, typed runtime settings store backed by a single JSON file on a
 * configured filesystem disk.
 *
 * This is a *dynamic* settings store (values that change at runtime and persist
 * across requests) — it is deliberately separate from Laravel's `config()` (the
 * static, deploy-time configuration). Keys use dot notation.
 */
class SettingsStore implements SettingsStoreInterface
{
    private readonly string $path;

    public function __construct(private ?Filesystem $disk = null, ?string $path = null)
    {
        $this->path = $path ?? ToolkitConfig::string('laranail.toolkit.settings.path', 'laranail/settings.json');
    }

    /**
     * All persisted settings.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $disk = $this->disk();

        if (!$disk->exists($this->path)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($this->path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get a setting by dot-notation key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->all(), $key);
    }

    /**
     * Set (or overwrite) a setting by dot-notation key and persist.
     */
    public function set(string $key, mixed $value): void
    {
        $this->mutate(function (array &$all) use ($key, $value): void {
            Arr::set($all, $key, $value);
        });
    }

    /**
     * Remove a setting by dot-notation key and persist.
     */
    public function forget(string $key): void
    {
        $this->mutate(function (array &$all) use ($key): void {
            Arr::forget($all, $key);
        });
    }

    /**
     * Serialise the read-modify-write behind an atomic lock so two concurrent
     * writers cannot each read the same snapshot and clobber the other's key.
     *
     * Cross-process safety requires an atomic cache store (redis/database/…); the
     * array/file drivers serialise within a single process.
     *
     * @param callable(array<string, mixed>): void $mutator
     */
    private function mutate(callable $mutator): void
    {
        Cache::lock('laranail:toolkit:settings:' . md5($this->path), 10)->block(5, function () use ($mutator): void {
            $all = $this->all();
            $mutator($all);
            $this->persist($all);
        });
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function persist(array $settings): void
    {
        $this->disk()->put(
            $this->path,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    private function disk(): Filesystem
    {
        return $this->disk ??= Storage::disk(ToolkitConfig::string('laranail.toolkit.settings.disk', 'local'));
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigMerger;
use Simtabi\Laranail\Package\Tools\Support\ConfigDecorator;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Two ways to shape host config from a package:
 *
 *  - `mergesConfigDefaults()` / `mergesConfigDefaultsFrom()` — merge a
 *    package config file (or a directory of them) onto global keys as
 *    DEFAULTS, host values winning. Applied in the register phase (see the
 *    provider's registerPackageConfigDecorations()).
 *  - `configDecorator()` — a boot-time closure receiving a
 *    {@see ConfigDecorator} for authoritative sets that may depend on runtime
 *    data. A decoration is a general escape hatch, so it **fails closed**
 *    (Critical by default): a throw crashes boot unless the author marks it
 *    `BootCriticality::Degradable` (for a genuinely cosmetic decoration whose
 *    absence leaves a safe state).
 *
 * The host-wins merge reuses {@see ConfigMerger::deepMerge()} —
 * `deepMerge($packageDefaults, $hostConfig)` (host is the second/winning arg).
 */
trait HasConfigDecorations
{
    /** @var array<int, array{path: string, key: ?string, dir: bool}> */
    protected array $configDefaultMerges = [];

    /** @var array<int, array{fn: Closure, criticality: BootCriticality}> */
    protected array $configDecorators = [];

    public function mergesConfigDefaults(string $path, ?string $globalKey = null): static
    {
        $this->configDefaultMerges[] = ['path' => $path, 'key' => $globalKey, 'dir' => false];

        return $this;
    }

    public function mergesConfigDefaultsFrom(string $directory): static
    {
        $this->configDefaultMerges[] = ['path' => $directory, 'key' => null, 'dir' => true];

        return $this;
    }

    public function configDecorator(Closure $decorator, BootCriticality $criticality = BootCriticality::Critical): static
    {
        $this->configDecorators[] = ['fn' => $decorator, 'criticality' => $criticality];

        return $this;
    }

    /**
     * Apply the config-default merges. Call from the provider's register
     * phase (after registerPackageConfigs()).
     */
    public function applyPackageConfigDefaults(): void
    {
        $merger = new ConfigMerger;

        foreach ($this->configDefaultMerges as $spec) {
            $absolute = $this->basePath('/' . ltrim((string) $spec['path'], '/'));

            if ($spec['dir']) {
                foreach (glob(rtrim((string) $absolute, '/') . '/*.php') ?: [] as $file) {
                    $this->mergeDefaultsFile($merger, basename($file, '.php'), $file);
                }

                continue;
            }

            if ($spec['key'] !== null) {
                $this->mergeDefaultsFile($merger, $spec['key'], $absolute);

                continue;
            }

            // No global key: the file returns [globalKey => defaults].
            foreach ($this->loadArray($absolute) as $globalKey => $defaults) {
                if (is_array($defaults)) {
                    $this->mergeDefaultsArray($merger, (string) $globalKey, $defaults);
                }
            }
        }
    }

    /**
     * Run the boot-time config decorators under the failure-handling policy,
     * each with the criticality the author declared (Critical by default).
     * Call from the provider's boot().
     */
    public function bootPackageConfigDecorators(): void
    {
        foreach ($this->configDecorators as $decorator) {
            FailurePolicy::run(
                static fn () => ($decorator['fn'])(new ConfigDecorator),
                'configDecorator',
                $decorator['criticality'],
            );
        }
    }

    private function mergeDefaultsFile(ConfigMerger $merger, string $globalKey, string $file): void
    {
        $defaults = $this->loadArray($file);

        if ($defaults !== []) {
            $this->mergeDefaultsArray($merger, $globalKey, $defaults);
        }
    }

    /**
     * @param array<int|string, mixed> $defaults
     */
    private function mergeDefaultsArray(ConfigMerger $merger, string $globalKey, array $defaults): void
    {
        $existing = config($globalKey, []);

        // Host wins: package defaults first, host config as the winning merge.
        config()->set($globalKey, $merger->deepMerge($defaults, is_array($existing) ? $existing : []));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function loadArray(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $data = require $file;

        return is_array($data) ? $data : [];
    }

    abstract public function basePath(?string $directory = null): string;
}

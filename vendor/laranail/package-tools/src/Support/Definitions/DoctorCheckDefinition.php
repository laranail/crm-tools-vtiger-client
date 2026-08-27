<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\CallbackCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\ConfigPresentCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\PhpExtensionCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\PhpVersionCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\ReachabilityCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\SoftDependencyCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\WritablePathCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;

/**
 * a fluent doctor check: wraps any DoctorCheck (or a bare closure) with
 * chainable name/description overrides and config gating through the
 * shared ConfigGate — no positional nullable constructor args. static
 * factories cover the whole bundled check library.
 *
 * the definition IS a DoctorCheck, so the DoctorService needs no special
 * handling; a gated-off definition is simply never registered at boot
 * (run-time preconditions belong inside the check via DoctorResult::skip).
 *
 * @implements Arrayable<string, mixed>
 */
final class DoctorCheckDefinition implements Arrayable, DoctorCheck, Jsonable, JsonSerializable
{
    private ?string $name = null;

    private ?string $description = null;

    private ?ConfigGate $gate = null;

    private function __construct(
        private readonly DoctorCheck $inner,
    ) {}

    /**
     * wrap an existing check to gain the fluent surface.
     */
    public static function wrap(DoctorCheck $check): self
    {
        return new self($check);
    }

    /**
     * the escape hatch: an arbitrary closure returning a DoctorResult.
     *
     * @param Closure(): DoctorResult $run
     */
    public static function callback(string $name, Closure $run): self
    {
        return (new self(new CallbackCheck($name, '', $run)))->named($name);
    }

    public static function phpVersion(string $minVersion): self
    {
        return new self(new PhpVersionCheck($minVersion));
    }

    /**
     * @param string|list<string> $extensions
     */
    public static function phpExtensions(string|array $extensions): self
    {
        return new self(new PhpExtensionCheck($extensions));
    }

    /**
     * @param array<string, string>|list<string> $keys label => config-key, or a plain list of keys
     */
    public static function configPresent(array $keys, bool $required = true): self
    {
        return new self(new ConfigPresentCheck($keys, $required));
    }

    /**
     * @param array<string, string>|list<string> $paths label => path, or a plain list of paths
     */
    public static function writablePaths(array $paths, ?int $minFreeBytes = null): self
    {
        return new self(new WritablePathCheck($paths, $minFreeBytes));
    }

    /**
     * @param Closure(): bool $probe
     */
    public static function reachable(string $name, Closure $probe, string $target = 'Target'): self
    {
        return new self(new ReachabilityCheck($probe, $name, null, $target));
    }

    /**
     * @param class-string $class
     */
    public static function softDependency(string $class, string $label, bool $required = true): self
    {
        return new self(new SoftDependencyCheck($class, $label, $required));
    }

    public function named(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function describe(string $description): self
    {
        $this->description = $description;

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
     * boot-time gate: false means the check is never registered.
     */
    public function shouldRegister(): bool
    {
        return ! $this->gate instanceof ConfigGate || $this->gate->passes();
    }

    public function name(): string
    {
        return $this->name ?? $this->inner->name();
    }

    public function description(): string
    {
        if ($this->description !== null) {
            return $this->description;
        }

        $inner = $this->inner->description();

        return $inner !== '' ? $inner : $this->name();
    }

    public function run(): DoctorResult
    {
        return $this->inner->run();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'check' => $this->inner::class,
            'gate' => $this->gate?->toArray(),
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

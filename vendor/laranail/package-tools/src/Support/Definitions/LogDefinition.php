<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

use function Illuminate\Support\enum_value;

use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;

/**
 * a package's logging configuration: where the per-package logfile lives,
 * how it rotates, its minimum level, and its format — or a delegation to
 * an existing host channel. every setting here is the PACKAGE AUTHOR's
 * default; host config ({vendor}.{package}.logging.* and the global
 * package-tools.logging.*) overrides it, and a host-defined
 * logging.channels entry named after the package wins wholesale.
 *
 * @implements Arrayable<string, mixed>
 */
final class LogDefinition implements Arrayable, Jsonable, JsonSerializable
{
    private bool $enabled = true;

    private ?string $path = null;

    private ?string $directory = null;

    private string $driver = 'daily';

    private int $days = 14;

    private string $level = 'debug';

    private ?string $channel = null;

    private string $format = 'line';

    private ?int $permission = null;

    private ?ConfigGate $gate = null;

    private function __construct() {}

    public static function make(): self
    {
        return new self;
    }

    /**
     * full logfile path override (absolute).
     */
    public function path(string $absolutePath): self
    {
        $this->path = $absolutePath;

        return $this;
    }

    /**
     * keep the default `{vendor}-{package}.log` filename, move the
     * directory.
     */
    public function directory(string $directory): self
    {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * daily-rotated file (the default), keeping `$days` of history.
     */
    public function daily(int $days = 14): self
    {
        $this->driver = 'daily';
        $this->days = $days;

        return $this;
    }

    /**
     * one single, unrotated file.
     */
    public function single(): self
    {
        $this->driver = 'single';

        return $this;
    }

    /**
     * minimum level written (PSR-3 level name or a backed enum of one).
     */
    public function level(BackedEnum|string $level): self
    {
        $this->level = (string) enum_value($level);

        return $this;
    }

    /**
     * delegate to an existing host logging channel instead of creating a
     * per-package file.
     */
    public function useChannel(BackedEnum|string $channel): self
    {
        $this->channel = (string) enum_value($channel);

        return $this;
    }

    /**
     * machine-first output: swaps the bracketed line format for Monolog's
     * JsonFormatter.
     */
    public function asJson(): self
    {
        $this->format = 'json';

        return $this;
    }

    /**
     * unix permission mode for the created logfile (e.g. 0664).
     */
    public function permission(int $mode): self
    {
        $this->permission = $mode;

        return $this;
    }

    public function disabled(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * gate logging on a host config key (evaluated lazily at write time).
     */
    public function whenConfig(string $key, bool $default = true): self
    {
        $this->gate = ConfigGate::make($key, $default)->truthy();

        return $this;
    }

    /**
     * mirrors the other definitions' should*() gate checks.
     */
    public function shouldLog(): bool
    {
        return $this->enabled && (! $this->gate instanceof ConfigGate || $this->gate->passes());
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function pathValue(): ?string
    {
        return $this->path;
    }

    public function directoryValue(): ?string
    {
        return $this->directory;
    }

    public function driverValue(): string
    {
        return $this->driver;
    }

    public function daysValue(): int
    {
        return $this->days;
    }

    public function levelValue(): string
    {
        return $this->level;
    }

    public function channelValue(): ?string
    {
        return $this->channel;
    }

    public function formatValue(): string
    {
        return $this->format;
    }

    public function permissionValue(): ?int
    {
        return $this->permission;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'path' => $this->path,
            'directory' => $this->directory,
            'driver' => $this->driver,
            'days' => $this->days,
            'level' => $this->level,
            'channel' => $this->channel,
            'format' => $this->format,
            'permission' => $this->permission,
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

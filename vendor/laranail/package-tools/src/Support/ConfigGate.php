<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * the one implementation of config gating, shared by every whenConfig() /
 * whenConfigNotNull() across the package. evaluation happens when passes()
 * is called (boot or schedule time), never at configure time. two explicit
 * modes: truthy (the default) and not-null ("configured means on").
 *
 * @implements Arrayable<string, bool|string>
 */
final class ConfigGate implements Arrayable
{
    private GateMode $mode = GateMode::Truthy;

    private function __construct(
        private readonly string $key,
        private readonly bool $default,
    ) {}

    public static function make(string $key, bool $default = true): self
    {
        return new self($key, $default);
    }

    public function truthy(): self
    {
        $this->mode = GateMode::Truthy;

        return $this;
    }

    public function notNull(): self
    {
        $this->mode = GateMode::NotNull;

        return $this;
    }

    public function passes(): bool
    {
        if ($this->mode === GateMode::NotNull) {
            return config($this->key) !== null;
        }

        return (bool) config($this->key, $this->default);
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return array{key: string, default: bool, mode: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'default' => $this->default, 'mode' => $this->mode->value];
    }
}

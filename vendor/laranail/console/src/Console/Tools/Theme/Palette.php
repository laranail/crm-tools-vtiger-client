<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Theme;

use InvalidArgumentException;

/**
 * A semantic colour palette — the design tokens every component shares. Roles map
 * to colour specs (any {@see Color} spec).
 * Immutable; override any role to re-skin the whole UI.
 */
final readonly class Palette
{
    /** @var array<string, string> */
    private const array DEFAULTS = [
        'primary' => '#7c3aed',
        'accent' => '#06b6d4',
        'success' => '#16a34a',
        'warning' => '#d97706',
        'danger' => '#dc2626',
        'info' => '#2563eb',
        'muted' => '#64748b',
    ];

    /** @var array<string, string> */
    public array $roles;

    /**
     * @param array<string, string> $overrides
     */
    public function __construct(array $overrides = [])
    {
        $this->roles = array_merge(self::DEFAULTS, array_filter($overrides, is_string(...)));
    }

    /**
     * @param array<string, string> $overrides
     */
    public static function make(array $overrides = []): self
    {
        return new self($overrides);
    }

    /**
     * A palette built from a named {@see Presets built-in preset}.
     *
     * @throws InvalidArgumentException on an unknown preset name
     */
    public static function preset(string $name): self
    {
        $roles = Presets::get($name)
            ?? throw new InvalidArgumentException("Unknown theme preset '{$name}'. Available: " . implode(', ', Presets::names()) . '.');

        return new self($roles);
    }

    public function get(string $role): ?string
    {
        return $this->roles[$role] ?? null;
    }

    public function with(string $role, string $color): self
    {
        return new self([...$this->roles, $role => $color]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->roles;
    }
}

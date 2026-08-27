<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Theme;

/**
 * Built-in named palettes for the design system. Each maps the seven semantic
 * roles (primary/accent/success/warning/danger/info/muted) to a hex colour from a
 * well-known scheme. Selectable via {@see Theme::preset()} or
 * `config('laranail.console.theme.preset')`. Add one by adding an entry here.
 */
final class Presets
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $cache = null;

    /**
     * Names of the built-in presets.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $name): bool
    {
        return isset(self::all()[strtolower($name)]);
    }

    /**
     * The role→hex map for a preset, or null if unknown.
     *
     * @return array<string, string>|null
     */
    public static function get(string $name): ?array
    {
        return self::all()[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function all(): array
    {
        return self::$cache ??= [
            'dracula' => [
                'primary' => '#bd93f9', 'accent' => '#8be9fd', 'success' => '#50fa7b',
                'warning' => '#ffb86c', 'danger' => '#ff5555', 'info' => '#6272a4',
                'muted' => '#6272a4',
            ],
            'nord' => [
                'primary' => '#88c0d0', 'accent' => '#8fbcbb', 'success' => '#a3be8c',
                'warning' => '#ebcb8b', 'danger' => '#bf616a', 'info' => '#5e81ac',
                'muted' => '#4c566a',
            ],
            'solarized' => [
                'primary' => '#268bd2', 'accent' => '#2aa198', 'success' => '#859900',
                'warning' => '#b58900', 'danger' => '#dc322f', 'info' => '#6c71c4',
                'muted' => '#586e75',
            ],
            'monochrome' => [
                'primary' => '#e5e5e5', 'accent' => '#bdbdbd', 'success' => '#d4d4d4',
                'warning' => '#9e9e9e', 'danger' => '#ffffff', 'info' => '#a3a3a3',
                'muted' => '#737373',
            ],
            'github' => [
                'primary' => '#0969da', 'accent' => '#1f883d', 'success' => '#1a7f37',
                'warning' => '#9a6700', 'danger' => '#cf222e', 'info' => '#0550ae',
                'muted' => '#656d76',
            ],
        ];
    }
}

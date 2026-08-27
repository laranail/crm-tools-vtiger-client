<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support\Fonts;

/**
 * Registry of the big-text fonts bundled with the package. Adding a font is a
 * new `*Font` class plus one entry in {@see definitions()} — no filesystem
 * scanning, no `resources/` data files.
 */
final class BuiltinFonts
{
    /**
     * @var array<string, FontDefinition>|null
     */
    private static ?array $cache = null;

    /**
     * Names of the bundled fonts.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::definitions());
    }

    public static function has(string $name): bool
    {
        return isset(self::definitions()[$name]);
    }

    public static function get(string $name): ?FontDefinition
    {
        return self::definitions()[$name] ?? null;
    }

    /**
     * @return array<string, FontDefinition>
     */
    private static function definitions(): array
    {
        return self::$cache ??= [
            'block' => BlockFont::definition(),
        ];
    }
}

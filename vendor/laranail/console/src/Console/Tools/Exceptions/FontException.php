<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Exceptions;

use Simtabi\Laranail\Console\Exceptions\ConsoleException;

/**
 * Thrown by {@see Figlet} when a font
 * can't be loaded. Messages resolve from `laranail-console::console.font_*`.
 */
class FontException extends ConsoleException
{
    public static function unknown(string $name): static
    {
        return self::fromKey('console.font_unknown', ['name' => $name]);
    }

    public static function unreadable(string $path): static
    {
        return self::fromKey('console.font_unreadable', ['path' => $path]);
    }

    public static function notAFigletFont(string $path): static
    {
        return self::fromKey('console.font_invalid', ['path' => $path]);
    }
}

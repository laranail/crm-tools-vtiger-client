<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * A typed builder for CSI (Control Sequence Introducer) escape sequences:
 * `ESC [ <params> <final-byte>`. Underpins {@see Terminal}'s cursor/erase
 * emission instead of ad-hoc string concatenation.
 *
 * Re-derived from the ECMA-48 specification. Returns raw sequences; gate
 * emission on terminal capability at the call site.
 */
final class Csi
{
    public const string ESC = "\e";

    public const string CSI = "\e[";

    /**
     * Build a CSI sequence from a final byte and optional numeric/string params.
     *
     *   Csi::sequence('A', 3)      => "\e[3A"   (cursor up 3)
     *   Csi::sequence('H', 2, 5)   => "\e[2;5H" (cursor to row 2, col 5)
     *   Csi::sequence('J', 2)      => "\e[2J"   (clear screen)
     */
    public static function sequence(string $finalByte, int|string ...$params): string
    {
        return self::CSI . implode(';', array_map(static fn (int|string $p): string => (string) $p, $params)) . $finalByte;
    }
}

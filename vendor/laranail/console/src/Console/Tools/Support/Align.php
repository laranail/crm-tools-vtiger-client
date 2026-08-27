<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Horizontal alignment of a block of lines within a target width — display-width
 * aware (ANSI/wide-char safe via {@see DisplayWidth}). The single helper every
 * widget uses for left/center/right placement.
 */
final class Align
{
    public const string LEFT = 'left';

    public const string CENTER = 'center';

    public const string RIGHT = 'right';

    /** Full-justify (used by Paragraph); not handled by pad()/place(). */
    public const string JUSTIFY = 'justify';

    /**
     * Pad each line out to $width according to $align (every returned line has the
     * exact display width $width).
     *
     * @param list<string> $lines
     * @return list<string>
     */
    public static function pad(array $lines, int $width, string $align = self::LEFT): array
    {
        return array_map(
            static fn (string $line): string => match ($align) {
                self::RIGHT => DisplayWidth::padLeft($line, $width),
                self::CENTER => DisplayWidth::center($line, $width),
                default => DisplayWidth::pad($line, $width),
            },
            $lines,
        );
    }

    /**
     * Place a block (kept at its own width) within $width by prefixing a margin —
     * used to centre/right-align a sub-block (e.g. a box) without stretching it.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    public static function place(array $lines, int $width, string $align = self::LEFT): array
    {
        if ($align === self::LEFT || $lines === []) {
            return $lines;
        }

        $blockWidth = DisplayWidth::maxWidth($lines);
        $margin = match ($align) {
            self::RIGHT => max($width - $blockWidth, 0),
            self::CENTER => (int) max(floor(($width - $blockWidth) / 2), 0),
            default => 0,
        };

        if ($margin === 0) {
            return $lines;
        }

        $prefix = str_repeat(' ', $margin);

        return array_map(static fn (string $line): string => $prefix . $line, $lines);
    }

    /**
     * Normalise an alignment token to a known value (default left).
     */
    public static function normalize(string $align): string
    {
        return match (strtolower($align)) {
            self::RIGHT => self::RIGHT,
            self::CENTER => self::CENTER,
            default => self::LEFT,
        };
    }
}

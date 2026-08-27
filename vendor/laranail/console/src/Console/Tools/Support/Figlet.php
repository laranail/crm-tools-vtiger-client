<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

use Simtabi\Laranail\Console\Tools\Exceptions\FontException;
use Simtabi\Laranail\Console\Tools\Support\Fonts\BuiltinFonts;
use Simtabi\Laranail\Console\Tools\Support\Fonts\FontDefinition;

/**
 * FIGlet-style big-text renderer.
 *
 * Loads either a bundled font by name (from the {@see BuiltinFonts} registry) or a
 * standard FIGlet `.flf` file by path, and renders a string into an array of
 * equal-width lines (one block of `height()` rows). Unknown glyphs fall back to
 * their upper-case form, then to a blank block, so rendering never throws on
 * content. Used by the Banner widget.
 */
final class Figlet
{
    /** @var array<string, self> name/path => parsed font */
    private static array $cache = [];

    /**
     * @param array<int|string, list<string>> $chars char => rows (equal width per char)
     */
    private function __construct(
        private readonly int $height,
        private readonly array $chars,
        private readonly int $gap,
    ) {}

    public static function font(string $name): self
    {
        return self::$cache[$name] ??= str_ends_with(strtolower($name), '.flf')
            ? self::parseFlf($name)
            : self::loadBuiltin($name);
    }

    /**
     * Names of the bundled fonts.
     *
     * @return list<string>
     */
    public static function builtins(): array
    {
        return BuiltinFonts::names();
    }

    public function height(): int
    {
        return $this->height;
    }

    /**
     * Render text to a block of lines (each line the same display width).
     *
     * @return list<string>
     */
    public function render(string $text): array
    {
        $rows = array_fill(0, $this->height, '');
        $first = true;

        foreach (mb_str_split($text) as $char) {
            $glyph = $this->glyph($char);

            for ($i = 0; $i < $this->height; $i++) {
                if (! $first && $this->gap > 0) {
                    $rows[$i] .= str_repeat(' ', $this->gap);
                }

                $rows[$i] .= $glyph[$i];
            }

            $first = false;
        }

        return array_values($rows);
    }

    /**
     * Rows for a single character, falling back to its upper-case form then to a
     * blank block of the font height.
     *
     * @return list<string>
     */
    private function glyph(string $char): array
    {
        $rows = $this->chars[$char] ?? $this->chars[mb_strtoupper($char)] ?? null;

        if ($rows !== null) {
            return $rows;
        }

        $width = isset($this->chars[' ']) ? DisplayWidth::of($this->chars[' '][0]) : 3;

        return array_fill(0, $this->height, str_repeat(' ', $width));
    }

    private static function loadBuiltin(string $name): self
    {
        $def = BuiltinFonts::get($name);

        if (! $def instanceof FontDefinition) {
            throw FontException::unknown($name);
        }

        return new self($def->height, $def->chars, $def->gap);
    }

    private static function parseFlf(string $path): self
    {
        // font() must not receive untrusted input; reject null-byte poisoning and
        // require an existing, readable, regular .flf file (no directories/devices).
        if (str_contains($path, "\0") || ! is_file($path) || ! is_readable($path)) {
            throw FontException::unreadable($path);
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) ?: [];
        $header = (string) ($lines[0] ?? '');

        if (! str_starts_with($header, 'flf2a')) {
            throw FontException::notAFigletFont($path);
        }

        $parts = preg_split('/\s+/', $header) ?: [];
        $hardblank = substr($parts[0], 5, 1) ?: '$';
        $height = max((int) ($parts[1] ?? 1), 1);
        $commentLines = max((int) ($parts[5] ?? 0), 0);

        $cursor = 1 + $commentLines;
        $chars = [];

        // ASCII 32..126 are stored in order, each glyph `height` lines, every
        // line terminated by an endmark (the last char of the line), the final
        // line by two. We strip the endmark run and swap the hardblank for space.
        for ($code = 32; $code <= 126; $code++) {
            $glyph = [];

            for ($row = 0; $row < $height; $row++) {
                $line = $lines[$cursor++] ?? null;

                if ($line === null) {
                    break 2;
                }

                $glyph[] = $line;
            }

            if (count($glyph) < $height) {
                break;
            }

            $chars[chr($code)] = self::normalizeGlyph($glyph, $hardblank);
        }

        return new self($height, $chars, 0);
    }

    /**
     * Strip trailing endmark characters, swap the hardblank for a space, and pad
     * every row to the glyph's widest row.
     *
     * @param list<string> $glyph
     * @return list<string>
     */
    private static function normalizeGlyph(array $glyph, string $hardblank): array
    {
        $endmark = substr($glyph[0], -1) ?: '@';

        $rows = array_map(
            static fn (string $line): string => str_replace(
                $hardblank,
                ' ',
                rtrim($line, $endmark),
            ),
            $glyph,
        );

        $width = DisplayWidth::maxWidth($rows);

        return array_values(array_map(static fn (string $row): string => DisplayWidth::pad($row, $width), $rows));
    }
}

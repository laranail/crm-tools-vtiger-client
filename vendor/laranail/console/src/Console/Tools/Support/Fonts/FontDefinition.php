<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support\Fonts;

/**
 * An immutable big-text font definition: a fixed row height, a map of character →
 * glyph rows (each glyph the same number of rows as `height`), and the default gap
 * (in columns) rendered between adjacent glyphs. Consumed by the Figlet renderer.
 *
 * Keys are character strings; PHP coerces numeric-string keys (`"0".."9"`) to int,
 * hence the `int|string` key type.
 */
final readonly class FontDefinition
{
    /**
     * @param array<int|string, list<string>> $chars character => glyph rows
     */
    public function __construct(
        public int $height,
        public array $chars,
        public int $gap = 1,
    ) {}

    /**
     * @param array<int|string, list<string>> $chars
     */
    public static function make(int $height, array $chars, int $gap = 1): self
    {
        return new self($height, $chars, $gap);
    }
}

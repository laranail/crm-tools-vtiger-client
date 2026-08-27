<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Enums;

/**
 * SGR (Select Graphic Rendition) parameter codes — text styles plus their
 * granular per-attribute resets and a few rarely-exposed styles
 * (framed/encircled/overlined/conceal/blink-rapid). Lets callers turn off a
 * single attribute instead of resetting everything with `\e[0m`.
 *
 * Re-derived from the ECMA-48 specification (numeric facts). Builders return raw
 * sequences; gate emission on {@see Capabilities::supportsColor()} at the call site.
 */
enum Sgr: int
{
    case Reset = 0;
    case Bold = 1;
    case Faint = 2;
    case Italic = 3;
    case Underline = 4;
    case Blink = 5;
    case BlinkRapid = 6;
    case Reverse = 7;
    case Conceal = 8;
    case Strikethrough = 9;

    // Granular "off" / reset codes.
    case BoldOff = 22; // also turns off faint (normal intensity)
    case ItalicOff = 23;
    case UnderlineOff = 24;
    case BlinkOff = 25;
    case ReverseOff = 27;
    case Reveal = 28; // conceal off
    case StrikethroughOff = 29;

    // Rarely-exposed styles and their resets.
    case Framed = 51;
    case Encircled = 52;
    case Overlined = 53;
    case FramedEncircledOff = 54;
    case OverlinedOff = 55;

    /**
     * This single code as an SGR escape sequence, e.g. `\e[4m`.
     */
    public function open(): string
    {
        return "\e[{$this->value}m";
    }

    /**
     * Combine several codes into one sequence, e.g. `\e[1;4m`.
     */
    public static function sequence(self ...$codes): string
    {
        $params = implode(';', array_map(static fn (self $c): string => (string) $c->value, $codes));

        return "\e[{$params}m";
    }

    /**
     * Wrap text in the given styles, closing with a full reset.
     */
    public static function wrap(string $text, self ...$codes): string
    {
        return self::sequence(...$codes) . $text . self::Reset->open();
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Enums;

/**
 * Box-drawing glyph families.
 *
 * Each case yields a complete, self-consistent set of corner, edge and
 * junction glyphs. Renderers must draw a whole box from one family — mixing
 * families is what produces misaligned output.
 */
enum BorderStyle: string
{
    case Ascii = 'ascii';
    case Light = 'light';
    case Heavy = 'heavy';
    case Rounded = 'rounded';
    case Double = 'double';

    /**
     * @return array{tl:string,tr:string,bl:string,br:string,h:string,v:string,teeDown:string,teeUp:string,teeLeft:string,teeRight:string,cross:string}
     */
    public function glyphs(): array
    {
        return match ($this) {
            self::Ascii => $this->set('+', '+', '+', '+', '-', '|', '+', '+', '+', '+', '+'),
            self::Light => $this->set('┌', '┐', '└', '┘', '─', '│', '┬', '┴', '┤', '├', '┼'),
            self::Heavy => $this->set('┏', '┓', '┗', '┛', '━', '┃', '┳', '┻', '┫', '┣', '╋'),
            self::Rounded => $this->set('╭', '╮', '╰', '╯', '─', '│', '┬', '┴', '┤', '├', '┼'),
            self::Double => $this->set('╔', '╗', '╚', '╝', '═', '║', '╦', '╩', '╣', '╠', '╬'),
        };
    }

    /**
     * The ASCII family, used as the fallback when Unicode is unavailable.
     */
    public function fallback(): self
    {
        return self::Ascii;
    }

    /**
     * @return array{tl:string,tr:string,bl:string,br:string,h:string,v:string,teeDown:string,teeUp:string,teeLeft:string,teeRight:string,cross:string}
     */
    private function set(
        string $tl,
        string $tr,
        string $bl,
        string $br,
        string $h,
        string $v,
        string $teeDown,
        string $teeUp,
        string $teeLeft,
        string $teeRight,
        string $cross,
    ): array {
        return ['tl' => $tl, 'tr' => $tr, 'bl' => $bl, 'br' => $br, 'h' => $h, 'v' => $v, 'teeDown' => $teeDown, 'teeUp' => $teeUp, 'teeLeft' => $teeLeft, 'teeRight' => $teeRight, 'cross' => $cross];
    }
}

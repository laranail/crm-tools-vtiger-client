<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Enums;

/**
 * Named spinner frame sets, with an ASCII fallback for terminals without
 * Unicode support.
 */
enum SpinnerFrames: string
{
    case Braille = 'braille';
    case Dots = 'dots';
    case Line = 'line';
    case Breath = 'breath';

    /**
     * @return list<string>
     */
    public function frames(bool $unicode = true): array
    {
        if (! $unicode) {
            return ['-', '\\', '|', '/'];
        }

        return match ($this) {
            self::Braille => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
            self::Dots => ['⠁', '⠂', '⠄', '⡀', '⢀', '⠠', '⠐', '⠈'],
            self::Line => ['-', '\\', '|', '/'],
            self::Breath => ['.', 'o', 'O', '°', 'O', 'o'],
        };
    }

    public static function fromName(?string $name): self
    {
        return $name !== null ? (self::tryFrom($name) ?? self::Braille) : self::Braille;
    }
}

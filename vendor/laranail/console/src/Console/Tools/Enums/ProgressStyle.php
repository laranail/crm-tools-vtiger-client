<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Enums;

/**
 * Progress-bar layout presets. The placeholders are Symfony ProgressBar tokens
 * (plus the package's custom %rate%).
 */
enum ProgressStyle: string
{
    case Minimal = 'minimal';
    case Classic = 'classic';
    case Detailed = 'detailed';

    public function format(): string
    {
        return match ($this) {
            self::Minimal => ' %bar% %percent:3s%%',
            self::Classic => ' %bar% %percent:3s%% %current%/%max%',
            self::Detailed => ' %bar% %percent:3s%% %current%/%max% • %elapsed% • ETA %estimated% • %rate%/s',
        };
    }

    public static function fromName(?string $name): self
    {
        return $name !== null ? (self::tryFrom($name) ?? self::Detailed) : self::Detailed;
    }
}

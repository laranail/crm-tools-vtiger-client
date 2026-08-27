<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Human-friendly, three-tier duration formatting:
 *   < 60s   -> "45.2s"
 *   < 1h    -> "2m 18s"
 *   >= 1h   -> "1h 8m"
 */
final class TimeFormat
{
    public static function duration(float $seconds): string
    {
        if ($seconds < 0) {
            $seconds = 0.0;
        }

        if ($seconds < 60) {
            // Guard the rounding boundary: 59.97s rounds to "60.0" at one
            // decimal, which belongs in the minutes tier, not "60s".
            $rounded = round($seconds, 1);

            if ($rounded < 60.0) {
                return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.') . 's';
            }

            $seconds = 60.0;
        }

        if ($seconds < 3600) {
            $minutes = intdiv((int) $seconds, 60);
            $secs = (int) $seconds % 60;

            return "{$minutes}m {$secs}s";
        }

        $hours = intdiv((int) $seconds, 3600);
        $minutes = intdiv((int) $seconds % 3600, 60);

        return "{$hours}h {$minutes}m";
    }

    /**
     * Millisecond-scale formatting with adaptive units:
     *   < 1s   -> "812.40 ms"
     *   < 1m   -> "3.45 s"
     *   >= 1m  -> "1.20 min"
     */
    public static function fromMillis(float $ms): string
    {
        if ($ms < 0) {
            $ms = 0.0;
        }

        return match (true) {
            $ms < 1000 => number_format($ms, 2) . ' ms',
            $ms < 60000 => number_format($ms / 1000, 2) . ' s',
            default => number_format($ms / 60000, 2) . ' min',
        };
    }
}

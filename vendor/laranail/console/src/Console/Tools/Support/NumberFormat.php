<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Compact numeric formatting shared by the chart/widget family: fixed-decimal
 * rounding with trailing zeros (and a dangling decimal point) trimmed, so `3.50`
 * renders as `3.5` and `12.00` as `12`.
 */
final class NumberFormat
{
    /**
     * Round to `$decimals` places, then strip trailing zeros and any bare `.`.
     */
    public static function trim(float $value, int $decimals = 2): string
    {
        return rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');
    }
}

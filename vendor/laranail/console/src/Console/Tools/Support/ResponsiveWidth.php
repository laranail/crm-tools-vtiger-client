<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * The single source of truth for "how wide may this widget render".
 *
 * Resolution: an explicit `->width()` always wins; otherwise, when responsiveness
 * is enabled (config `console.responsive`, default true, AND the widget opts in),
 * the cap is the detected terminal width; otherwise there is no cap (legacy
 * behaviour — content is not clamped).
 *
 * @internal Internal helper behind every widget's `responsive()`/`width()`.
 */
final class ResponsiveWidth
{
    /**
     * Whether width-responsiveness is globally enabled.
     */
    public static function enabled(): bool
    {
        return (bool) Config::get('responsive', true);
    }

    /**
     * The detected terminal width.
     */
    public static function terminal(?Capabilities $capabilities = null): int
    {
        return ($capabilities ?? Capabilities::detect())->width();
    }

    /**
     * The maximum width a widget should occupy, or null for "no cap" (legacy).
     *
     * @param int|null $explicit the widget's explicit ->width(), if any
     * @param bool $widgetWantsResponsive the widget's ->responsive() flag
     */
    public static function cap(?int $explicit, bool $widgetWantsResponsive = true, ?Capabilities $capabilities = null): ?int
    {
        if ($explicit !== null) {
            return max($explicit, 1);
        }

        if (! $widgetWantsResponsive || ! self::enabled()) {
            return null;
        }

        return max(self::terminal($capabilities), 1);
    }
}

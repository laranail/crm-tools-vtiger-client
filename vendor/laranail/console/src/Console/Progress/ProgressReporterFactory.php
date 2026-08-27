<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Progress;

use Symfony\Component\Tui\Tui;

/**
 * Picks the active {@see ProgressReporter}: the symfony/tui renderer when the
 * `console.tui.progress` opt-in is on AND the experimental package is installed,
 * otherwise the always-available laravel/prompts renderer.
 *
 * Default (opt-in off) is the prompts renderer, so behaviour is unchanged for
 * every existing consumer and nothing depends on symfony/tui being present.
 */
final class ProgressReporterFactory
{
    public static function make(): ProgressReporter
    {
        if (self::tuiEnabled()) {
            return new TuiProgressReporter;
        }

        return new PromptsProgressReporter;
    }

    public static function tuiEnabled(): bool
    {
        return (bool) config('laranail.console.tui.progress', false)
            && class_exists(Tui::class);
    }
}

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Output rendering
    |--------------------------------------------------------------------------
    |
    | "auto" lets the package detect terminal capabilities at runtime. Force a
    | value to override detection (useful in CI or constrained terminals).
    |
    | unicode : auto | true | false   — use Unicode glyphs or ASCII fallbacks
    | colors  : auto | always | never — emit ANSI colour
    | symbols : auto | fancy | ascii  — status/tree/box glyph set
    | width   : null (autodetect) or an integer column count fallback
    |
    */
    'output' => [
        'unicode' => env('CONSOLE_UNICODE', 'auto'),
        'colors' => env('CONSOLE_COLORS', 'auto'),
        'symbols' => env('CONSOLE_SYMBOLS', 'auto'),
        'width' => env('CONSOLE_WIDTH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Responsive layout
    |--------------------------------------------------------------------------
    | When true, widgets clamp their output to the detected terminal width so
    | content never overflows a narrow terminal. An explicit ->width() always
    | wins, and a widget's ->responsive(false) opts out per call.
    */
    'responsive' => env('CONSOLE_RESPONSIVE', true),

    /*
    |--------------------------------------------------------------------------
    | Validate config at boot
    |--------------------------------------------------------------------------
    |
    | When true, the console.* config is validated on every Artisan run (console
    | only — web requests are never affected) and a clear error is thrown on a bad
    | value. Off by default; you can also run `php artisan laranail::console.check`
    | or call Console::validateConfig() on demand.
    |
    */

    'validate_config' => env('CONSOLE_VALIDATE_CONFIG', false),

    /*
    |--------------------------------------------------------------------------
    | Theme (design tokens)
    |--------------------------------------------------------------------------
    | The semantic colour palette the design system (typography, banners,
    | badges, Text/Style) shares. Override any role to re-skin the whole UI;
    | accepts any colour spec (hex, rgb(), hsl(), named, @256).
    */
    'theme' => [
        // A built-in preset palette (dracula, nord, solarized, monochrome, github),
        // or null for the default. `palette` below overrides individual roles on top.
        'preset' => env('CONSOLE_THEME_PRESET'),

        'palette' => [
            // 'primary' => '#7c3aed', 'accent' => '#06b6d4', 'success' => '#16a34a',
            // 'warning' => '#d97706', 'danger' => '#dc2626', 'info' => '#2563eb',
            // 'muted' => '#64748b',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Spinner defaults
    |--------------------------------------------------------------------------
    | frames: braille | dots | line | breath
    */
    'spinner' => [
        'frames' => env('CONSOLE_SPINNER_FRAMES', 'braille'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Progress bar defaults
    |--------------------------------------------------------------------------
    | format: minimal | classic | detailed
    | glyphs: blocks | ascii | dots | arrows | gradient
    */
    'progress' => [
        'format' => env('CONSOLE_PROGRESS_FORMAT', 'detailed'),
        'glyphs' => env('CONSOLE_PROGRESS_GLYPHS', 'blocks'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TUI (experimental)
    |--------------------------------------------------------------------------
    | Opt in to the experimental symfony/tui full-screen renderer for the
    | ProgressReporter seam. Off by default → the laravel/prompts renderer (works
    | everywhere, incl. CI). Only takes effect when symfony/tui is installed
    | (`composer require symfony/tui`); otherwise it silently falls back.
    */
    'tui' => [
        'progress' => env('CONSOLE_TUI_PROGRESS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Banner
    |--------------------------------------------------------------------------
    | font  : default FIGlet font for big-text banners — a bundled font name
    |         (e.g. "block") or a path to a ".flf" file. null = plain title.
    | width : default inner content width, or null to auto-fit the terminal.
    */
    'banner' => [
        'font' => env('CONSOLE_BANNER_FONT'),
        'width' => env('CONSOLE_BANNER_WIDTH'),

        // Custom named themes for Banner::theme('name'); each is a subset of
        // [font, color, gradient (list), border (ascii|light|heavy|rounded|double),
        // align, padding]. Built-in names: success, error, warning, info, plain.
        'themes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution summary
    |--------------------------------------------------------------------------
    | Layout for Console::summary(). divider_width is the top rule length;
    | label_pad aligns the metric labels; message_max truncates long error
    | messages; rate_good / rate_warn are the success-rate colour thresholds
    | (>= good is green, >= warn is yellow, else red).
    */
    'summary' => [
        'divider_width' => 60,
        'label_pad' => 16,
        'message_max' => 80,
        'rate_good' => 100,
        'rate_warn' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Emoji
    |--------------------------------------------------------------------------
    | mode   : auto | unicode | ascii — "auto" follows Unicode capability.
    | custom : extra/override emoji, keyed by name. Each value is
    |          [unicode, ascii] or a single string used for both, e.g.
    |          'deploy' => ['🚀', '>>'].
    */
    'emoji' => [
        'mode' => env('CONSOLE_EMOJI_MODE', 'auto'),
        'custom' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Interactive menu
    |--------------------------------------------------------------------------
    | Defaults for Console::menu(). foreground is a hex/colour name (or null),
    | width is the frame width (or null to auto-fit).
    */
    'menu' => [
        'foreground' => env('CONSOLE_MENU_FG'),
        'width' => env('CONSOLE_MENU_WIDTH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hyperlinks
    |--------------------------------------------------------------------------
    | Only these URL schemes are allowed in OSC-8 terminal hyperlinks. Anything
    | else is rendered as plain "label (url)" text. Prevents link spoofing.
    */
    'links' => [
        'allowed_schemes' => ['http', 'https', 'mailto'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error logging
    |--------------------------------------------------------------------------
    | redact_keys          : context keys scrubbed before an exception is logged
    | trace_in_debug_only  : only log stack traces when app.debug is true
    | channel              : log channel name, or null for the default
    */
    'logging' => [
        'redact_keys' => ['password', 'secret', 'token', 'key', 'authorization'],
        'trace_in_debug_only' => true,
        'channel' => env('CONSOLE_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Interaction
    |--------------------------------------------------------------------------
    | When true, asking for a required value (e.g. a password) in a
    | non-interactive session throws instead of silently returning empty.
    */
    'interaction' => [
        'non_interactive_required_throws' => (bool) env('CONSOLE_REQUIRE_INTERACTIVE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    | Translation locale for console strings. null follows the application locale.
    */
    'locale' => env('CONSOLE_LOCALE'),

];

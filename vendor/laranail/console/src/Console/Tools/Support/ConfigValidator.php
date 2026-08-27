<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

use Simtabi\Laranail\Console\Tools\Exceptions\InvalidColorException;
use Simtabi\Laranail\Console\Tools\Theme\Presets;

/**
 * Validates the `console.*` configuration and returns human-readable error
 * messages (empty = valid). Reads config through {@see Config} — the same path the
 * toolkit uses at runtime — so validation matches actual behaviour.
 *
 * Conservative and lenient: it only flags clearly-invalid known keys and ignores
 * unknown/extra keys, so it never produces false positives. Surfaced via
 * `Console::validateConfig()`, the `laranail::console.check` command, and (opt-in)
 * a fail-fast boot check.
 */
final class ConfigValidator
{
    private const array SYMBOL_MODES = ['auto', 'fancy', 'ascii'];

    private const array EMOJI_MODES = ['auto', 'unicode', 'ascii'];

    /**
     * @return list<string>
     */
    public static function validate(): array
    {
        $errors = [];

        // theme.palette — each role must be a parseable colour spec.
        /** @var array<string, mixed> $palette */
        $palette = (array) Config::get('theme.palette', []);
        foreach ($palette as $role => $spec) {
            if (! is_string($spec)) {
                continue;
            }

            try {
                Color::parseStrict($spec);
            } catch (InvalidColorException) {
                $errors[] = "theme.palette.{$role}: '{$spec}' is not a valid colour spec.";
            }
        }

        // theme.preset — null or a known preset.
        $preset = Config::get('theme.preset');
        if (is_string($preset) && $preset !== '' && ! Presets::has($preset)) {
            $errors[] = "theme.preset: unknown preset '{$preset}'. Available: " . implode(', ', Presets::names()) . '.';
        }

        // output.symbols / emoji.mode — enums.
        $errors = [
            ...$errors,
            ...self::checkEnum('output.symbols', self::SYMBOL_MODES, 'auto'),
            ...self::checkEnum('emoji.mode', self::EMOJI_MODES, 'auto'),
        ];

        // responsive — boolean.
        if (! is_bool(Config::get('responsive', true))) {
            $errors[] = 'responsive: must be a boolean (true|false).';
        }

        // banner.font — string or null.
        $font = Config::get('banner.font');
        if ($font !== null && ! is_string($font)) {
            $errors[] = 'banner.font: must be a string (font name or .flf path) or null.';
        }

        return $errors;
    }

    /**
     * @param list<string> $allowed
     * @return list<string>
     */
    private static function checkEnum(string $key, array $allowed, mixed $default): array
    {
        $value = Config::get($key, $default);

        if (in_array($value, $allowed, true)) {
            return [];
        }

        $shown = is_scalar($value) ? (string) $value : gettype($value);

        return ["{$key}: '{$shown}' must be one of: " . implode(', ', $allowed) . '.'];
    }
}

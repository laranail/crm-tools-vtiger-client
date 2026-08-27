<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Enums\BorderStyle;
use Simtabi\Laranail\Console\Tools\Support\Config;
use Simtabi\Laranail\Console\Tools\Theme\Theme;

/**
 * A named banner preset — font + colour/gradient + border + alignment + padding —
 * applied via {@see Banner::theme()}. Built-in presets (success/error/warning/
 * info/plain) draw their colours from the design-system {@see Theme} palette; you
 * can also define custom presets under `config('laranail.console.banner.themes.*')`.
 *
 * Null fields mean "leave the banner's current value" — so a theme sets defaults
 * and later explicit setters still override.
 */
final readonly class BannerTheme
{
    /**
     * @param list<string>|null $gradient
     */
    public function __construct(
        public ?string $font = null,
        public ?string $color = null,
        public ?array $gradient = null,
        public ?BorderStyle $border = null,
        public ?string $align = null,
        public ?int $padding = null,
    ) {}

    /**
     * Resolve a preset from a name (config custom → built-in) or pass an instance
     * through unchanged.
     */
    public static function make(string|self $preset): self
    {
        if ($preset instanceof self) {
            return $preset;
        }

        $custom = Config::get("banner.themes.{$preset}");

        if (is_array($custom)) {
            return self::fromConfig($custom);
        }

        return self::named($preset);
    }

    private static function named(string $name): self
    {
        $palette = Theme::resolve()->palette();

        return match ($name) {
            'success' => new self(color: $palette->get('success'), border: BorderStyle::Light, align: 'center', padding: 1),
            'error', 'danger' => new self(color: $palette->get('danger'), border: BorderStyle::Heavy, align: 'center', padding: 1),
            'warning' => new self(color: $palette->get('warning'), border: BorderStyle::Light, align: 'center', padding: 1),
            'info' => new self(color: $palette->get('info'), border: BorderStyle::Light, align: 'center', padding: 1),
            default => new self(align: 'center'),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function fromConfig(array $config): self
    {
        $border = $config['border'] ?? null;

        return new self(
            font: isset($config['font']) ? (string) $config['font'] : null,
            color: isset($config['color']) ? (string) $config['color'] : null,
            gradient: isset($config['gradient']) && is_array($config['gradient'])
                ? array_values(array_map(strval(...), $config['gradient']))
                : null,
            border: is_string($border) ? BorderStyle::tryFrom($border) : null,
            align: isset($config['align']) ? (string) $config['align'] : null,
            padding: isset($config['padding']) ? (int) $config['padding'] : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Theme;

use InvalidArgumentException;
use Simtabi\Laranail\Console\Tools\Support\Config;
use Simtabi\Laranail\Console\Tools\Support\Style;

/**
 * The design system's stylesheet: a {@see Palette} plus a resolved {@see Style}
 * per UI element (headings, paragraph, link, quote, code, list marker, rule,
 * status roles…). Element styles derive from the palette by default, so
 * re-skinning the palette restyles everything; any element can be overridden.
 *
 * Immutable + concurrency-safe: `resolve()` reads `config('laranail.console.theme.*')` and
 * builds a fresh instance (no shared mutable state).
 */
final readonly class Theme
{
    /**
     * @param array<string, Style> $overrides element => Style overrides
     */
    public function __construct(
        public Palette $palette,
        private array $overrides = [],
    ) {}

    /**
     * @param array<string, string> $paletteOverrides
     * @param array<string, Style> $styleOverrides
     */
    public static function make(array $paletteOverrides = [], array $styleOverrides = []): self
    {
        return new self(Palette::make($paletteOverrides), $styleOverrides);
    }

    /**
     * A theme built from a named built-in {@see Presets preset} (e.g. `nord`,
     * `dracula`), optionally with per-element style overrides.
     *
     * @param array<string, Style> $styleOverrides
     *
     * @throws InvalidArgumentException on an unknown preset name
     */
    public static function preset(string $name, array $styleOverrides = []): self
    {
        return new self(Palette::preset($name), $styleOverrides);
    }

    /**
     * The active theme. A `console.theme.preset` (if set and known) supplies the
     * base palette; `console.theme.palette` entries then override it role-by-role.
     */
    public static function resolve(): self
    {
        $preset = Config::get('theme.preset');
        $base = is_string($preset) && $preset !== '' ? (Presets::get($preset) ?? []) : [];

        /** @var array<string, string> $overrides */
        $overrides = (array) Config::get('theme.palette', []);

        return new self(Palette::make([...$base, ...$overrides]));
    }

    public function palette(): Palette
    {
        return $this->palette;
    }

    public function color(string $role): ?string
    {
        return $this->palette->get($role);
    }

    public function withStyle(string $element, Style $style): self
    {
        return new self($this->palette, [...$this->overrides, $element => $style]);
    }

    /**
     * The {@see Style} for a UI element. Overrides win; otherwise a sensible
     * default derived from the palette.
     */
    public function style(string $element): Style
    {
        if (isset($this->overrides[$element])) {
            return $this->overrides[$element];
        }

        $p = $this->palette;
        $s = Style::make();

        return match ($element) {
            'h1' => $s->fg($p->get('primary') ?? '#7c3aed')->bold(),
            'h2' => $s->fg($p->get('accent') ?? '#06b6d4')->bold(),
            'h3' => $s->bold(),
            'h4', 'h5', 'h6' => $s->fg($p->get('muted') ?? '#64748b')->bold(),
            'paragraph' => $s,
            'muted' => $s->fg($p->get('muted') ?? '#64748b'),
            'link' => $s->fg($p->get('info') ?? '#2563eb')->underline(),
            'quote' => $s->fg($p->get('muted') ?? '#64748b')->italic(),
            'code' => $s->fg($p->get('accent') ?? '#06b6d4'),
            'rule', 'list_marker' => $s->fg($p->get('muted') ?? '#64748b'),
            'success' => $s->fg($p->get('success') ?? '#16a34a'),
            'warning' => $s->fg($p->get('warning') ?? '#d97706'),
            'danger', 'error' => $s->fg($p->get('danger') ?? '#dc2626'),
            'info' => $s->fg($p->get('info') ?? '#2563eb'),
            'primary' => $s->fg($p->get('primary') ?? '#7c3aed'),
            'accent' => $s->fg($p->get('accent') ?? '#06b6d4'),
            default => $s,
        };
    }
}

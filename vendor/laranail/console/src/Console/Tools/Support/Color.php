<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

use Simtabi\Laranail\Console\Tools\Exceptions\InvalidColorException;

/**
 * Colour parsing + emission with graceful degradation.
 *
 * Accepts hex (`#rrggbb`/`rrggbb`), `rgb(r,g,b)`, `hsl(h,s%,l%)`, named colours
 * (e.g. `red`, `orange`, `slate`) and xterm-256 indices (`@N`). Emits at the best
 * depth the terminal supports — 24-bit truecolor → xterm-256 → nearest ANSI-16 —
 * and returns text unstyled when {@see Capabilities::supportsColor()} is false, so
 * NO_COLOR and non-TTY pipes stay clean.
 */
final readonly class Color
{
    /** Common colour names → hex. */
    private const array NAMES = [
        'black' => '#000000', 'white' => '#ffffff', 'red' => '#ff0000',
        'green' => '#00aa00', 'lime' => '#00ff00', 'blue' => '#0000ff',
        'yellow' => '#ffff00', 'cyan' => '#00ffff', 'magenta' => '#ff00ff',
        'gray' => '#808080', 'grey' => '#808080', 'silver' => '#c0c0c0',
        'maroon' => '#800000', 'olive' => '#808000', 'navy' => '#000080',
        'teal' => '#008080', 'purple' => '#800080', 'orange' => '#ff8800',
        'pink' => '#ff69b4', 'brown' => '#a52a2a', 'gold' => '#ffd700',
        'slate' => '#64748b', 'indigo' => '#4b0082', 'violet' => '#ee82ee',
        'crimson' => '#dc143c', 'coral' => '#ff7f50', 'salmon' => '#fa8072',
        'turquoise' => '#40e0d0', 'aqua' => '#00ffff', 'mint' => '#3eb489',
    ];

    private Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
    }

    public static function make(?Capabilities $capabilities = null): self
    {
        return new self($capabilities);
    }

    public static function isValidHex(string $hex): bool
    {
        return preg_match('/^#?[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /**
     * Like {@see parse()} but throws on an unparseable colour instead of returning
     * null — use it to validate user/config colour input up front.
     *
     * @throws InvalidColorException
     */
    public static function parseStrict(string $color): string
    {
        return self::parse($color) ?? throw InvalidColorException::forValue($color);
    }

    /**
     * Normalise any supported colour spec to a `#rrggbb` hex string, or null when
     * it can't be parsed.
     */
    public static function parse(string $color): ?string
    {
        $color = trim($color);

        if (self::isValidHex($color)) {
            return '#' . strtolower(ltrim($color, '#'));
        }

        $lower = strtolower($color);

        if (isset(self::NAMES[$lower])) {
            return self::NAMES[$lower];
        }

        // xterm-256 index, e.g. "@196"
        if (preg_match('/^@(\d{1,3})$/', $color, $m) === 1 && (int) $m[1] <= 255) {
            return self::hexFromXterm256((int) $m[1]);
        }

        // rgb(r, g, b)
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $color, $m) === 1) {
            return self::rgbToHex((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // hsl(h, s%, l%)
        if (preg_match('/^hsl\(\s*(\d{1,3})\s*,\s*(\d{1,3})%?\s*,\s*(\d{1,3})%?\s*\)$/i', $color, $m) === 1) {
            return self::hslToHex((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', self::clamp($r), self::clamp($g), self::clamp($b));
    }

    /**
     * Blend two colours by ratio $t (0 = $a, 1 = $b). Inputs are any parseable
     * colour spec; returns a hex string.
     */
    public static function blend(string $a, string $b, float $t): string
    {
        [$ar, $ag, $ab] = self::hexToRgb(self::parse($a) ?? '#000000');
        [$br, $bg, $bb] = self::hexToRgb(self::parse($b) ?? '#000000');
        $t = max(0.0, min(1.0, $t));

        return self::rgbToHex(
            (int) round($ar + ($br - $ar) * $t),
            (int) round($ag + ($bg - $ag) * $t),
            (int) round($ab + ($bb - $ab) * $t),
        );
    }

    /**
     * Whether the terminal background is (probably) dark — used by adaptive().
     * Reads COLORFGBG ("fg;bg"); defaults to dark when unknown.
     */
    public static function prefersDark(): bool
    {
        $fgbg = (string) getenv('COLORFGBG');

        if ($fgbg !== '' && preg_match('/(\d+)\s*;\s*(\d+)\s*$/', $fgbg, $m) === 1) {
            $bg = (int) $m[2];

            // 0–6 and 8 are dark; 7 and 15 (and other light) are light.
            return ! in_array($bg, [7, 15], true) && $bg < 7;
        }

        return true;
    }

    /**
     * Pick the colour suited to the detected background (light vs dark).
     */
    public static function adaptive(string $light, string $dark): string
    {
        return self::prefersDark() ? $dark : $light;
    }

    /**
     * The opening SGR sequence for a colour at the best available depth, or '' when
     * colour is unsupported / the spec is invalid. Set $background for 48/bg codes.
     */
    public function sequence(string $color, bool $background = false): string
    {
        $hex = self::parse($color);

        if (! $this->capabilities->supportsColor() || $hex === null) {
            return '';
        }

        [$r, $g, $b] = self::hexToRgb($hex);

        return $this->openSgr($r, $g, $b, $background);
    }

    /**
     * Wrap text in a foreground colour (any parseable spec).
     */
    public function fg(string $text, string $hex): string
    {
        $seq = $this->sequence($hex);

        return $seq === '' ? $text : $seq . $text . "\033[0m";
    }

    /**
     * Wrap text in a background colour (any parseable spec).
     */
    public function bg(string $text, string $color): string
    {
        $seq = $this->sequence($color, true);

        return $seq === '' ? $text : $seq . $text . "\033[0m";
    }

    /**
     * Interpolate a per-character foreground gradient across colour stops.
     *
     * @param list<string> $stops Colours (≥ 2; any parseable spec)
     */
    public function gradient(string $text, array $stops): string
    {
        $chars = mb_str_split($text);
        $count = count($chars);

        if (! $this->capabilities->supportsColor() || count($stops) < 2 || $count === 0) {
            return $text;
        }

        $rgbStops = [];
        foreach ($stops as $stop) {
            $rgbStops[] = self::hexToRgb(self::parse($stop) ?? '#ffffff');
        }
        $segments = count($rgbStops) - 1;
        $out = '';

        foreach ($chars as $i => $char) {
            $t = $count > 1 ? $i / ($count - 1) : 0.0;
            $scaled = $t * $segments;
            $idx = min((int) $scaled, $segments - 1);
            $local = $scaled - $idx;

            [$r1, $g1, $b1] = $rgbStops[$idx];
            [$r2, $g2, $b2] = $rgbStops[$idx + 1];

            $r = (int) round($r1 + ($r2 - $r1) * $local);
            $g = (int) round($g1 + ($g2 - $g1) * $local);
            $b = (int) round($b1 + ($b2 - $b1) * $local);

            $out .= $this->openSgr($r, $g, $b, false) . $char;
        }

        return $out . "\033[0m";
    }

    /**
     * Opening SGR sequence for an RGB colour at the best available depth:
     * 24-bit truecolor → xterm 256 → nearest ANSI-16. Foreground or background.
     */
    private function openSgr(int $r, int $g, int $b, bool $background): string
    {
        if ($this->capabilities->supportsTrueColor()) {
            return "\033[" . ($background ? '48' : '38') . ";2;{$r};{$g};{$b}m";
        }

        if ($this->capabilities->supports256Color()) {
            return "\033[" . ($background ? '48' : '38') . ';5;' . $this->rgbTo256($r, $g, $b) . 'm';
        }

        $base = $background ? 40 : 30;

        return "\033[" . ($base + $this->nearestAnsi($r, $g, $b)) . 'm';
    }

    /**
     * Quantize an RGB triple to the xterm 256-colour palette (6×6×6 cube +
     * grayscale ramp).
     */
    private function rgbTo256(int $r, int $g, int $b): int
    {
        if ($r === $g && $g === $b) {
            if ($r < 8) {
                return 16;
            }

            if ($r > 248) {
                return 231;
            }

            return 232 + (int) round(($r - 8) / 247 * 24);
        }

        return 16
            + 36 * (int) round($r / 255 * 5)
            + 6 * (int) round($g / 255 * 5)
            + (int) round($b / 255 * 5);
    }

    /**
     * Nearest of the 8 basic ANSI colours (0-7) for an RGB triple.
     */
    private function nearestAnsi(int $r, int $g, int $b): int
    {
        $palette = [
            0 => [0, 0, 0], 1 => [205, 0, 0], 2 => [0, 205, 0], 3 => [205, 205, 0],
            4 => [0, 0, 238], 5 => [205, 0, 205], 6 => [0, 205, 205], 7 => [229, 229, 229],
        ];

        $best = 7;
        $bestDist = PHP_INT_MAX;

        foreach ($palette as $code => [$pr, $pg, $pb]) {
            $dist = ($r - $pr) ** 2 + ($g - $pg) ** 2 + ($b - $pb) ** 2;

            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $code;
            }
        }

        return $best;
    }

    private static function hslToHex(int $h, int $s, int $l): string
    {
        $h = (($h % 360) + 360) % 360;
        $sf = max(0, min(100, $s)) / 100;
        $lf = max(0, min(100, $l)) / 100;

        $c = (1 - abs(2 * $lf - 1)) * $sf;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $lf - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0.0],
            $h < 120 => [$x, $c, 0.0],
            $h < 180 => [0.0, $c, $x],
            $h < 240 => [0.0, $x, $c],
            $h < 300 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };

        return self::rgbToHex(
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        );
    }

    private static function hexFromXterm256(int $index): string
    {
        if ($index < 16) {
            $basic = [
                '#000000', '#800000', '#008000', '#808000', '#000080', '#800080',
                '#008080', '#c0c0c0', '#808080', '#ff0000', '#00ff00', '#ffff00',
                '#0000ff', '#ff00ff', '#00ffff', '#ffffff',
            ];

            return $basic[$index];
        }

        if ($index >= 232) {
            $v = 8 + ($index - 232) * 10;

            return self::rgbToHex($v, $v, $v);
        }

        $index -= 16;
        $steps = [0, 95, 135, 175, 215, 255];

        return self::rgbToHex(
            $steps[intdiv($index, 36) % 6],
            $steps[intdiv($index, 6) % 6],
            $steps[$index % 6],
        );
    }

    private static function clamp(int $v): int
    {
        return max(0, min(255, $v));
    }
}

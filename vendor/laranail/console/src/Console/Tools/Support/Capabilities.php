<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

use Symfony\Component\Console\Terminal;

/**
 * Detects terminal capabilities — TTY, colour, Unicode and width — honouring
 * the package configuration and the de-facto environment standards
 * (NO_COLOR, FORCE_COLOR, TERM, COLORTERM, LANG).
 *
 * Resolution is lazy and cached. Configuration values of "auto" defer to
 * detection; explicit values force the outcome.
 */
final class Capabilities
{
    private ?bool $tty = null;

    private ?bool $colors = null;

    private ?bool $unicode = null;

    private ?int $width = null;

    /**
     * A forced capability profile for tests (see {@see fake()}). When set,
     * detect() returns it so every widget resolves against the same profile.
     */
    private static ?self $fake = null;

    /**
     * @param resource|null $stream Output stream to probe (defaults to STDOUT).
     */
    public function __construct(private $stream = null) {}

    public static function detect(): self
    {
        return self::$fake ?? new self;
    }

    /**
     * Force a deterministic capability profile for tests. A null argument leaves
     * that dimension to normal detection. Returns the fake so it can be asserted;
     * call {@see clearFake()} (or use the InteractsWithConsole trait) to restore.
     */
    public static function fake(?bool $colors = null, ?bool $unicode = null, ?int $width = null, ?bool $interactive = null): self
    {
        $fake = new self;
        $fake->colors = $colors;
        $fake->unicode = $unicode;
        $fake->width = $width;
        $fake->tty = $interactive;

        return self::$fake = $fake;
    }

    /**
     * Clear any forced capability profile set via {@see fake()}.
     */
    public static function clearFake(): void
    {
        self::$fake = null;
    }

    public function isInteractive(): bool
    {
        if ($this->tty !== null) {
            return $this->tty;
        }

        $stream = $this->stream ?? (defined('STDOUT') ? STDOUT : null);

        return $this->tty = is_resource($stream) && function_exists('stream_isatty') && stream_isatty($stream);
    }

    public function supportsColor(): bool
    {
        if ($this->colors !== null) {
            return $this->colors;
        }

        $configured = Config::get('output.colors', 'auto');

        if ($configured === 'always' || $configured === true) {
            return $this->colors = true;
        }

        if ($configured === 'never' || $configured === false) {
            return $this->colors = false;
        }

        // NO_COLOR disables colour when set to any non-empty value.
        if (($noColor = getenv('NO_COLOR')) !== false && $noColor !== '') {
            return $this->colors = false;
        }

        // FORCE_COLOR forces it on.
        if (($force = getenv('FORCE_COLOR')) !== false && $force !== '0' && $force !== '') {
            return $this->colors = true;
        }

        $term = (string) getenv('TERM');

        if ($term === 'dumb') {
            return $this->colors = false;
        }

        return $this->colors = $this->isInteractive();
    }

    public function supportsTrueColor(): bool
    {
        if (! $this->supportsColor()) {
            return false;
        }

        $colorTerm = strtolower((string) getenv('COLORTERM'));

        return $colorTerm === 'truecolor' || $colorTerm === '24bit';
    }

    /**
     * Whether the terminal supports the xterm 256-colour palette.
     */
    public function supports256Color(): bool
    {
        if (! $this->supportsColor()) {
            return false;
        }
        if ($this->supportsTrueColor()) {
            return true;
        }

        return str_contains((string) getenv('TERM'), '256color');
    }

    public function supportsUnicode(): bool
    {
        if ($this->unicode !== null) {
            return $this->unicode;
        }

        $configured = Config::get('output.unicode', 'auto');

        if ($configured === true || $configured === 'true') {
            return $this->unicode = true;
        }

        if ($configured === false || $configured === 'false') {
            return $this->unicode = false;
        }

        // Modern Windows terminals advertise themselves; otherwise look at locale.
        if (getenv('WT_SESSION') !== false || getenv('ConEmuANSI') === 'ON' || getenv('TERM_PROGRAM') === 'vscode') {
            return $this->unicode = true;
        }

        foreach (['LC_ALL', 'LC_CTYPE', 'LANG'] as $var) {
            if (stripos((string) getenv($var), 'UTF-8') !== false || stripos((string) getenv($var), 'UTF8') !== false) {
                return $this->unicode = true;
            }
        }

        return $this->unicode = false;
    }

    public function width(int $default = 80): int
    {
        if ($this->width !== null) {
            return $this->width;
        }

        $configured = Config::get('output.width');

        if (is_int($configured) || (is_string($configured) && ctype_digit($configured))) {
            return $this->width = (int) $configured;
        }

        $cols = (int) getenv('COLUMNS');

        if ($cols > 0) {
            return $this->width = $cols;
        }

        // Fall back to the real terminal before the static default.
        $terminal = (new Terminal)->getWidth();

        return $this->width = $terminal > 0 ? $terminal : $default;
    }

    /**
     * Resolve the glyph set to use: 'fancy' (Unicode) or 'ascii'. Honours the
     * `output.symbols` config (auto|fancy|ascii); `auto` follows Unicode support.
     */
    public function symbolMode(): string
    {
        $mode = Config::get('output.symbols', 'auto');

        return match ($mode) {
            'fancy' => 'fancy',
            'ascii' => 'ascii',
            default => $this->supportsUnicode() ? 'fancy' : 'ascii',
        };
    }
}

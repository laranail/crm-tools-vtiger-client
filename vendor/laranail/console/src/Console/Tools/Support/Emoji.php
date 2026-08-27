<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Fluent emoji helper that serves Unicode emoji or an ASCII fallback, decided by
 * mode (auto | unicode | ascii). "auto" follows {@see Capabilities::supportsUnicode()}.
 *
 *   Console::emoji()->get('rocket');                 // 🚀  (or "->" in ascii mode)
 *   Console::emoji()->ascii()->render('Done :tada:'); // "Done \o/"
 *   Console::emoji()->with(['deploy' => ['🚀', '>>']])->get('deploy');
 *
 * Configurable globally via config('laranail.console.emoji.mode') and
 * config('laranail.console.emoji.custom'); per call via auto()/unicode()/ascii()/with().
 */
final class Emoji
{
    /** @var array<string, array{0:string,1:string}> name => [unicode, ascii] */
    private const array MAP = [
        'rocket' => ['🚀', '->'],
        'check' => ['✅', '[OK]'],
        'white_check_mark' => ['✅', '[OK]'],
        'heavy_check_mark' => ['✔️', '[ok]'],
        'cross' => ['❌', '[X]'],
        'x' => ['❌', '[X]'],
        'warning' => ['⚠️', '[!]'],
        'info' => ['ℹ️', '[i]'],
        'question' => ['❓', '[?]'],
        'sparkles' => ['✨', '*'],
        'fire' => ['🔥', '!!'],
        'package' => ['📦', '[pkg]'],
        'gear' => ['⚙️', '*'],
        'bug' => ['🐛', 'bug'],
        'tada' => ['🎉', '\\o/'],
        'party' => ['🥳', '\\o/'],
        'bulb' => ['💡', '*'],
        'lock' => ['🔒', '[lock]'],
        'unlock' => ['🔓', '[open]'],
        'key' => ['🔑', 'key'],
        'hourglass' => ['⏳', '...'],
        'clock' => ['🕐', '[time]'],
        'star' => ['⭐', '*'],
        'zap' => ['⚡', '!'],
        'wrench' => ['🔧', '*'],
        'hammer' => ['🔨', '*'],
        'broom' => ['🧹', '*'],
        'mag' => ['🔍', '?'],
        'save' => ['💾', '[save]'],
        'trash' => ['🗑️', '[del]'],
        'inbox' => ['📥', '[in]'],
        'outbox' => ['📤', '[out]'],
        'download' => ['⬇️', 'v'],
        'upload' => ['⬆️', '^'],
        'link' => ['🔗', '[link]'],
        'bell' => ['🔔', '[bell]'],
        'eyes' => ['👀', 'oo'],
        'thumbsup' => ['👍', '+1'],
        'thumbsdown' => ['👎', '-1'],
        'stop' => ['🛑', '[stop]'],
        'construction' => ['🚧', '[wip]'],
        'green_circle' => ['🟢', '(o)'],
        'red_circle' => ['🔴', '(x)'],
        'yellow_circle' => ['🟡', '(!)'],
        'arrow_right' => ['➡️', '->'],
        'arrow_left' => ['⬅️', '<-'],
        'arrow_up' => ['⬆️', '^'],
        'arrow_down' => ['⬇️', 'v'],
        'heart' => ['❤️', '<3'],
        'computer' => ['💻', '[pc]'],
        'folder' => ['📁', '[dir]'],
        'page' => ['📄', '[doc]'],
        'calendar' => ['📅', '[date]'],
        'email' => ['📧', '[mail]'],
        'globe' => ['🌐', '[www]'],
        'cloud' => ['☁️', '[cloud]'],
        'coffee' => ['☕', '[coffee]'],
        'robot' => ['🤖', '[bot]'],
        'skull' => ['💀', 'x_x'],
        'wave' => ['👋', '\\o'],
        'pray' => ['🙏', 'ty'],
        'muscle' => ['💪', '!'],
        'brain' => ['🧠', '[brain]'],
        'ok_hand' => ['👌', 'ok'],
        'point_right' => ['👉', '->'],
        'point_left' => ['👈', '<-'],
        'snake' => ['🐍', '~'],
    ];

    /** @var array<string, array{0:string,1:string}> custom name => [unicode, ascii] */
    private array $custom = [];

    /** @var 'auto'|'unicode'|'ascii' */
    private string $mode;

    private readonly Capabilities $capabilities;

    public function __construct(?string $mode = null, ?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->mode = $this->normalizeMode($mode ?? (string) Config::get('emoji.mode', 'auto'));

        /** @var array<string, array{0:string,1:string}|string> $configCustom */
        $configCustom = (array) Config::get('emoji.custom', []);
        $this->custom = $this->normalizeMap($configCustom);
    }

    public static function make(?Capabilities $capabilities = null): self
    {
        return new self(null, $capabilities);
    }

    public function auto(): self
    {
        $this->mode = 'auto';

        return $this;
    }

    public function unicode(): self
    {
        $this->mode = 'unicode';

        return $this;
    }

    public function ascii(): self
    {
        $this->mode = 'ascii';

        return $this;
    }

    /**
     * Add or override emoji. Each value is `[unicode, ascii]` or a single string
     * used for both.
     *
     * @param array<string, array{0:string,1:string}|string> $map
     */
    public function with(array $map): self
    {
        $this->custom = array_merge($this->custom, $this->normalizeMap($map));

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->custom[$name]) || isset(self::MAP[$name]);
    }

    /**
     * Resolve a single emoji by name for the active mode. Returns $default (or
     * '') for an unknown name.
     */
    public function get(string $name, ?string $default = null): string
    {
        $entry = $this->custom[$name] ?? self::MAP[$name] ?? null;

        if ($entry === null) {
            return $default ?? '';
        }

        return $this->unicodeActive() ? $entry[0] : $entry[1];
    }

    /**
     * Interpolate `:name:` shortcodes in a string. Unknown shortcodes are left
     * untouched.
     */
    public function render(string $text): string
    {
        return (string) preg_replace_callback(
            '/:([a-z0-9_+-]+):/i',
            fn (array $m): string => $this->has($m[1]) ? $this->get($m[1]) : $m[0],
            $text,
        );
    }

    /**
     * Remove known `:name:` shortcodes from a string (for plain logs), collapsing
     * any doubled spaces left behind.
     */
    public function strip(string $text): string
    {
        $stripped = (string) preg_replace_callback(
            '/:([a-z0-9_+-]+):/i',
            fn (array $m): string => $this->has($m[1]) ? '' : $m[0],
            $text,
        );

        return trim((string) preg_replace('/ {2,}/', ' ', $stripped));
    }

    /**
     * All known emoji names (built-in + custom).
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_values(array_unique([...array_keys(self::MAP), ...array_keys($this->custom)]));
    }

    private function unicodeActive(): bool
    {
        return match ($this->mode) {
            'unicode' => true,
            'ascii' => false,
            default => $this->capabilities->supportsUnicode(),
        };
    }

    /**
     * @return 'auto'|'unicode'|'ascii'
     */
    private function normalizeMode(string $mode): string
    {
        return match (strtolower($mode)) {
            'unicode' => 'unicode',
            'ascii' => 'ascii',
            default => 'auto',
        };
    }

    /**
     * @param array<string, array{0:string,1:string}|string> $map
     * @return array<string, array{0:string,1:string}>
     */
    private function normalizeMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $name => $value) {
            if (is_string($value)) {
                $normalized[$name] = [$value, $value];

                continue;
            }

            $normalized[$name] = [(string) ($value[0] ?? ''), (string) ($value[1] ?? $value[0] ?? '')];
        }

        return $normalized;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Raw single-key / arrow / modifier reader.
 *
 * The escape-sequence → key-name mappings are the ECMA-48 / xterm conventions
 * (`\e[A` = up, etc.); the blocking reads require a POSIX terminal (`stty`) and
 * are guarded by {@see isSupported()}, degrading to '' / null on Windows or a
 * non-TTY rather than shelling out blindly. The `translateKey()` / `getKeyName()`
 * mappers are pure and always available.
 */
final class Keypress
{
    public const string KEY_UP = 'UP';

    public const string KEY_DOWN = 'DOWN';

    public const string KEY_RIGHT = 'RIGHT';

    public const string KEY_LEFT = 'LEFT';

    public const string KEY_ENTER = 'ENTER';

    public const string KEY_SPACE = 'SPACE';

    public const string KEY_BACKSPACE = 'BACKSPACE';

    public const string KEY_TAB = 'TAB';

    public const string KEY_ESC = 'ESC';

    public const string KEY_HOME = 'HOME';

    public const string KEY_END = 'END';

    public const string KEY_PAGE_UP = 'PAGE UP';

    public const string KEY_PAGE_DOWN = 'PAGE DOWN';

    public const string KEY_INSERT = 'INSERT';

    public const string KEY_DELETE = 'DELETE';

    public const string KEY_CTRL_C = 'CTRL+C';

    public static function make(): self
    {
        return new self;
    }

    /**
     * Whether raw keypress reading is available (POSIX TTY, not Windows).
     */
    public static function isSupported(): bool
    {
        return DIRECTORY_SEPARATOR !== '\\'
            && function_exists('stream_select')
            && function_exists('shell_exec')
            && defined('STDIN')
            && @stream_isatty(STDIN);
    }

    /**
     * Block until a key is pressed and return its name (or the raw bytes for
     * unmapped keys). Returns '' when reading is unsupported.
     */
    public function listen(): string
    {
        if (! self::isSupported()) {
            return '';
        }

        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            return '';
        }

        stream_set_blocking($stdin, false);
        shell_exec('stty cbreak -echo');

        try {
            $read = [$stdin];
            $write = null;
            $except = null;
            @stream_select($read, $write, $except, null);
            $char = stream_get_contents($stdin, 8) ?: '';
        } finally {
            shell_exec('stty sane');
            stream_set_blocking($stdin, true);
            fclose($stdin);
        }

        return self::translateKey($char);
    }

    /**
     * Read a key within $timeoutMs, or null on timeout / when unsupported.
     */
    public function listenNonBlocking(int $timeoutMs = 100): ?string
    {
        if (! self::isSupported()) {
            return null;
        }

        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            return null;
        }

        stream_set_blocking($stdin, false);
        shell_exec('stty cbreak -echo');

        try {
            $read = [$stdin];
            $write = null;
            $except = null;
            $count = @stream_select($read, $write, $except, intdiv($timeoutMs, 1000), ($timeoutMs % 1000) * 1000);

            if ($count === false || $count === 0) {
                return null;
            }

            return self::translateKey(stream_get_contents($stdin, 8) ?: '');
        } finally {
            shell_exec('stty sane');
            stream_set_blocking($stdin, true);
            fclose($stdin);
        }
    }

    /**
     * Map raw input bytes to a key name (or return the bytes unchanged).
     */
    public static function translateKey(string $key): string
    {
        return match ($key) {
            "\033[A" => self::KEY_UP,
            "\033[B" => self::KEY_DOWN,
            "\033[C" => self::KEY_RIGHT,
            "\033[D" => self::KEY_LEFT,
            "\n", "\r" => self::KEY_ENTER,
            ' ' => self::KEY_SPACE,
            "\010", "\177" => self::KEY_BACKSPACE,
            "\t" => self::KEY_TAB,
            "\e", "\033" => self::KEY_ESC,
            "\x03" => self::KEY_CTRL_C,
            "\033[H", "\033[1~" => self::KEY_HOME,
            "\033[F", "\033[4~" => self::KEY_END,
            "\033[5~" => self::KEY_PAGE_UP,
            "\033[6~" => self::KEY_PAGE_DOWN,
            "\033[2~" => self::KEY_INSERT,
            "\033[3~" => self::KEY_DELETE,
            default => $key,
        };
    }

    /**
     * A human-readable label for a translated key.
     */
    public static function getKeyName(string $key): string
    {
        return match ($key) {
            self::KEY_UP => 'UP ARROW',
            self::KEY_DOWN => 'DOWN ARROW',
            self::KEY_LEFT => 'LEFT ARROW',
            self::KEY_RIGHT => 'RIGHT ARROW',
            self::KEY_ENTER => 'ENTER',
            self::KEY_SPACE => 'SPACE',
            self::KEY_BACKSPACE => 'BACKSPACE',
            self::KEY_TAB => 'TAB',
            self::KEY_ESC => 'ESCAPE',
            self::KEY_HOME => 'HOME',
            self::KEY_END => 'END',
            self::KEY_PAGE_UP => 'PAGE UP',
            self::KEY_PAGE_DOWN => 'PAGE DOWN',
            self::KEY_INSERT => 'INSERT',
            self::KEY_DELETE => 'DELETE',
            self::KEY_CTRL_C => 'CTRL+C',
            default => strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126
                ? "'" . $key . "'"
                : 'UNKNOWN SEQUENCE',
        };
    }

    /**
     * Detect an Alt+<char> combination (ESC-prefixed), or null.
     */
    public static function detectAltKey(string $key): ?string
    {
        if (strlen($key) >= 2 && $key[0] === "\033") {
            $char = substr($key, 1);

            if (strlen($char) === 1 && ord($char) >= 32 && ord($char) <= 126) {
                return 'ALT+' . strtoupper($char);
            }
        }

        return null;
    }
}

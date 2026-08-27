<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Formatting;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Hyperlink;
use Stringable;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Fluent helper class for formatting Symfony Console output with badge support
 *
 * @example
 * // Regular formatting
 * $output = ConsoleUIFormatter::create()
 *     ->addMessage('Processing...')
 *     ->addTextColor(ConsoleUIFormatter::GREEN)
 *     ->render();
 *
 * // Badge formatting
 * $badge = ConsoleUIFormatter::create()
 *     ->addMessage('NEW')
 *     ->isBadge(ConsoleUIFormatter::BADGE_STYLE_SUCCESS)
 *     ->render();
 */
class ConsoleUIFormatter implements Stringable
{
    // Foreground Colors
    public const string BLACK = 'black';

    public const string RED = 'red';

    public const string GREEN = 'green';

    public const string YELLOW = 'yellow';

    public const string BLUE = 'blue';

    public const string MAGENTA = 'magenta';

    public const string CYAN = 'cyan';

    public const string WHITE = 'white';

    public const string GRAY = 'gray';

    public const string BRIGHT_RED = 'bright-red';

    public const string BRIGHT_GREEN = 'bright-green';

    public const string BRIGHT_YELLOW = 'bright-yellow';

    public const string BRIGHT_BLUE = 'bright-blue';

    public const string BRIGHT_MAGENTA = 'bright-magenta';

    public const string BRIGHT_CYAN = 'bright-cyan';

    public const string BRIGHT_WHITE = 'bright-white';

    // Background Colors (prefixed for clarity)
    public const string BG_BLACK = 'black';

    public const string BG_RED = 'red';

    public const string BG_GREEN = 'green';

    public const string BG_YELLOW = 'yellow';

    public const string BG_BLUE = 'blue';

    public const string BG_MAGENTA = 'magenta';

    public const string BG_CYAN = 'cyan';

    public const string BG_WHITE = 'white';

    public const string BG_GRAY = 'gray';

    public const string BG_BRIGHT_RED = 'bright-red';

    public const string BG_BRIGHT_GREEN = 'bright-green';

    public const string BG_BRIGHT_YELLOW = 'bright-yellow';

    public const string BG_BRIGHT_BLUE = 'bright-blue';

    public const string BG_BRIGHT_MAGENTA = 'bright-magenta';

    public const string BG_BRIGHT_CYAN = 'bright-cyan';

    public const string BG_BRIGHT_WHITE = 'bright-white';

    // Text Style Options
    public const string BOLD = 'bold';

    public const string UNDERSCORE = 'underscore';

    public const string UNDERLINE = 'underscore'; // Alias for underscore

    public const string BLINK = 'blink';

    public const string REVERSE = 'reverse';

    public const string CONCEAL = 'conceal';

    public const string HIDDEN = 'conceal'; // Alias for conceal

    // Predefined Style Tags
    public const string STYLE_TAG_INFO = 'info';

    public const string STYLE_TAG_COMMENT = 'comment';

    public const string STYLE_TAG_QUESTION = 'question';

    public const string STYLE_TAG_ERROR = 'error';

    // Badge Styles (Bootstrap-inspired)
    public const string BADGE_STYLE_PRIMARY = 'primary';

    public const string BADGE_STYLE_SECONDARY = 'secondary';

    public const string BADGE_STYLE_SUCCESS = 'success';

    public const string BADGE_STYLE_DANGER = 'danger';

    public const string BADGE_STYLE_WARNING = 'warning';

    public const string BADGE_STYLE_INFO = 'info';

    public const string BADGE_STYLE_LIGHT = 'light';

    public const string BADGE_STYLE_DARK = 'dark';

    // ANSI Color Codes (for terminal compatibility)
    public const array ANSI_COLORS = [
        // Foreground colors
        'black' => "\033[30m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'gray' => "\033[90m",
        'bright_red' => "\033[91m",
        'bright_green' => "\033[92m",
        'bright_yellow' => "\033[93m",
        'bright_blue' => "\033[94m",
        'bright_magenta' => "\033[95m",
        'bright_cyan' => "\033[96m",
        'bright_white' => "\033[97m",

        // Background colors
        'black_bg' => "\033[40m",
        'red_bg' => "\033[41m",
        'green_bg' => "\033[42m",
        'yellow_bg' => "\033[43m",
        'blue_bg' => "\033[44m",
        'magenta_bg' => "\033[45m",
        'cyan_bg' => "\033[46m",
        'white_bg' => "\033[47m",
        'gray_bg' => "\033[100m",
        'bright_red_bg' => "\033[101m",
        'bright_green_bg' => "\033[102m",
        'bright_yellow_bg' => "\033[103m",
        'bright_blue_bg' => "\033[104m",
        'bright_magenta_bg' => "\033[105m",
        'bright_cyan_bg' => "\033[106m",
        'bright_white_bg' => "\033[107m",

        // Text styles
        'bold' => "\033[1m",
        'dim' => "\033[2m",
        'italic' => "\033[3m",
        'underline' => "\033[4m",
        'reset' => "\033[0m",
    ];

    // Badge color schemes
    private const array BADGE_SCHEMES = [
        self::BADGE_STYLE_PRIMARY => [
            'fg' => self::WHITE,
            'bg' => '#0d6efd', // Bootstrap primary blue
            'styles' => [self::BOLD],
        ],
        self::BADGE_STYLE_SECONDARY => [
            'fg' => self::WHITE,
            'bg' => '#6c757d', // Bootstrap secondary gray
            'styles' => [self::BOLD],
        ],
        self::BADGE_STYLE_SUCCESS => [
            'fg' => self::WHITE,
            'bg' => '#198754', // Bootstrap success green
            'styles' => [self::BOLD],
        ],
        self::BADGE_STYLE_DANGER => [
            'fg' => self::WHITE,
            'bg' => '#dc3545', // Bootstrap danger red
            'styles' => [self::BOLD],
        ],
        self::BADGE_STYLE_WARNING => [
            'fg' => self::BLACK,
            'bg' => '#ffc107', // Bootstrap warning yellow
            'styles' => [self::BOLD],
        ],
        self::BADGE_STYLE_INFO => [
            'fg' => self::BLACK,
            'bg' => '#0dcaf0', // Bootstrap info cyan
            'styles' => [self::BOLD],
        ],
        self::BADGE_STYLE_LIGHT => [
            'fg' => self::BLACK,
            'bg' => '#f8f9fa', // Bootstrap light gray
            'styles' => [self::BOLD],
        ],
        self::BADGE_STYLE_DARK => [
            'fg' => self::WHITE,
            'bg' => '#212529', // Bootstrap dark
            'styles' => [self::BOLD],
        ],
    ];

    // Properties
    private string $message = '';

    private ?string $foregroundColor = null;

    private ?string $backgroundColor = null;

    private array $textStyles = [];

    private ?string $styleTag = null;

    private ?string $href = null;

    private bool $isClickable = false;

    private bool $badgeMode = false;

    private string $badgePadding = ' ';

    // Terminal capability detection
    private bool $supportsColor = true;

    /**
     * Private constructor to enforce factory method usage
     */
    private function __construct()
    {
        $this->supportsColor = $this->detectColorSupport();
    }

    /**
     * Create a new instance with fresh state
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Set the message content
     */
    public function addMessage(string $message): self
    {
        $this->message = self::sanitizeText($message);

        return $this;
    }

    /**
     * Strip terminal control characters (C0 controls, ESC, DEL) from a string,
     * preserving tab and newline. Prevents ANSI/CR output-spoofing injection
     * when rendering user-controlled text.
     */
    public static function sanitizeText(string $text): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text);
    }

    /**
     * Constrain a colour token to safe characters so it cannot break out of or
     * inject extra attributes into a `<fg=...>` / `<bg=...>` formatter tag.
     */
    private function sanitizeColorToken(string $color): string
    {
        return (string) preg_replace('/[^A-Za-z0-9#]/', '', $color);
    }

    /**
     * Add text/foreground color with optional predefined style tag or clickable link
     *
     * @param string $text Color name or hex code (e.g., '#ff0000')
     * @param string|null $styleTag Optional predefined style tag (info, comment, question, error)
     * @param bool $isClickable Whether the text should be clickable (requires href)
     * @param string|null $href URL for clickable text
     */
    public function addTextColor(
        string $text,
        ?string $styleTag = null,
        bool $isClickable = false,
        ?string $href = null
    ): self {
        $this->foregroundColor = $text;
        $this->styleTag = $styleTag;
        $this->isClickable = false;
        $this->href = null;

        // Route the href through the same sanitisation + scheme allow-list as
        // setHref(), so a hostile URL can't emit an arbitrary OSC-8 hyperlink.
        if ($isClickable && $href !== null && $href !== '') {
            $this->setHref($href);
        }

        return $this;
    }

    /**
     * Add background color
     *
     * @param string $text Color name or hex code (e.g., '#2c3e50')
     */
    public function addBackgroundColor(string $text): self
    {
        $this->backgroundColor = $text;

        return $this;
    }

    /**
     * Add text style options
     *
     * @param string|array $styles Single style or array of styles
     */
    public function addTextStyles(string|array $styles): self
    {
        if (is_string($styles)) {
            $styles = [$styles];
        }

        $this->textStyles = array_unique(array_merge($this->textStyles, $styles));

        return $this;
    }

    /**
     * Enable badge mode with specified style
     *
     * @param string|null $style Badge style constant (BADGE_STYLE_*)
     * @param string $padding Padding character(s) around badge text
     */
    public function isBadge(?string $style = self::BADGE_STYLE_PRIMARY, string $padding = ' '): self
    {
        $this->badgeMode = true;
        $this->badgePadding = $padding;

        // Apply badge color scheme if style is specified
        if ($style && isset(self::BADGE_SCHEMES[$style])) {
            $scheme = self::BADGE_SCHEMES[$style];
            $this->foregroundColor = $scheme['fg'];
            $this->backgroundColor = $scheme['bg'];
            $this->textStyles = array_unique(array_merge($this->textStyles, $scheme['styles']));
        }

        return $this;
    }

    /**
     * Set clickable link
     *
     * @param string $url URL to make the text clickable
     */
    public function setHref(string $url): self
    {
        // Delegate the allow-list + sanitisation to Support\Hyperlink (the single
        // source of truth). A rejected URL degrades to plain, non-clickable text
        // rather than emitting an attacker-controlled hyperlink.
        $safe = Hyperlink::safe($url);

        $this->href = $safe;
        $this->isClickable = $safe !== null;

        return $this;
    }

    /**
     * Clear all text styles
     */
    public function clearStyles(): self
    {
        $this->textStyles = [];

        return $this;
    }

    /**
     * Clear all formatting
     */
    public function reset(): self
    {
        $this->message = '';
        $this->foregroundColor = null;
        $this->backgroundColor = null;
        $this->textStyles = [];
        $this->styleTag = null;
        $this->href = null;
        $this->isClickable = false;
        $this->badgeMode = false;
        $this->badgePadding = ' ';

        return $this;
    }

    /**
     * Colorize text with ANSI codes (for terminal compatibility)
     */
    public function colorize(string $text, string $color, bool $bold = false, ?string $background = null): string
    {
        if (! $this->supportsColor) {
            return $text;
        }

        $colorCode = self::ANSI_COLORS[$color] ?? '';
        $boldCode = $bold ? self::ANSI_COLORS['bold'] : '';
        // Background colour tokens (e.g. BG_RED = 'red') map to the '*_bg' ANSI key.
        $backgroundCode = $background !== null && $background !== ''
            ? (self::ANSI_COLORS[$background . '_bg'] ?? self::ANSI_COLORS[$background] ?? '')
            : '';
        $resetCode = self::ANSI_COLORS['reset'];
        $text = self::sanitizeText($text);

        return "{$boldCode}{$backgroundCode}{$colorCode}{$text}{$resetCode}";
    }

    /**
     * Detect colour support via the shared Capabilities detector, so the whole
     * package degrades by one consistent set of rules (NO_COLOR/FORCE_COLOR/
     * TERM/TTY).
     */
    private function detectColorSupport(): bool
    {
        return Capabilities::detect()->supportsColor();
    }

    /**
     * Render the formatted string
     */
    public function render(): string
    {
        if ($this->message === '') {
            return '';
        }

        // Format message as badge if enabled
        $displayMessage = $this->badgeMode
            ? $this->badgePadding . mb_strtoupper($this->message) . $this->badgePadding
            : $this->message;

        // Use predefined style tag if set and not in badge mode
        if ($this->styleTag && ! $this->badgeMode) {
            return sprintf('<%s>%s</>', $this->styleTag, $displayMessage);
        }

        // Use clickable href if set. Escape the URL so it can't inject formatter
        // tags (e.g. a URL containing `<fg=red>`); Hyperlink vets the scheme, but
        // `<`/`>` must still be neutralised for the Symfony tag syntax.
        if ($this->isClickable && $this->href) {
            return sprintf('<href=%s>%s</>', OutputFormatter::escape($this->href), $displayMessage);
        }

        // Build custom formatting
        $tags = [];

        if ($this->foregroundColor) {
            $tags[] = 'fg=' . $this->sanitizeColorToken($this->foregroundColor);
        }

        if ($this->backgroundColor) {
            $tags[] = 'bg=' . $this->sanitizeColorToken($this->backgroundColor);
        }

        if ($this->textStyles !== []) {
            $tags[] = 'options=' . implode(',', $this->textStyles);
        }

        // No formatting needed
        if ($tags === []) {
            return $displayMessage;
        }

        // Build formatted string
        return sprintf('<%s>%s</>', implode(';', $tags), $displayMessage);
    }

    /**
     * Convert to string (alias for render)
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Create a badge with specified style
     *
     * @param string $text Badge text
     * @param string|null $style Badge style (BADGE_STYLE_*)
     */
    public static function badge(string $text, ?string $style = self::BADGE_STYLE_PRIMARY): string
    {
        return self::create()
            ->addMessage($text)
            ->isBadge($style)
            ->render();
    }

    /**
     * Create multiple badges in a row
     *
     * @param array $badges Array of ['text' => string, 'style' => string] or just strings
     * @param string $separator Separator between badges
     */
    public static function badges(array $badges, string $separator = ' '): string
    {
        $rendered = [];

        foreach ($badges as $badge) {
            if (is_string($badge)) {
                $rendered[] = self::badge($badge);
            } elseif (is_array($badge)) {
                $text = $badge['text'] ?? $badge[0] ?? '';
                $style = $badge['style'] ?? $badge[1] ?? self::BADGE_STYLE_PRIMARY;
                $rendered[] = self::badge($text, $style);
            }
        }

        return implode($separator, $rendered);
    }

    /**
     * Static helper for quick formatting
     *
     * @param string $message The message to format
     * @param string|null $foreground Foreground color
     * @param string|null $background Background color
     * @param array $styles Text styles
     */
    public static function format(
        string $message,
        ?string $foreground = null,
        ?string $background = null,
        array $styles = []
    ): string {
        $formatter = self::create()->addMessage($message);

        if ($foreground) {
            $formatter->addTextColor($foreground);
        }

        if ($background) {
            $formatter->addBackgroundColor($background);
        }

        if ($styles !== []) {
            $formatter->addTextStyles($styles);
        }

        return $formatter->render();
    }

    /**
     * Static helper for success messages
     */
    public static function success(string $message): string
    {
        return self::create()
            ->addMessage($message)
            ->addTextColor(self::GREEN)
            ->addTextStyles(self::BOLD)
            ->render();
    }

    /**
     * Static helper for error messages
     */
    public static function error(string $message): string
    {
        return self::create()
            ->addMessage($message)
            ->addTextColor(self::WHITE)
            ->addBackgroundColor(self::BG_RED)
            ->addTextStyles(self::BOLD)
            ->render();
    }

    /**
     * Static helper for warning messages
     */
    public static function warning(string $message): string
    {
        return self::create()
            ->addMessage($message)
            ->addTextColor(self::YELLOW)
            ->addTextStyles(self::BOLD)
            ->render();
    }

    /**
     * Static helper for info messages
     */
    public static function info(string $message): string
    {
        return self::create()
            ->addMessage($message)
            ->addTextColor(text: self::CYAN)
            ->render();
    }

    /**
     * Create a clickable link format
     *
     * @param string $text Display text
     * @param string $url URL to link to
     */
    public static function link(string $text, string $url): string
    {
        return self::create()
            ->addMessage($text)
            ->setHref($url)
            ->render();
    }

    /**
     * Format with hex colors
     *
     * @param string $message The message
     * @param string|null $hexFg Hex foreground color (e.g., '#e74c3c')
     * @param string|null $hexBg Hex background color (e.g., '#2c3e50')
     */
    public static function hex(string $message, ?string $hexFg = null, ?string $hexBg = null): string
    {
        $formatter = self::create()->addMessage($message);

        if ($hexFg) {
            $formatter->addTextColor($hexFg);
        }

        if ($hexBg) {
            $formatter->addBackgroundColor($hexBg);
        }

        return $formatter->render();
    }

    /**
     * Get all available badge styles
     */
    public static function getBadgeStyles(): array
    {
        return [
            self::BADGE_STYLE_PRIMARY,
            self::BADGE_STYLE_SECONDARY,
            self::BADGE_STYLE_SUCCESS,
            self::BADGE_STYLE_DANGER,
            self::BADGE_STYLE_WARNING,
            self::BADGE_STYLE_INFO,
            self::BADGE_STYLE_LIGHT,
            self::BADGE_STYLE_DARK,
        ];
    }
}

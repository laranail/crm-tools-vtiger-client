<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * OSC-8 terminal hyperlinks + the URL scheme allow-list — the single place that
 * decides whether a URL is safe to emit, shared by the formatter and the `Link`
 * component (no duplication). A disallowed or unsupported URL degrades to plain
 * `label (url)` text rather than emitting an attacker-controlled link.
 */
final class Hyperlink
{
    /**
     * @return list<string>
     */
    public static function schemes(): array
    {
        /** @var list<string> $schemes */
        $schemes = (array) Config::get('links.allowed_schemes', ['http', 'https', 'mailto']);

        return $schemes;
    }

    /**
     * Strip control characters and the OSC-8 separator from a URL.
     */
    public static function sanitize(string $url): string
    {
        return str_replace(';', '', (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $url));
    }

    /**
     * Whether a URL uses an allow-listed scheme (after sanitisation).
     */
    public static function isAllowed(string $url): bool
    {
        $scheme = strtolower((string) parse_url(self::sanitize($url), PHP_URL_SCHEME));

        return $scheme !== '' && in_array($scheme, self::schemes(), true);
    }

    /**
     * The sanitised URL if it is safe to emit, else null.
     */
    public static function safe(string $url): ?string
    {
        $clean = self::sanitize($url);

        return self::isAllowed($clean) ? $clean : null;
    }

    /**
     * Render a hyperlink: an OSC-8 sequence in an interactive terminal with a safe
     * URL, otherwise the plain `label (url)` fallback (or just the label when the
     * URL is unsafe/empty).
     */
    public static function render(string $label, string $url, ?Capabilities $capabilities = null): string
    {
        $safe = self::safe($url);
        $capabilities ??= Capabilities::detect();

        if ($safe === null) {
            return $label;
        }

        if ($capabilities->isInteractive()) {
            return "\e]8;;{$safe}\e\\{$label}\e]8;;\e\\";
        }

        return $label === $safe ? $label : "{$label} ({$safe})";
    }
}

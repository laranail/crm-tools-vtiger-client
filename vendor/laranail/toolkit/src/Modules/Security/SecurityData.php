<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Security;

use RuntimeException;

/**
 * Lazy accessor for the package's bundled security datasets (passwords,
 * passphrase wordlist, redaction keys), statically cached per process.
 *
 * The datasets ship in {@see config/security.php} and are merged under the
 * `laranail.toolkit.security` config namespace by the toolkit provider, so:
 *
 *  - When a Laravel app is booted, the data is read from
 *    `config('laranail.toolkit.security.*')` — including any published override
 *    applied by package-tools' config bridge.
 *  - When NO app is booted (the Security generators are pure value objects with
 *    standalone unit tests), it falls back to the package default file via a
 *    `__DIR__`-relative path, which resolves with no framework present.
 *
 * @see config/security.php
 */
final class SecurityData
{
    /** EFF Large Wordlist size (6^5) — asserted on load. */
    public const WORDLIST_SIZE = 7776;

    /**
     * Process-wide cache of the loaded security config array. One `require`
     * per process; `null` until first access. Typed loosely because the array
     * is loaded from an arbitrary (publishable) file at runtime and validated
     * defensively by the accessors.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $config = null;

    /**
     * Return the lowercased, deduplicated common-password denylist.
     *
     * @return list<string>
     */
    public static function commonPasswords(): array
    {
        return self::stringList(self::section('passwords')['common'] ?? null);
    }

    /**
     * Return the EFF Large Wordlist (exactly 7776 CC0 words).
     *
     * @throws RuntimeException when the list is not exactly 7776 words
     *
     * @return list<string>
     */
    public static function passphraseWords(): array
    {
        $list = self::stringList(self::section('passphrases')['wordlist'] ?? null);

        if (count($list) !== self::WORDLIST_SIZE) {
            throw new RuntimeException(sprintf(
                'EFF wordlist must contain exactly %d words, found %d.',
                self::WORDLIST_SIZE,
                count($list),
            ));
        }

        return $list;
    }

    /**
     * Return the default request-data redaction keys.
     *
     * @return list<string>
     */
    public static function redactKeys(): array
    {
        return self::stringList(self::config()['redact_keys'] ?? null);
    }

    /**
     * Return a top-level config section as an array (empty array when absent or
     * malformed).
     *
     * @return array<string, mixed>
     */
    private static function section(string $key): array
    {
        $value = self::config()[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Coerce an arbitrary value into a `list<string>`, dropping non-strings.
     *
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Load (once per process) and return the security config array. Reads the
     * merged `laranail.toolkit.security` config when an application is booted;
     * otherwise falls back to the package default file (framework-free).
     *
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // Prefer the booted application's merged config (package default +
        // published override). Guarded so the Security value objects keep working
        // with no booted Laravel application.
        if (function_exists('app') && app()->bound('config')) {
            $data = app('config')->get('laranail.toolkit.security');

            if (is_array($data) && $data !== []) {
                /** @var array<string, mixed> $data */
                return self::$config = $data;
            }
        }

        // Framework-free fallback — the package default file:
        //   src/Modules/Security/ --(dirname __DIR__, 3)--> repo root --> config/security.php
        $path = dirname(__DIR__, 3) . '/config/security.php';

        if (!is_file($path)) {
            throw new RuntimeException("Security config not found at [{$path}].");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new RuntimeException("Security config at [{$path}] must return an array.");
        }

        /** @var array<string, mixed> $config */
        return self::$config = $config;
    }
}

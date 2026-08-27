<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Faker\Generator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Support\Cast;
use Simtabi\Laranail\Toolkit\Support\Username;

/**
 * String / identity / miscellaneous value helpers.
 *
 * Folded into {@see Helper} — call via the
 * `Helper::` facade, never the trait directly.
 */
trait InteractsWithStrings
{
    public static function strBetween(string $string, string $start, string $end): ?string
    {
        $start = preg_quote($start, '/');
        $end = preg_quote($end, '/');

        $pattern = "/$start(.*?)$end/";
        preg_match($pattern, $string, $matches);

        return $matches[1] ?? null;
    }

    public static function strSlugify(string $string, string $separator = '-'): string
    {
        // Str::slug transliterates unicode (e.g. "Café" => "cafe").
        return Str::slug($string, $separator);
    }

    /**
     * Title-case a string with multibyte awareness (falls back to Str::ucwords
     * when the mbstring extension is unavailable).
     */
    public static function ucWords(string $string, string $encoding = 'UTF-8'): string
    {
        if (!function_exists('mb_convert_case')) {
            return Str::ucwords($string);
        }

        return mb_convert_case($string, MB_CASE_TITLE, $encoding);
    }

    /**
     * Derive a username from an email address: the local part stripped to
     * `[A-Za-z0-9._]`, prefixed with `user_` when it doesn't start with a
     * letter, and falling back to `user` when nothing usable remains.
     *
     * Delegates to {@see Username}; the config below reproduces the legacy
     * shape (preserve case, no length padding, `_` lead-in, `user` fallback).
     */
    public static function usernameFromEmail(string $email): string
    {
        $local = (string) preg_replace('/[^a-zA-Z0-9._]/', '', Str::before($email, '@'));

        if ($local === '') {
            return 'user';
        }

        if (!ctype_alpha($local[0])) {
            $local = 'user_' . $local;
        }

        return Username::for($local)
            ->preserveCase()
            ->allow('._')
            ->minLength(1)
            ->generate();
    }

    /**
     * Build an email address from a username. A value already containing `@` is
     * returned unchanged; otherwise the username is sanitised and the domain
     * appended.
     */
    public static function emailFromUsername(string $username, string $domain = 'example.com'): string
    {
        if (Str::contains($username, '@')) {
            return $username;
        }

        $username = (string) preg_replace('/[^a-zA-Z0-9._-]/', '', $username);

        return $username . '@' . $domain;
    }

    /**
     * Generate a deterministic list of username suggestions from a person's
     * name using only native Str helpers (no third-party name library).
     *
     * Combines first/last parts and initials, slug-normalised, plus a couple of
     * numeric-suffixed variants so callers can pick the first available one.
     *
     * @return list<string>
     */
    public static function nameToUsernames(string $firstName, ?string $lastName = null): array
    {
        return Username::fromName($firstName, $lastName)->candidates(10);
    }

    /**
     * Generate a random, handle-style username.
     *
     * Unlike {@see usernameFromEmail()} / {@see nameToUsernames()} (which derive
     * a username from existing identity data), this produces a fresh anonymous
     * handle — a lowercase alphabetic prefix followed by a numeric suffix
     * (e.g. "user4821"), suitable for placeholder/guest accounts. The result is
     * always a valid identifier: it starts with a letter and contains only
     * `[a-z0-9]`.
     *
     * @param string $prefix Leading word; sanitised to `[a-z]`, falling back to
     *                       `user` when nothing usable remains.
     * @param int    $digits Number of trailing random digits (clamped to 1..10).
     */
    public static function generateUsername(string $prefix = 'user', int $digits = 4): string
    {
        return Username::random($prefix, $digits)->minLength(1)->generate();
    }

    /**
     * Escape a dirty string (or array of strings, joined) into an
     * XSS-safe {@see HtmlString} via Laravel's `e()` helper. Null yields an
     * empty HtmlString. (Recovered as the legacy `html()` sanitiser.)
     *
     * @param string|array<int|string, mixed>|null $dirty
     */
    public static function escapeHtml(string|array|null $dirty): HtmlString
    {
        if ($dirty === null) {
            return new HtmlString('');
        }

        $value = is_array($dirty)
            ? implode('', array_map(static fn (mixed $item): string => Cast::toString($item), $dirty))
            : $dirty;

        return new HtmlString(e($value));
    }

    /**
     * The "basename" of a class — its short name without the namespace.
     */
    public static function classBasename(object|string $class): string
    {
        return class_basename($class);
    }

    /**
     * A random integer in `[$from, $to]` excluding any value in `$except`.
     *
     * Bounded (max attempts) so an impossible exclusion set throws rather than
     * recursing forever — the legacy version recursed unbounded.
     *
     * @param list<int> $except
     *
     * @throws RuntimeException when no allowed value is found within the bound
     */
    public static function randomIntExcept(int $from, int $to, array $except = []): int
    {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $maxAttempts = 1000;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $number = random_int($from, $to);

            if (!in_array($number, $except, true)) {
                return $number;
            }
        }

        throw new RuntimeException(
            sprintf('Could not find an allowed integer in [%d, %d] after %d attempts.', $from, $to, $maxAttempts),
        );
    }

    /**
     * A Faker generator for the given locale, via Laravel's `fake()` helper.
     */
    public static function faker(?string $locale = null): Generator
    {
        return fake($locale);
    }
}

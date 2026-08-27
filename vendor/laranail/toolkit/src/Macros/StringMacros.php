<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Registers the toolkit's general-purpose Str and Stringable macros.
 */
final class StringMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerStrMacros();
        $this->registerStringableMacros();
    }

    private function registerStrMacros(): void
    {
        // Strip the application's own base URL, leaving a relative path.
        //
        // Seeded and imported content routinely carries absolute URLs from
        // wherever it was produced, which then point at the wrong host in every
        // other environment.
        //
        // Both `app.url` and `url('')` are stripped, because they disagree more
        // often than you would like: `url('')` is the CURRENT REQUEST's root, so
        // behind a proxy, on a secondary domain, or in a queue worker it is not
        // the canonical app URL that the content was stored with. Using either
        // alone silently leaves the other's URLs absolute.
        //
        // Pass $base explicitly for content that came from a different host
        // entirely — a staging import, say — which neither default can know.
        Str::macro('withoutBaseUrl', function (string $string, ?string $base = null): string {
            $bases = $base !== null ? [$base] : [config('app.url'), url('')];

            foreach ($bases as $candidate) {
                $candidate = is_string($candidate) ? rtrim($candidate, '/') : '';

                if ($candidate !== '') {
                    $string = Str::replace($candidate, '', $string);
                }
            }

            return $string;
        });

        Str::macro('kebabToTitle', fn (string $string): string => Str::title(Str::replace('-', ' ', $string)));

        Str::macro('snakeToTitle', fn (string $string): string => Str::title(Str::replace('_', ' ', $string)));

        Str::macro('camelToTitle', fn (string $string): string => Str::title(Str::snake($string, ' ')));

        Str::macro('truncateMiddle', function (string $string, int $length = 50, string $middle = '...'): string {
            if (Str::length($string) <= $length) {
                return $string;
            }

            $middleLength = Str::length($middle);

            // No room for the marker — hard-truncate to the budget.
            if ($length <= $middleLength) {
                return Str::substr($string, 0, max(0, $length));
            }

            $halfLength = intdiv($length - $middleLength, 2);

            // Room for the marker but not for two balanced ends: keep the head
            // that fits. (`-0` would otherwise make Str::substr return the whole
            // string, producing output longer than the input.)
            if ($halfLength < 1) {
                return Str::substr($string, 0, $length - $middleLength) . $middle;
            }

            return Str::substr($string, 0, $halfLength) . $middle . Str::substr($string, -$halfLength);
        });

        Str::macro('isEmail', fn (string $string): bool => filter_var($string, FILTER_VALIDATE_EMAIL) !== false);

        Str::macro('stripWhitespace', fn (string $string): string => (string) preg_replace('/\s+/', '', $string));

        Str::macro('normalizeWhitespace', fn (string $string): string => (string) preg_replace('/\s+/', ' ', trim($string)));

        Str::macro('toBool', function (string $string): bool {
            $string = Str::lower(trim($string));

            return in_array($string, ['1', 'true', 'yes', 'on'], true);
        });

        Str::macro('wrapWith', fn (string $string, string $wrapper = '"'): string => $wrapper . $string . $wrapper);

        Str::macro('replaceMany', function (string $string, array $replacements): string {
            foreach ($replacements as $search => $replace) {
                $string = Str::replace((string) $search, (string) $replace, $string);
            }

            return $string;
        });

        // Whether the string matches a full PCRE pattern. The legacy macro
        // delegated to a non-existent Str::matches() and double-wrapped the
        // result; this is a direct, bool-returning preg_match() wrapper.
        Str::macro('matches', fn (string $string, string $pattern): bool => preg_match($pattern, $string) === 1);

        Str::macro('reverseString', fn (string $string): string => strrev($string));

        Str::macro('countWords', fn (string $string): int => str_word_count($string));

        Str::macro('removeAccents', function (string $string): string {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);

            return $converted === false ? $string : $converted;
        });

        // Estimated reading time in (rounded-up) minutes for a body of text.
        Str::macro('readingMinutes', function (string $string, int $wordsPerMinute = 200): int {
            $wordsPerMinute = max(1, $wordsPerMinute);
            $words = str_word_count(strip_tags($string));

            return max(1, (int) ceil($words / $wordsPerMinute));
        });

        // Wrap each occurrence of the given term(s) in <mark>…</mark>. The input
        // text is e()-escaped first and only the matched terms are wrapped, so
        // the result is always safe HTML — returned as an HtmlString.
        Str::macro('highlightWords', function (string $string, string|array $words): HtmlString {
            $terms = array_filter(
                array_map(static fn (mixed $word): string => trim(Cast::toString($word)), (array) $words),
                static fn (string $word): bool => $word !== '',
            );

            $escaped = e($string);

            if ($terms === []) {
                return new HtmlString($escaped);
            }

            // Match against the already-escaped haystack using escaped needles so
            // terms containing HTML-special chars (", &, <, >) still highlight.
            $pattern = '/(' . implode('|', array_map(
                static fn (string $term): string => preg_quote(e($term), '/'),
                $terms,
            )) . ')/iu';

            $highlighted = preg_replace($pattern, '<mark>$1</mark>', $escaped);

            return new HtmlString($highlighted ?? $escaped);
        });

        // Strip HTML/PHP tags, optionally keeping an allow-list (passed straight
        // to strip_tags()). Str has no native equivalent.
        Str::macro('stripTags', fn (string $string, string|array|null $allowedTags = null): string => $allowedTags === null
            ? strip_tags($string)
            : strip_tags($string, $allowedTags));

        // Number of lines in a string (1 for a non-empty string with no newline,
        // 0 for the empty string). Counts \n, \r\n and bare \r uniformly.
        Str::macro('linesCount', function (string $string): int {
            if ($string === '') {
                return 0;
            }

            $breaks = preg_match_all('/\r\n|\r|\n/', $string);

            return ($breaks === false ? 0 : $breaks) + 1;
        });

        // Interpolate `:placeholder` tokens from an associative replacement map,
        // longest key first so :foo_bar is replaced before :foo. This is the
        // corrected intent of the broken legacy Interpolate macro (which called a
        // nonexistent Str::interpolate).
        Str::macro('interpolate', function (string $string, array $replacements): string {
            $keys = array_keys($replacements);
            usort($keys, static fn (mixed $a, mixed $b): int => strlen((string) $b) <=> strlen((string) $a));

            foreach ($keys as $key) {
                $string = str_replace(':' . $key, Cast::toString($replacements[$key]), $string);
            }

            return $string;
        });

        // --- String similarity (native, no third-party / pheg dependency) ---

        // Levenshtein edit distance (byte-based, like PHP's native levenshtein()).
        Str::macro('levenshtein', fn (string $string, string $other): int => levenshtein($string, $other));

        // Similarity as a percentage (0–100) via PHP's similar_text().
        Str::macro('similarText', function (string $string, string $other): float {
            similar_text($string, $other, $percent);

            return round($percent, 2);
        });

        // Jaro–Winkler similarity (0–1), a pure-PHP implementation favouring a
        // common prefix — useful for fuzzy name/handle matching.
        Str::macro('jaroWinkler', function (string $string, string $other): float {
            $len1 = strlen($string);
            $len2 = strlen($other);

            if ($len1 === 0 && $len2 === 0) {
                return 1.0;
            }

            if ($len1 === 0 || $len2 === 0) {
                return 0.0;
            }

            $matchDistance = max(0, (int) floor(max($len1, $len2) / 2) - 1);
            $s1Matches = array_fill(0, $len1, false);
            $s2Matches = array_fill(0, $len2, false);
            $matches = 0;

            for ($i = 0; $i < $len1; $i++) {
                $start = max(0, $i - $matchDistance);
                $end = min($i + $matchDistance + 1, $len2);

                for ($j = $start; $j < $end; $j++) {
                    if ($s2Matches[$j] || $string[$i] !== $other[$j]) {
                        continue;
                    }

                    $s1Matches[$i] = true;
                    $s2Matches[$j] = true;
                    $matches++;
                    break;
                }
            }

            if ($matches === 0) {
                return 0.0;
            }

            $transpositions = 0;
            $k = 0;

            for ($i = 0; $i < $len1; $i++) {
                if (!$s1Matches[$i]) {
                    continue;
                }

                while (!$s2Matches[$k]) {
                    $k++;
                }

                if ($string[$i] !== $other[$k]) {
                    $transpositions++;
                }

                $k++;
            }

            $transpositions = (int) ($transpositions / 2);
            $jaro = ($matches / $len1 + $matches / $len2 + ($matches - $transpositions) / $matches) / 3;

            $prefix = 0;
            $maxPrefix = min(4, $len1, $len2);

            for ($i = 0; $i < $maxPrefix; $i++) {
                if ($string[$i] !== $other[$i]) {
                    break;
                }

                $prefix++;
            }

            return round($jaro + $prefix * 0.1 * (1 - $jaro), 4);
        });

        // The closest match among $candidates by Levenshtein distance (ties go to
        // the first candidate). Returns null for an empty candidate list.
        Str::macro('closest', function (string $string, array $candidates): ?string {
            $closest = null;
            $shortest = PHP_INT_MAX;

            foreach ($candidates as $candidate) {
                $candidate = Cast::toString($candidate);
                $distance = levenshtein($string, $candidate);

                if ($distance < $shortest) {
                    $shortest = $distance;
                    $closest = $candidate;
                }
            }

            return $closest;
        });
    }

    private function registerStringableMacros(): void
    {
        Stringable::macro('kebabToTitle', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::kebabToTitle((string) $this));
        });

        Stringable::macro('snakeToTitle', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::snakeToTitle((string) $this));
        });

        Stringable::macro('camelToTitle', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::camelToTitle((string) $this));
        });

        Stringable::macro('truncateMiddle', function (int $length = 50, string $middle = '...'): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::truncateMiddle((string) $this, $length, $middle));
        });

        Stringable::macro('isEmail', function (): bool {
            /** @var Stringable $this */
            return Str::isEmail((string) $this);
        });

        Stringable::macro('stripWhitespace', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::stripWhitespace((string) $this));
        });

        Stringable::macro('normalizeWhitespace', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::normalizeWhitespace((string) $this));
        });

        Stringable::macro('toBool', function (): bool {
            /** @var Stringable $this */
            return Str::toBool((string) $this);
        });

        Stringable::macro('wrapWith', function (string $wrapper = '"'): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::wrapWith((string) $this, $wrapper));
        });

        Stringable::macro('matches', function (string $pattern): bool {
            /** @var Stringable $this */
            return Str::matches((string) $this, $pattern);
        });

        Stringable::macro('reverseString', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::reverseString((string) $this));
        });

        Stringable::macro('countWords', function (): int {
            /** @var Stringable $this */
            return Str::countWords((string) $this);
        });

        Stringable::macro('removeAccents', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::removeAccents((string) $this));
        });

        Stringable::macro('readingMinutes', function (int $wordsPerMinute = 200): int {
            /** @var Stringable $this */
            return Str::readingMinutes((string) $this, $wordsPerMinute);
        });

        Stringable::macro('highlightWords', function (string|array $words): HtmlString {
            /** @var Stringable $this */
            return Str::highlightWords((string) $this, $words);
        });

        // Note: Stringable::stripTags() and Stringable::fromBase64() are native
        // Laravel methods, so no Stringable macro is registered for them (a macro
        // would be shadowed by the core method). Str::stripTags has no native
        // equivalent, so it is kept above.
        Stringable::macro('linesCount', function (): int {
            /** @var Stringable $this */
            return Str::linesCount((string) $this);
        });

        Stringable::macro('interpolate', function (array $replacements): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::interpolate((string) $this, $replacements));
        });

        Stringable::macro('levenshtein', function (string $other): int {
            /** @var Stringable $this */
            return Str::levenshtein((string) $this, $other);
        });

        Stringable::macro('similarText', function (string $other): float {
            /** @var Stringable $this */
            return Str::similarText((string) $this, $other);
        });

        Stringable::macro('jaroWinkler', function (string $other): float {
            /** @var Stringable $this */
            return Str::jaroWinkler((string) $this, $other);
        });

        Stringable::macro('closest', function (array $candidates): ?string {
            /** @var Stringable $this */
            return Str::closest((string) $this, $candidates);
        });
    }
}

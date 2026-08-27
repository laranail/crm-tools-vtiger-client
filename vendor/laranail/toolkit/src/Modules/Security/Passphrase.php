<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * Fluent, immutable EFF-diceware passphrase generator.
 *
 * Builds memorable, high-entropy passphrases by drawing words uniformly at
 * random from the EFF Large Wordlist (7776 CC0 words) with a native CSPRNG
 * ({@see random_int()} only — never `rand`/`mt_rand`/`uniqid`/`Str::random`).
 * Each chain method returns a fresh instance (clone-and-mutate via {@see with()}),
 * so a configured builder is reusable.
 *
 * Every word contributes `log2(7776) ≈ 12.925` bits, so entropy is simply
 * `wordCount * log2(7776)`. A 6-word passphrase scores ≈ 77.5 bits — the EFF's
 * recommended memorable-but-strong default.
 *
 * The wordlist is loaded once per process and statically cached (NOT re-read per
 * {@see generate()}), and asserted to contain exactly 7776 entries.
 *
 * @see https://www.eff.org/dice
 * @see https://www.eff.org/deeplinks/2016/07/new-wordlists-random-passphrases
 */
final class Passphrase implements \Stringable
{
    /** EFF Large Wordlist size (6^5). */
    public const WORDLIST_SIZE = 7776;

    private const SEP_HYPHEN = '-';

    private const ALLOWED_SEPARATORS = ['-', '_', ' ', ''];

    private const ALLOWED_CAPITALIZE = ['none', 'first', 'all', 'title'];

    /**
     * Process-wide cache of the EFF wordlist. Loaded once on first use.
     *
     * @var list<string>|null
     */
    private static ?array $wordlist = null;

    private int $wordCount = 6;

    private string $separator = self::SEP_HYPHEN;

    private string $capitalize = 'none';

    private int $numberDigits = 0;

    private ?string $symbol = null;

    // --- Presets -------------------------------------------------------------

    /** Six words, hyphen-separated — the EFF memorable default (≈ 77.5 bits). */
    public static function memorable(): self
    {
        $instance = new self();
        $instance->wordCount = 6;
        $instance->separator = self::SEP_HYPHEN;

        return $instance;
    }

    /** Alias of {@see memorable()} — the package default passphrase shape. */
    public static function default(): self
    {
        return self::memorable();
    }

    // --- Chain (immutable) ---------------------------------------------------

    /**
     * Set the number of words (guarded to 1..20).
     *
     * @throws InvalidArgumentException when `$count` is outside 1..20
     */
    public function wordCount(int $count): self
    {
        if ($count < 1 || $count > 20) {
            throw new InvalidArgumentException("wordCount must be between 1 and 20, got [{$count}].");
        }

        return $this->with(fn (self $c) => $c->wordCount = $count);
    }

    /**
     * Set the inter-word separator. One of `-`, `_`, ` ` (space) or `''` (none).
     *
     * @throws InvalidArgumentException on an unsupported separator
     */
    public function separator(string $separator): self
    {
        if (!in_array($separator, self::ALLOWED_SEPARATORS, true)) {
            throw new InvalidArgumentException(
                sprintf("Invalid separator [%s]. Allowed: '-', '_', ' ', ''.", $separator),
            );
        }

        return $this->with(fn (self $c) => $c->separator = $separator);
    }

    /**
     * Capitalisation strategy: `none` (lowercase), `first` (first word only),
     * `all` (UPPERCASE every word) or `title` (Title-case every word, including
     * each segment of a hyphenated compound — "felt-tip" → "Felt-Tip").
     *
     * @param 'none'|'first'|'all'|'title' $strategy
     *
     * @throws InvalidArgumentException on an unknown strategy
     */
    public function capitalize(string $strategy): self
    {
        if (!in_array($strategy, self::ALLOWED_CAPITALIZE, true)) {
            throw new InvalidArgumentException(
                sprintf("Invalid capitalize strategy [%s]. Allowed: 'none', 'first', 'all', 'title'.", $strategy),
            );
        }

        return $this->with(fn (self $c) => $c->capitalize = $strategy);
    }

    /**
     * Append `$digits` random decimal digits (CSPRNG) as a trailing token.
     *
     * @throws InvalidArgumentException when `$digits` is < 1
     */
    public function withNumber(int $digits): self
    {
        if ($digits < 1) {
            throw new InvalidArgumentException("withNumber digits must be >= 1, got [{$digits}].");
        }

        return $this->with(fn (self $c) => $c->numberDigits = $digits);
    }

    /**
     * Append a symbol token. Pass `null` to draw a random symbol from a safe set,
     * or a specific string to append verbatim.
     */
    public function withSymbol(?string $symbol = null): self
    {
        return $this->with(fn (self $c) => $c->symbol = $symbol ?? $this->randomSymbol());
    }

    // --- Terminals -----------------------------------------------------------

    /** Generate the passphrase. */
    public function generate(): string
    {
        $words = $this->pickWords();

        $tokens = $this->applyCapitalisation($words);

        if ($this->numberDigits > 0) {
            $tokens[] = $this->randomDigits($this->numberDigits);
        }

        if ($this->symbol !== null && $this->symbol !== '') {
            $tokens[] = $this->symbol;
        }

        return implode($this->separator, $tokens);
    }

    /**
     * Generate the passphrase together with its entropy metadata.
     *
     * @return array{passphrase: string, entropy: float, word_count: int, words: list<string>}
     */
    public function generateWithMetadata(): array
    {
        $words = $this->pickWords();
        $tokens = $this->applyCapitalisation($words);

        if ($this->numberDigits > 0) {
            $tokens[] = $this->randomDigits($this->numberDigits);
        }

        if ($this->symbol !== null && $this->symbol !== '') {
            $tokens[] = $this->symbol;
        }

        return [
            'passphrase' => implode($this->separator, $tokens),
            'entropy' => $this->wordCount * log(self::WORDLIST_SIZE, 2),
            'word_count' => $this->wordCount,
            'words' => $words,
        ];
    }

    /** Convenience: a {@see generate()}d passphrase. */
    public function __toString(): string
    {
        return $this->generate();
    }

    // --- Internals -----------------------------------------------------------

    /**
     * Draw {@see $wordCount} words uniformly at random (with replacement) from
     * the EFF list. Independent uniform draws preserve `wordCount * log2(7776)`.
     *
     * @return list<string>
     */
    private function pickWords(): array
    {
        $list = self::wordlist();
        $max = count($list) - 1;

        $words = [];
        for ($i = 0; $i < $this->wordCount; $i++) {
            $words[] = $list[random_int(0, $max)];
        }

        return $words;
    }

    /**
     * Apply the configured capitalisation to each drawn word.
     *
     * @param list<string> $words
     *
     * @return list<string>
     */
    private function applyCapitalisation(array $words): array
    {
        return match ($this->capitalize) {
            'all' => array_map(strtoupper(...), $words),
            // Title-case every word, capitalising each segment of a compound
            // (hyphenated) word too — e.g. "felt-tip" → "Felt-Tip" — so the result
            // is fully title-cased regardless of the separator in use.
            'title' => array_map(static fn (string $word): string => ucwords($word, ' -'), $words),
            'first' => $this->capitaliseFirstWord($words),
            default => $words,
        };
    }

    /**
     * Capitalise only the first word of the list.
     *
     * @param list<string> $words
     *
     * @return list<string>
     */
    private function capitaliseFirstWord(array $words): array
    {
        if ($words === []) {
            return $words;
        }

        $words[0] = ucfirst($words[0]);

        return $words;
    }

    /** A fixed-width CSPRNG decimal string. */
    private function randomDigits(int $digits): string
    {
        $out = '';
        for ($i = 0; $i < $digits; $i++) {
            $out .= (string) random_int(0, 9);
        }

        return $out;
    }

    /** Pick a single symbol uniformly at random from a safe set. */
    private function randomSymbol(): string
    {
        $symbols = '!@#$%^&*?-_+=';

        return $symbols[random_int(0, strlen($symbols) - 1)];
    }

    /**
     * Load (once) and return the static-cached EFF Large Wordlist. The exact
     * 7776-word assertion lives in {@see SecurityData::passphraseWords()}.
     *
     * @throws RuntimeException when the list is missing or not exactly 7776 words
     *
     * @return list<string>
     */
    private static function wordlist(): array
    {
        return self::$wordlist ??= SecurityData::passphraseWords();
    }

    /**
     * Return a mutated clone, keeping the builder immutable.
     *
     * @param callable(self): mixed $mutator
     */
    private function with(callable $mutator): self
    {
        $clone = clone $this;
        $mutator($clone);

        return $clone;
    }
}

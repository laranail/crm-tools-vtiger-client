<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Fluent, immutable username / handle builder.
 *
 * Replaces the legacy pheg-bound `Username::name2username()` with a native,
 * fully chainable generator. Every chain method returns a fresh instance
 * (clone-and-mutate via {@see with()}), so a configured builder is reusable.
 *
 * Research-backed defaults (lowercase, ASCII transliteration, leading-letter
 * enforcement, collapsed/trimmed separators, a 3..30 length window, reserved
 * word filtering and a bounded uniqueness loop) follow common handle rules used
 * by GitHub / Instagram / YouTube and the regex-validation literature.
 *
 * Spaces are NEVER permitted in a generated handle. Whitespace in the source is
 * silently stripped during sanitisation, and the generated result is guaranteed
 * to contain no space character. {@see allow()} rejects a space outright, and
 * {@see rejectSpaces()} opts into hard failure when the SOURCE itself contains a
 * space (instead of silently stripping it). {@see alphanumericOnly()} narrows the
 * handle to bare `[a-z0-9]` with no separators at all.
 *
 * @see https://www.username.dev/username-validation-rules
 * @see https://www.w3tutorials.net/blog/regular-expression-to-validate-username/
 * @see https://www.handlegrab.com/blog/social-media-username-rules-limits
 */
final class Username implements \Stringable
{
    /** Separators that may sit between username parts. */
    private const ALLOWED_SEPARATORS = ['', '.', '_', '-'];

    /** Characters (besides alphanumerics) that are ever permitted in a handle. */
    private const SAFE_EXTRA_CHARS = '._-';

    /** Casing strategies. */
    private const CASE_LOWER = 'lower';

    private const CASE_UPPER = 'upper';

    private const CASE_PRESERVE = 'preserve';

    /** Upper bound on uniqueness retries before giving up (replaces unbounded recursion). */
    private const MAX_UNIQUE_ATTEMPTS = 100;

    /**
     * The seed for generation.
     *
     * - `for()` / `fromEmail()`    -> a plain string source.
     * - `fromName()`              -> `[first, last]` (last may be '').
     * - `random()`               -> null (a fresh handle is synthesised).
     *
     * Typed as a union (not `?string`) so name-mode's array seed is sound under
     * phpstan level 9 without casts.
     *
     * @var string|array<int,string>|null
     */
    private string|array|null $source = null;

    private string $separator = '';

    private string $case = self::CASE_LOWER;

    private bool $ascii = true;

    private int $minLength = 3;

    private int $maxLength = 30;

    private string $prefix = '';

    private string $suffix = '';

    private int $randomSuffixDigits = 0;

    /** Extra permitted characters, always a subset of {@see SAFE_EXTRA_CHARS}. */
    private string $allowedExtra = self::SAFE_EXTRA_CHARS;

    /** @var list<string> lowercased reserved names a generated handle must avoid */
    private array $reserved = [];

    /** When true, a space in the SOURCE is a hard error instead of being silently stripped. */
    private bool $rejectSpaces = false;

    /** @var (callable(string): bool)|null returns true when a handle is AVAILABLE */
    private $uniquenessChecker = null;

    /** Random handle defaults (random() mode). */
    private string $randomPrefix = 'user';

    private int $randomDigits = 4;

    private bool $isRandom = false;

    // --- Entry points --------------------------------------------------------

    /**
     * Seed the builder from an arbitrary source string (a name, email local
     * part, slug — anything). The string is sanitised at generation time.
     */
    public static function for(string $source): self
    {
        $instance = new self();
        $instance->source = $source;

        return $instance;
    }

    /**
     * Seed from an email address: the local part (before `@`) becomes the
     * source. Defaults to a `.` separator to mirror typical `john.doe` handles.
     */
    public static function fromEmail(string $email): self
    {
        $instance = new self();
        $instance->source = Str::before($email, '@');
        $instance->separator = '.';

        return $instance;
    }

    /**
     * Seed from a person's name. Stored as `[first, last]` so {@see candidates()}
     * can build the canonical name-mode variants (janedoe / jane.doe / jdoe …).
     */
    public static function fromName(string $first, ?string $last = null): self
    {
        $instance = new self();
        $instance->source = [$first, $last ?? ''];

        return $instance;
    }

    /**
     * Seed a fresh random handle: a sanitised alphabetic prefix followed by
     * `$digits` random digits (e.g. `user4821`).
     */
    public static function random(string $prefix = 'user', int $digits = 4): self
    {
        $instance = new self();
        $instance->isRandom = true;
        $instance->randomPrefix = $prefix;
        $instance->randomDigits = self::clampDigits($digits);

        return $instance;
    }

    // --- Chain (immutable) ---------------------------------------------------

    /** Set the separator between parts. One of '', '.', '_', '-'. */
    public function separator(string $separator): self
    {
        if (!in_array($separator, self::ALLOWED_SEPARATORS, true)) {
            throw new InvalidArgumentException(
                "Invalid separator [{$separator}]. Allowed: '', '.', '_', '-'.",
            );
        }

        return $this->with(fn (self $c) => $c->separator = $separator);
    }

    /** Force the generated handle to lowercase (the default). */
    public function lowercase(): self
    {
        return $this->with(fn (self $c) => $c->case = self::CASE_LOWER);
    }

    /** Force the generated handle to uppercase. */
    public function uppercase(): self
    {
        return $this->with(fn (self $c) => $c->case = self::CASE_UPPER);
    }

    /** Keep the source casing as-is. */
    public function preserveCase(): self
    {
        return $this->with(fn (self $c) => $c->case = self::CASE_PRESERVE);
    }

    /** Clamp the maximum length (must be >= 1 and >= the current minimum). */
    public function maxLength(int $length): self
    {
        if ($length < 1) {
            throw new InvalidArgumentException("maxLength must be >= 1, got [{$length}].");
        }

        if ($length < $this->minLength) {
            throw new InvalidArgumentException(
                "maxLength [{$length}] must be >= minLength [{$this->minLength}].",
            );
        }

        return $this->with(fn (self $c) => $c->maxLength = $length);
    }

    /** Set the minimum length (short results are padded with random digits). */
    public function minLength(int $length): self
    {
        if ($length < 1) {
            throw new InvalidArgumentException("minLength must be >= 1, got [{$length}].");
        }

        if ($length > $this->maxLength) {
            throw new InvalidArgumentException(
                "minLength [{$length}] must be <= maxLength [{$this->maxLength}].",
            );
        }

        return $this->with(fn (self $c) => $c->minLength = $length);
    }

    /** Toggle ASCII transliteration of unicode/accented characters (on by default). */
    public function ascii(bool $ascii = true): self
    {
        return $this->with(fn (self $c) => $c->ascii = $ascii);
    }

    /** Prepend a (sanitised) prefix to the handle. */
    public function prefix(string $prefix): self
    {
        return $this->with(fn (self $c) => $c->prefix = $prefix);
    }

    /** Append a (sanitised) suffix to the handle. */
    public function suffix(string $suffix): self
    {
        return $this->with(fn (self $c) => $c->suffix = $suffix);
    }

    /** Append `$digits` random digits to every generated handle. */
    public function withRandomSuffix(int $digits): self
    {
        return $this->with(fn (self $c) => $c->randomSuffixDigits = self::clampDigits($digits));
    }

    /**
     * Reject these names (case-insensitively) from the generated handle. A clash
     * is treated like a taken handle: the uniqueness loop appends random digits.
     *
     * @param array<int,string> $blacklist
     */
    public function reserved(array $blacklist): self
    {
        $normalised = [];
        foreach ($blacklist as $name) {
            $name = Str::lower(trim($name));
            if ($name !== '') {
                $normalised[] = $name;
            }
        }

        return $this->with(fn (self $c) => $c->reserved = array_values(array_unique($normalised)));
    }

    /**
     * Restrict which of the safe extra characters (`._-`) survive sanitisation.
     * Passing `''` strips all separators; passing `'.'` keeps only dots, etc.
     *
     * A space is never an allowable handle character: passing one (or any other
     * char outside `._-`) throws, so a space can never sneak into the result.
     *
     * @throws InvalidArgumentException when `$extraChars` contains a space or any
     *                                  character outside the safe set `._-`
     */
    public function allow(string $extraChars): self
    {
        if (str_contains($extraChars, ' ')) {
            throw new InvalidArgumentException(
                'A space is not an allowable username character.',
            );
        }

        $chars = '';
        foreach (str_split($extraChars) as $char) {
            if (str_contains(self::SAFE_EXTRA_CHARS, $char)) {
                $chars .= $char;

                continue;
            }

            throw new InvalidArgumentException(
                "Character [{$char}] is not allowed. Only '._-' may be permitted.",
            );
        }

        return $this->with(fn (self $c) => $c->allowedExtra = $chars);
    }

    /**
     * Opt into strict whitespace handling: instead of silently stripping spaces
     * from the source, fail loudly if the source string contains any whitespace.
     *
     * The check runs at generation time against the resolved source (name parts
     * or the raw `for()` / `fromEmail()` string). Generated handles are always
     * space-free regardless of this flag — this only changes silent-strip into
     * a hard error for callers who want that strictness.
     */
    public function rejectSpaces(): self
    {
        return $this->with(fn (self $c) => $c->rejectSpaces = true);
    }

    /**
     * Strip the handle down to bare `[a-z0-9]`: no separators at all, regardless
     * of any prior {@see allow()} / {@see separator()} configuration. Equivalent
     * to `->separator('')->allow('')` but expressed as a single intent.
     */
    public function alphanumericOnly(): self
    {
        return $this->with(function (self $c): void {
            $c->separator = '';
            $c->allowedExtra = '';
        });
    }

    /**
     * Plug in a backend-agnostic availability checker. `$checker($username)`
     * returns true when the handle is AVAILABLE. Works with any backend
     * (Eloquent, cache, raw DB) — no hard Eloquent coupling.
     *
     * @param callable(string): bool $checker
     */
    public function unique(callable $checker): self
    {
        return $this->with(fn (self $c) => $c->uniquenessChecker = $checker);
    }

    // --- Terminals -----------------------------------------------------------

    /**
     * Build the final, sanitised, unique handle.
     *
     * Pipeline: sanitise (ascii -> strip unsafe -> collapse/trim separators ->
     * leading-alpha) -> prefix/suffix -> casing -> length clamp -> reserved
     * check -> bounded uniqueness loop (append random digits each retry).
     *
     * @throws RuntimeException when no available handle is found within the bound
     */
    public function generate(): string
    {
        $this->guardSourceSpaces();

        $base = $this->buildBase();

        $candidate = $this->finalise($base);

        if ($this->isAcceptable($candidate)) {
            return self::assertNoSpaces($candidate);
        }

        for ($attempt = 0; $attempt < self::MAX_UNIQUE_ATTEMPTS; $attempt++) {
            $candidate = $this->finalise($base . $this->separator . $this->randomDigitString(4));

            if ($this->isAcceptable($candidate)) {
                return self::assertNoSpaces($candidate);
            }
        }

        throw new RuntimeException(
            sprintf('Could not generate an available username after %d attempts.', self::MAX_UNIQUE_ATTEMPTS),
        );
    }

    /**
     * Deterministic suggestions, ordered from most to least desirable.
     *
     * In name mode the canonical variants are produced first
     * (`janedoe`, `jane.doe`, `jane_doe`, `jdoe`, `jane.d`, `jane`, `doe`),
     * followed by numeric-suffixed variants of the primary handle to pad the
     * list out to `$n`.
     *
     * @return list<string>
     */
    public function candidates(int $n = 10): array
    {
        if ($n < 1) {
            throw new InvalidArgumentException("candidates count must be >= 1, got [{$n}].");
        }

        $parts = $this->nameParts();
        $first = $parts[0];
        $last = $parts[1];

        $raw = [];

        if ($first === '' && $last === '') {
            $raw[] = $this->buildBase();
        } elseif ($last !== '' && $first !== '') {
            $fi = Str::substr($first, 0, 1);
            $li = Str::substr($last, 0, 1);

            $raw[] = $first . $last;
            $raw[] = $first . '.' . $last;
            $raw[] = $first . '_' . $last;
            $raw[] = $fi . $last;
            $raw[] = $first . '.' . $li;
            $raw[] = $first;
            $raw[] = $last;
        } else {
            $raw[] = $first !== '' ? $first : $last;
        }

        $finalised = [];
        foreach ($raw as $value) {
            $handle = $this->finalise($value);
            if ($handle !== '') {
                $finalised[] = $handle;
            }
        }

        $finalised = array_values(array_unique($finalised));

        if ($finalised === []) {
            return [];
        }

        $primary = $finalised[0];
        $i = 0;
        while (count($finalised) < $n && $i < self::MAX_UNIQUE_ATTEMPTS) {
            $variant = $this->finalise($primary . $this->randomDigitString(3));
            if ($variant !== '' && !in_array($variant, $finalised, true)) {
                $finalised[] = $variant;
            }
            $i++;
        }

        return array_slice($finalised, 0, $n);
    }

    /**
     * The builder's resolved configuration plus the generated handle.
     *
     * @return array{
     *     username: string,
     *     separator: string,
     *     case: string,
     *     ascii: bool,
     *     minLength: int,
     *     maxLength: int,
     *     prefix: string,
     *     suffix: string,
     *     reserved: list<string>,
     * }
     */
    public function toArray(): array
    {
        return [
            'username' => (string) $this,
            'separator' => $this->separator,
            'case' => $this->case,
            'ascii' => $this->ascii,
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'reserved' => $this->reserved,
        ];
    }

    /** Never throws — yields '' when generation is impossible. */
    public function __toString(): string
    {
        try {
            return $this->generate();
        } catch (\Exception) {
            // generate() only ever throws InvalidArgumentException / RuntimeException;
            // the Stringable contract must not throw, so yield '' instead.
            return '';
        }
    }

    // --- Internals -----------------------------------------------------------

    /** The sanitised base handle, before prefix/suffix/casing/length. */
    private function buildBase(): string
    {
        if ($this->isRandom) {
            $prefix = (string) preg_replace('/[^a-z]/', '', Str::lower($this->randomPrefix));
            if ($prefix === '') {
                $prefix = 'user';
            }

            return $prefix . $this->randomDigitString($this->randomDigits);
        }

        $parts = $this->nameParts();

        if ($parts[0] !== '' || $parts[1] !== '') {
            $joined = array_values(array_filter([$parts[0], $parts[1]], static fn (string $p): bool => $p !== ''));

            return implode($this->separator, $joined);
        }

        return is_string($this->source) ? $this->source : '';
    }

    /**
     * Resolve the name-mode parts as already-sanitised tokens. Returns
     * `['', '']` when the builder isn't in name mode.
     *
     * @return array{0: string, 1: string}
     */
    private function nameParts(): array
    {
        if (!is_array($this->source)) {
            return ['', ''];
        }

        return [
            $this->sanitiseToken($this->source[0] ?? ''),
            $this->sanitiseToken($this->source[1] ?? ''),
        ];
    }

    /** Sanitise a single token to its bare alphanumeric form (no separators). */
    private function sanitiseToken(string $token): string
    {
        if ($this->ascii) {
            $token = Str::ascii($token);
        }

        return (string) preg_replace('/[^a-zA-Z0-9]/', '', $token);
    }

    /**
     * Apply the full finishing pipeline to a raw candidate string:
     * ascii -> strip unsafe -> collapse/trim separators -> prefix/suffix ->
     * leading-alpha -> casing -> length clamp.
     */
    private function finalise(string $value): string
    {
        if ($this->ascii) {
            $value = Str::ascii($value);
        }

        // Strip everything but alphanumerics and the permitted extra chars.
        $allowed = preg_quote($this->allowedExtra, '/');
        $value = (string) preg_replace('/[^a-zA-Z0-9' . $allowed . ']/', '', $value);

        $value = $this->collapseSeparators($value);

        $value = $this->affix($this->prefix) . $value . $this->affix($this->suffix);

        if ($this->randomSuffixDigits > 0) {
            $value .= $this->randomDigitString($this->randomSuffixDigits);
        }

        $value = $this->collapseSeparators($value);
        $value = $this->enforceLeadingAlpha($value);

        $value = match ($this->case) {
            self::CASE_LOWER => Str::lower($value),
            self::CASE_UPPER => Str::upper($value),
            default => $value,
        };

        return $this->clampLength($value);
    }

    /** Sanitise a prefix/suffix the same way as the body (no separators of its own). */
    private function affix(string $affix): string
    {
        if ($affix === '') {
            return '';
        }

        if ($this->ascii) {
            $affix = Str::ascii($affix);
        }

        $allowed = preg_quote($this->allowedExtra, '/');

        return (string) preg_replace('/[^a-zA-Z0-9' . $allowed . ']/', '', $affix);
    }

    /** Collapse runs of separators to a single one and trim leading/trailing ones. */
    private function collapseSeparators(string $value): string
    {
        if ($this->allowedExtra === '') {
            return $value;
        }

        $class = preg_quote($this->allowedExtra, '/');

        // Collapse any run of separators (even mixed) down to a single char.
        $value = (string) preg_replace('/([' . $class . '])[' . $class . ']+/', '$1', $value);

        // Trim leading/trailing separators.
        return trim($value, $this->allowedExtra);
    }

    /** Ensure the handle starts with a letter, prefixing `user` otherwise. */
    private function enforceLeadingAlpha(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (ctype_alpha($value[0])) {
            return $value;
        }

        $sep = $this->separator !== '' ? $this->separator : '';

        return $this->collapseSeparators('user' . $sep . $value);
    }

    /** Pad short handles up to minLength, then truncate to maxLength. */
    private function clampLength(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        while (Str::length($value) < $this->minLength) {
            $value .= $this->randomDigitString(1);
        }

        if (Str::length($value) > $this->maxLength) {
            $value = Str::substr($value, 0, $this->maxLength);
            $value = $this->allowedExtra !== '' ? rtrim($value, $this->allowedExtra) : $value;
        }

        return $value;
    }

    /** A handle is acceptable when it's non-empty, not reserved, and available. */
    private function isAcceptable(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        if (in_array(Str::lower($candidate), $this->reserved, true)) {
            return false;
        }

        if ($this->uniquenessChecker !== null) {
            return ($this->uniquenessChecker)($candidate) === true;
        }

        return true;
    }

    /**
     * A fixed-width random digit string. The first digit is 1..9 so the width
     * is exact; the rest are 0..9.
     */
    private function randomDigitString(int $digits): string
    {
        $digits = self::clampDigits($digits);

        $out = (string) random_int(1, 9);
        for ($i = 1; $i < $digits; $i++) {
            $out .= (string) random_int(0, 9);
        }

        return $out;
    }

    /** Clamp a digit count to the sane 1..10 window. */
    private static function clampDigits(int $digits): int
    {
        return max(1, min(10, $digits));
    }

    /**
     * In {@see rejectSpaces()} mode, fail loudly when the resolved source string
     * contains any whitespace instead of silently stripping it.
     *
     * @throws InvalidArgumentException when strict mode is on and the source has whitespace
     */
    private function guardSourceSpaces(): void
    {
        if (!$this->rejectSpaces) {
            return;
        }

        $sources = is_array($this->source)
            ? $this->source
            : [$this->source ?? ''];

        foreach ($sources as $source) {
            if (preg_match('/\s/', $source) === 1) {
                throw new InvalidArgumentException(
                    'The username source must not contain whitespace.',
                );
            }
        }
    }

    /**
     * Hard guarantee: a generated handle never contains a space. This is a
     * belt-and-braces assertion over the sanitised result — sanitisation already
     * strips whitespace, so a hit here would indicate a regression.
     *
     * @throws RuntimeException if a space somehow survived sanitisation
     */
    private static function assertNoSpaces(string $candidate): string
    {
        if (str_contains($candidate, ' ')) {
            throw new RuntimeException('Generated username unexpectedly contained a space.');
        }

        return $candidate;
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

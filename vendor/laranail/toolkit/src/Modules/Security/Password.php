<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Security;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use ZxcvbnPhp\Zxcvbn;

/**
 * Fluent, immutable random-password generator.
 *
 * Builds high-entropy random passwords from a configurable character-class pool
 * using a native CSPRNG ({@see random_int()} only — never `rand`/`mt_rand`/
 * `uniqid`/`Str::random`). Every chain method returns a fresh instance
 * (clone-and-mutate via {@see with()}), so a configured builder is reusable.
 *
 * Defaults follow NIST SP 800-63B / OWASP ASVS guidance: prefer length, draw
 * uniformly from the selected pool, and (optionally) guarantee class coverage
 * and a minimum entropy floor rather than relying on composition rules alone.
 *
 * Entropy is the Shannon worst-case estimate `length * log2(poolSize)` — the
 * information content of a uniformly random draw from the pool, in bits.
 *
 * @see https://pages.nist.gov/800-63-3/sp800-63b.html
 * @see https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
 */
final class Password implements \Stringable
{
    private const LOWERCASE = 'abcdefghijklmnopqrstuvwxyz';

    private const UPPERCASE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const DIGITS = '0123456789';

    private const SYMBOLS = '!@#$%^&*()-_=+[]{};:,.<>?';

    /** Ambiguous, easily-confused glyphs removed by {@see excludeAmbiguous()}. */
    private const AMBIGUOUS = '0O1lI';

    /** Bound on retries for the requireEachClass / minEntropy constraints. */
    private const MAX_ATTEMPTS = 1000;

    /**
     * Bound on retries for the (comparatively slow) zxcvbn `minStrength` gate.
     * Lower than {@see MAX_ATTEMPTS} because each attempt runs a full estimation.
     */
    private const MAX_STRENGTH_ATTEMPTS = 50;

    private int $length = 16;

    private bool $uppercase = true;

    private bool $lowercase = true;

    private bool $digits = true;

    private bool $symbols = true;

    private bool $excludeAmbiguous = false;

    private bool $requireEachClass = true;

    private float $minEntropy = 0.0;

    /** Minimum zxcvbn score (0–4) the generated password must reach; 0 = off. */
    private int $minStrengthScore = 0;

    // --- Presets -------------------------------------------------------------

    /** All four classes, 20 chars, ambiguous glyphs removed, each class required. */
    public static function strong(): self
    {
        $instance = new self();
        $instance->length = 20;
        $instance->symbols = true;
        $instance->excludeAmbiguous = true;
        $instance->requireEachClass = true;

        return $instance;
    }

    /** Letters + digits, no symbols, 16 chars. */
    public static function alphanumeric(): self
    {
        $instance = new self();
        $instance->length = 16;
        $instance->symbols = false;

        return $instance;
    }

    /** Digits only, 6 chars — a numeric PIN/OTP. */
    public static function numeric(): self
    {
        $instance = new self();
        $instance->length = 6;
        $instance->uppercase = false;
        $instance->lowercase = false;
        $instance->digits = true;
        $instance->symbols = false;
        $instance->requireEachClass = false;

        return $instance;
    }

    /** Lowercase + digits, 12 chars — a simple, readable default. */
    public static function basic(): self
    {
        $instance = new self();
        $instance->length = 12;
        $instance->uppercase = false;
        $instance->symbols = false;

        return $instance;
    }

    // --- Chain (immutable) ---------------------------------------------------

    /**
     * Set the password length.
     *
     * @throws InvalidArgumentException when `$length` is < 1
     */
    public function length(int $length): self
    {
        if ($length < 1) {
            throw new InvalidArgumentException("length must be >= 1, got [{$length}].");
        }

        return $this->with(fn (self $c) => $c->length = $length);
    }

    /** Toggle the uppercase class `[A-Z]`. */
    public function uppercase(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->uppercase = $enabled);
    }

    /** Toggle the lowercase class `[a-z]`. */
    public function lowercase(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->lowercase = $enabled);
    }

    /** Toggle the digit class `[0-9]`. */
    public function digits(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->digits = $enabled);
    }

    /** Toggle the symbol class. */
    public function symbols(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->symbols = $enabled);
    }

    /** Drop visually-ambiguous glyphs (`0 O 1 l I`) from every selected class. */
    public function excludeAmbiguous(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->excludeAmbiguous = $enabled);
    }

    /** Require at least one character from each selected class in the result. */
    public function requireEachClass(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->requireEachClass = $enabled);
    }

    /**
     * Require the generated password to meet a minimum entropy floor (bits).
     *
     * @throws InvalidArgumentException when `$bits` is negative
     */
    public function minEntropy(float $bits): self
    {
        if ($bits < 0.0) {
            throw new InvalidArgumentException("minEntropy must be >= 0, got [{$bits}].");
        }

        return $this->with(fn (self $c) => $c->minEntropy = $bits);
    }

    /**
     * Require the generated password to reach a minimum zxcvbn strength score.
     *
     * Scores are zxcvbn's 0–4 scale (0 = trivially guessable, 4 = very strong).
     * The gate is a no-op unless `bjeavons/zxcvbn-php` is installed; when present,
     * {@see generate()} regenerates (bounded by {@see MAX_STRENGTH_ATTEMPTS}) until
     * the score is met, throwing {@see RuntimeException} if the target is
     * effectively unreachable (e.g. `numeric()->minStrength(4)`).
     *
     * @throws InvalidArgumentException when `$score` is outside 0–4
     */
    public function minStrength(int $score): self
    {
        if ($score < 0 || $score > 4) {
            throw new InvalidArgumentException("minStrength score must be between 0 and 4, got [{$score}].");
        }

        return $this->with(fn (self $c) => $c->minStrengthScore = $score);
    }

    // --- Terminals -----------------------------------------------------------

    /**
     * Generate the password.
     *
     * @throws LogicException   when no character class is selected
     * @throws RuntimeException when the requireEachClass / minEntropy constraints
     *                          cannot be satisfied within {@see MAX_ATTEMPTS}
     *                          (e.g. minEntropy(128) on a short lowercase-only pool),
     *                          or when the zxcvbn `minStrength` score cannot be met
     *                          within {@see MAX_STRENGTH_ATTEMPTS} (e.g.
     *                          `numeric()->minStrength(4)`)
     */
    public function generate(): string
    {
        $pools = $this->pools();
        $combined = implode('', $pools);

        if ($combined === '') {
            throw new LogicException('At least one character class must be selected.');
        }

        $this->guardEntropyAchievable($combined);

        $strengthGate = $this->minStrengthScore > 0 && class_exists(Zxcvbn::class);

        for ($strengthAttempt = 0; $strengthAttempt < self::MAX_STRENGTH_ATTEMPTS; $strengthAttempt++) {
            $password = $this->drawConstrained($pools, $combined);

            if (!$strengthGate || $this->meetsStrength($password)) {
                return $password;
            }
        }

        throw new RuntimeException(sprintf(
            'Could not reach a zxcvbn strength score of %d within %d attempts.',
            $this->minStrengthScore,
            self::MAX_STRENGTH_ATTEMPTS,
        ));
    }

    /**
     * Draw a single password satisfying the requireEachClass constraint, retrying
     * up to {@see MAX_ATTEMPTS} times.
     *
     * @param array<string, string> $pools
     *
     * @throws RuntimeException when the constraints cannot be satisfied
     */
    private function drawConstrained(array $pools, string $combined): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $password = $this->draw($pools, $combined);

            if ($this->requireEachClass && !$this->coversEachClass($password, $pools)) {
                continue;
            }

            return $password;
        }

        throw new RuntimeException(
            sprintf('Could not satisfy the password constraints within %d attempts.', self::MAX_ATTEMPTS),
        );
    }

    /** True when `$password` reaches the configured zxcvbn `minStrengthScore`. */
    private function meetsStrength(string $password): bool
    {
        return self::strength($password)['score'] >= $this->minStrengthScore;
    }

    /**
     * Generate the password together with its entropy metadata.
     *
     * When `bjeavons/zxcvbn-php` is installed the result additionally carries the
     * zxcvbn estimation (`zxcvbn_score`, `zxcvbn_guesses`,
     * `zxcvbn_crack_times_seconds`, `zxcvbn_feedback`); those keys are absent when
     * the optional dependency is not present.
     *
     * @return array{
     *     password: string,
     *     entropy: float,
     *     charset_size: int,
     *     length: int,
     *     zxcvbn_score?: int,
     *     zxcvbn_guesses?: float,
     *     zxcvbn_crack_times_seconds?: array<string, float|int>,
     *     zxcvbn_feedback?: array{warning: string, suggestions: list<string>}
     * }
     */
    public function generateWithMetadata(): array
    {
        $password = $this->generate();
        $charsetSize = strlen(implode('', $this->pools()));

        $metadata = [
            'password' => $password,
            'entropy' => $this->entropyBits($charsetSize),
            'charset_size' => $charsetSize,
            'length' => $this->length,
        ];

        if (class_exists(Zxcvbn::class)) {
            $result = self::strength($password);

            $metadata['zxcvbn_score'] = $result['score'];
            $metadata['zxcvbn_guesses'] = $result['guesses'];
            $metadata['zxcvbn_crack_times_seconds'] = $result['crack_times_seconds'];
            $metadata['zxcvbn_feedback'] = $result['feedback'];
        }

        return $metadata;
    }

    /**
     * Estimate a password's strength with zxcvbn (realistic guess-based scoring).
     *
     * Returns the full zxcvbn result, including `score` (int 0–4), `guesses`
     * (float), `crack_times_seconds` (array), and `feedback`
     * (`['warning' => string, 'suggestions' => string[]]`).
     *
     * @param list<string> $userInputs Site/user-specific tokens to penalise (names, email, etc.)
     *
     * @throws LogicException when `bjeavons/zxcvbn-php` is not installed
     *
     * @return array{
     *     score: int,
     *     guesses: float,
     *     crack_times_seconds: array<string, float|int>,
     *     feedback: array{warning: string, suggestions: list<string>}
     * }
     */
    public static function strength(string $password, array $userInputs = []): array
    {
        if (!class_exists(Zxcvbn::class)) {
            throw new LogicException(
                'zxcvbn strength estimation requires the optional "bjeavons/zxcvbn-php" package. '
                . 'Install it with: composer require bjeavons/zxcvbn-php',
            );
        }

        /**
         * @var array{
         *     score: int,
         *     guesses: float,
         *     crack_times_seconds: array<string, float|int>,
         *     feedback: array{warning: string, suggestions: list<string>}
         * } $result
         */
        $result = (new Zxcvbn())->passwordStrength($password, $userInputs);

        return $result;
    }

    /** Convenience: a {@see generate()}d password. */
    public function __toString(): string
    {
        return $this->generate();
    }

    // --- Internals -----------------------------------------------------------

    /**
     * The selected, ambiguity-filtered character pools, keyed by class.
     *
     * @return array<string, string>
     */
    private function pools(): array
    {
        $pools = [];

        if ($this->lowercase) {
            $pools['lower'] = $this->filterAmbiguous(self::LOWERCASE);
        }

        if ($this->uppercase) {
            $pools['upper'] = $this->filterAmbiguous(self::UPPERCASE);
        }

        if ($this->digits) {
            $pools['digits'] = $this->filterAmbiguous(self::DIGITS);
        }

        if ($this->symbols) {
            $pools['symbols'] = $this->filterAmbiguous(self::SYMBOLS);
        }

        return array_filter($pools, static fn (string $pool): bool => $pool !== '');
    }

    /** Strip ambiguous glyphs from a pool when {@see excludeAmbiguous()} is on. */
    private function filterAmbiguous(string $pool): string
    {
        if (!$this->excludeAmbiguous) {
            return $pool;
        }

        $ambiguous = str_split(self::AMBIGUOUS);

        return str_replace($ambiguous, '', $pool);
    }

    /**
     * Draw one full password uniformly from the combined pool.
     *
     * @param array<string, string> $pools
     */
    private function draw(array $pools, string $combined): string
    {
        $size = strlen($combined);
        $password = '';

        for ($i = 0; $i < $this->length; $i++) {
            $password .= $combined[random_int(0, $size - 1)];
        }

        return $password;
    }

    /**
     * True when `$password` contains at least one character from every pool.
     *
     * @param array<string, string> $pools
     */
    private function coversEachClass(string $password, array $pools): bool
    {
        return array_all($pools, fn (string $pool): bool => $this->containsAny($password, $pool));
    }

    /** True when any character of `$pool` appears in `$password`. */
    private function containsAny(string $password, string $pool): bool
    {
        $chars = str_split($pool);

        return array_any($chars, fn (string $char): bool => str_contains($password, $char));
    }

    /** Worst-case Shannon entropy of a uniform draw: `length * log2(poolSize)`. */
    private function entropyBits(int $charsetSize): float
    {
        if ($charsetSize <= 1) {
            return 0.0;
        }

        return $this->length * log($charsetSize, 2);
    }

    /**
     * Fail fast when the minimum-entropy floor is mathematically unreachable for
     * the configured pool and length — no amount of retrying could satisfy it.
     *
     * @throws RuntimeException when the entropy target is impossible
     */
    private function guardEntropyAchievable(string $combined): void
    {
        if ($this->minEntropy <= 0.0) {
            return;
        }

        if ($this->entropyBits(strlen($combined)) < $this->minEntropy) {
            throw new RuntimeException(sprintf(
                'minEntropy of %.2f bits is unreachable: %d chars over a %d-symbol pool yields at most %.2f bits.',
                $this->minEntropy,
                $this->length,
                strlen($combined),
                $this->entropyBits(strlen($combined)),
            ));
        }
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

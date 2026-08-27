<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Modules\Security\SecurityData;
use Throwable;
use ZxcvbnPhp\Zxcvbn;

/**
 * Reject weak passwords: a large common-password denylist, optional minimum
 * length / Shannon-entropy gates, and an opt-in, privacy-preserving Have I Been
 * Pwned (HIBP) breach check.
 *
 * Defaults are fully offline and preserve the original behaviour (denylist only,
 * case-insensitive). All gates are opt-in via the constructor or the fluent
 * {@see config()} factory.
 *
 * Best-practice references:
 *  - NIST SP 800-63B — screen new passwords against known-compromised values.
 *  - OWASP ASVS — block common/breached passwords; prefer length over composition.
 *  - HIBP Pwned Passwords range API — k-anonymity: only the first five hex
 *    characters of the SHA-1 hash ever leave the process; the suffix is matched
 *    locally. The plaintext password is NEVER transmitted. The check FAILS OPEN
 *    on any non-200 / timeout / transport error so the API can never block a sign-up.
 *
 * @see https://haveibeenpwned.com/API/v3#PwnedPasswords
 * @see https://pages.nist.gov/800-63-3/sp800-63b.html
 */
final class RejectCommonPasswords implements ValidationRule
{
    /** Endpoint for the HIBP Pwned Passwords k-anonymity range API. */
    private const HIBP_RANGE_ENDPOINT = 'https://api.pwnedpasswords.com/range/';

    /** Network timeout (seconds) for the HIBP request — short, so we fail open quickly. */
    private const HIBP_TIMEOUT_SECONDS = 3;

    /**
     * Process-wide cache of the loaded denylist (loaded once per process).
     *
     * @var list<string>|null
     */
    private static ?array $commonPasswords = null;

    /**
     * @param int         $minLength      Minimum length gate (0 = off).
     * @param int         $minEntropy     Minimum Shannon-entropy gate in bits (0 = off).
     * @param bool        $checkHibp      Enable the opt-in HIBP k-anonymity breach check.
     * @param string|null $hibpApiKey     Optional HIBP API key (sent as `hibp-api-key`; not needed for the range API).
     * @param int         $minZxcvbnScore Minimum zxcvbn strength score 0–4 (0 = off; skipped when zxcvbn is absent).
     *
     * @throws \InvalidArgumentException when `$minZxcvbnScore` is outside 0–4
     */
    public function __construct(
        private readonly int $minLength = 0,
        private readonly int $minEntropy = 0,
        private readonly bool $checkHibp = false,
        private readonly ?string $hibpApiKey = null,
        private readonly int $minZxcvbnScore = 0,
    ) {
        if ($minZxcvbnScore < 0 || $minZxcvbnScore > 4) {
            throw new \InvalidArgumentException(
                "minZxcvbnScore must be between 0 and 4, got [{$minZxcvbnScore}].",
            );
        }
    }

    /**
     * Fluent factory entry point: `RejectCommonPasswords::config()->minLength(12)->rule()`.
     */
    public static function config(): RejectCommonPasswordsBuilder
    {
        return new RejectCommonPasswordsBuilder();
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return; // Let other validation rules handle non-string values.
        }

        $trimmed = trim($value);
        $normalised = Str::lower($trimmed);

        if (in_array($normalised, self::commonPasswords(), true)) {
            $fail('The :attribute contains a common password that is not allowed.');

            return;
        }

        if ($this->minLength > 0 && Str::length($trimmed) < $this->minLength) {
            $fail("The :attribute must be at least {$this->minLength} characters long.");

            return;
        }

        if ($this->minEntropy > 0 && self::entropyBits($trimmed) < (float) $this->minEntropy) {
            $fail('The :attribute is too weak; choose a longer or more varied password.');

            return;
        }

        if ($this->checkHibp && $this->isPwned($trimmed)) {
            $fail('The :attribute has appeared in a known data breach and cannot be used.');

            return;
        }

        if ($this->minZxcvbnScore > 0 && class_exists(Zxcvbn::class)) {
            /**
             * @var array{score: int, feedback: array{warning: string, suggestions: list<string>}} $result
             */
            $result = (new Zxcvbn())->passwordStrength($trimmed);

            if ($result['score'] < $this->minZxcvbnScore) {
                $fail($this->strengthMessage($result['feedback']));
            }
        }
    }

    /**
     * Build a human-readable failure message from the zxcvbn feedback, folding in
     * the warning and any suggestions when present.
     *
     * @param array{warning: string, suggestions: list<string>} $feedback
     */
    private function strengthMessage(array $feedback): string
    {
        $message = 'The :attribute is too weak.';

        if ($feedback['warning'] !== '') {
            $message .= ' ' . rtrim($feedback['warning'], '.') . '.';
        }

        if ($feedback['suggestions'] !== []) {
            $message .= ' ' . implode(' ', $feedback['suggestions']);
        }

        return $message;
    }

    /**
     * Load (once per process) and return the lowercased, deduplicated denylist.
     *
     * @return list<string>
     */
    private static function commonPasswords(): array
    {
        return self::$commonPasswords ??= SecurityData::commonPasswords();
    }

    /**
     * Shannon-style entropy estimate in bits: `length * log2(charsetSize)`, where
     * the charset size is the sum of the character-class pool sizes that appear in
     * the password (lowercase 26, uppercase 26, digits 10, the rest 33).
     */
    private static function entropyBits(string $password): float
    {
        if ($password === '') {
            return 0.0;
        }

        $pool = 0;
        if (preg_match('/[a-z]/', $password) === 1) {
            $pool += 26;
        }
        if (preg_match('/[A-Z]/', $password) === 1) {
            $pool += 26;
        }
        if (preg_match('/[0-9]/', $password) === 1) {
            $pool += 10;
        }
        if (preg_match('/[^a-zA-Z0-9]/', $password) === 1) {
            $pool += 33; // printable ASCII symbols / everything else.
        }

        if ($pool <= 1) {
            return 0.0;
        }

        return Str::length($password) * log($pool, 2);
    }

    /**
     * HIBP k-anonymity breach check. SHA-1 the password locally, send ONLY the
     * first five hex characters of the hash to the range API, then match the
     * 35-char suffix locally. The plaintext password is never transmitted.
     *
     * FAILS OPEN: any non-200 response, timeout, rate limit, or transport error
     * returns false (treated as not-pwned) so the API can never block a sign-up.
     */
    private function isPwned(string $password): bool
    {
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        try {
            $request = Http::timeout(self::HIBP_TIMEOUT_SECONDS)
                ->withHeaders(['Add-Padding' => 'true']);

            if ($this->hibpApiKey !== null && $this->hibpApiKey !== '') {
                $request = $request->withHeaders(['hibp-api-key' => $this->hibpApiKey]);
            }

            $response = $request->get(self::HIBP_RANGE_ENDPOINT . $prefix);
        } catch (Throwable) {
            return false; // Transport error / timeout -> fail open.
        }

        if (!$response->successful()) {
            return false; // Non-200 (429, 5xx, ...) -> fail open.
        }

        $lines = preg_split('/\R/', $response->body());
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $candidate = substr($line, 0, 35);
            if (strcasecmp($candidate, $suffix) === 0) {
                $count = (int) substr($line, 36);

                return $count > 0; // Padded entries report count 0 and are ignored.
            }
        }

        return false;
    }
}

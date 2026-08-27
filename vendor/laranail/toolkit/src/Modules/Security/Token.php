<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Security;

use InvalidArgumentException;
use LogicException;

/**
 * Fluent, immutable secure-token & one-time-code generator.
 *
 * A native-CSPRNG builder for opaque API keys, password-reset / verification
 * tokens, CSRF nonces and numeric OTP codes. Every chain method returns a fresh
 * instance (clone-and-mutate via {@see with()}), so a configured builder is
 * reusable. All randomness comes from {@see random_bytes()} / {@see random_int()};
 * signing/verification uses {@see hash_hmac()} (SHA-256) compared in constant
 * time with {@see hash_equals()}. No `rand`/`mt_rand`/`uniqid`/`Str::random`
 * touches the security core.
 *
 * ## Token format
 *
 * An **unsigned** token is just the encoded random body, optionally prefixed:
 *
 * ```
 *   [prefix] . encoded
 * ```
 *
 * A **signed** token appends an HMAC tag (and, when a {@see type()} is set, the
 * type segment that is covered by the signature), dot-joined:
 *
 * ```
 *   prefix . encoded [ . type ] . hmac
 * ```
 *
 * - `prefix`  — an optional Stripe-style identifier (e.g. `sk_live_`); it is part
 *   of the signed body so a token cannot be re-prefixed without breaking the MAC.
 * - `encoded` — the random body, rendered in the chosen {@see encoding()}.
 * - `type`    — the optional {@see type()} label (e.g. `reset`), signed alongside
 *   the body so a token minted for one purpose cannot be replayed as another.
 * - `hmac`    — base64url(`hash_hmac('sha256', signedBody, secret, raw)`) where
 *   `signedBody = prefix . encoded [ . expiry ] [ . type ]`.
 *
 * {@see verify()} re-derives the HMAC over the exact same `signedBody` and
 * compares with {@see hash_equals()} (constant time); on mismatch, on a tampered
 * prefix/body/type, or on an elapsed expiry it returns `false`. base64url is
 * RFC 4648 §5 URL-safe with no `=` padding.
 *
 * @see https://cheatsheetseries.owasp.org/cheatsheets/Cryptographic_Storage_Cheat_Sheet.html
 * @see https://datatracker.ietf.org/doc/html/rfc4648
 */
final class Token implements \Stringable
{
    /** Hexadecimal — `[0-9a-f]`, 2 chars per byte. */
    public const ENCODING_HEX = 'hex';

    /** RFC 4648 §5 URL-safe base64, no padding — `[A-Za-z0-9_-]`. */
    public const ENCODING_BASE64URL = 'base64url';

    /** RFC 4648 §6 base32 (no padding), Crockford-free A-Z2-7 alphabet. */
    public const ENCODING_BASE32 = 'base32';

    /** Mixed-case alphanumerics — `[A-Za-z0-9]`. */
    public const ENCODING_ALPHANUM = 'alphanum';

    /** Decimal digits only — `[0-9]`; the OTP-friendly encoding. */
    public const ENCODING_NUMERIC = 'numeric';

    /** Standard RFC 4648 base32 alphabet (uppercase, no padding). */
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Mixed-case alphanumeric alphabet (62 symbols). */
    private const ALPHANUM_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    /** Lower / upper guards on the random body size, in bytes. */
    private const MIN_BYTES = 8;

    private const MAX_BYTES = 1024;

    private string $prefix = '';

    /** @var int<self::MIN_BYTES, self::MAX_BYTES> */
    private int $bytes = 32;

    private string $encoding = self::ENCODING_HEX;

    private int $expiresIn = 0;

    private string $type = '';

    /**
     * Use {@see unsigned()} / {@see signed()} entry points.
     */
    private function __construct(private ?string $secret) {}

    // --- Entry points --------------------------------------------------------

    /**
     * An opaque, unsigned token — pure CSPRNG output with no integrity tag.
     * {@see verify()} cannot be called on an unsigned builder (it throws).
     */
    public static function unsigned(): self
    {
        return new self(null);
    }

    /**
     * A self-verifying signed token: the encoded body (plus optional expiry and
     * {@see type()}) is authenticated with HMAC-SHA256 under `$secret`.
     *
     * @throws InvalidArgumentException when `$secret` is an empty string
     */
    public static function signed(string $secret): self
    {
        if ($secret === '') {
            throw new InvalidArgumentException('A signing secret must not be empty.');
        }

        return new self($secret);
    }

    // --- Chain (immutable) ---------------------------------------------------

    /** Prepend a Stripe-style identifier (e.g. `sk_live_`). Signed into the MAC. */
    public function prefix(string $prefix): self
    {
        return $this->with(fn (self $c) => $c->prefix = $prefix);
    }

    /**
     * Set the random body size in bytes (guarded to {@see MIN_BYTES}..{@see MAX_BYTES}).
     *
     * @throws InvalidArgumentException when `$bytes` is outside 8..1024
     */
    public function length(int $bytes): self
    {
        if ($bytes < self::MIN_BYTES || $bytes > self::MAX_BYTES) {
            throw new InvalidArgumentException(
                sprintf('length must be between %d and %d bytes, got [%d].', self::MIN_BYTES, self::MAX_BYTES, $bytes),
            );
        }

        return $this->with(fn (self $c) => $c->bytes = $bytes);
    }

    /**
     * Choose how the random body is rendered. `numeric` yields an OTP-style
     * decimal code; the rest are opaque token alphabets.
     *
     * @param self::ENCODING_* $encoding
     *
     * @throws InvalidArgumentException on an unknown encoding
     */
    public function encoding(string $encoding): self
    {
        $allowed = [
            self::ENCODING_HEX,
            self::ENCODING_BASE64URL,
            self::ENCODING_BASE32,
            self::ENCODING_ALPHANUM,
            self::ENCODING_NUMERIC,
        ];

        if (!in_array($encoding, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid encoding [%s]. Allowed: %s.', $encoding, implode(', ', $allowed)),
            );
        }

        return $this->with(fn (self $c) => $c->encoding = $encoding);
    }

    /**
     * Bind an expiry (seconds from {@see generate()} time) into a signed token.
     * Ignored on unsigned builders. `0` (default) means no expiry.
     *
     * @throws InvalidArgumentException when `$seconds` is negative
     */
    public function expiresIn(int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException("expiresIn must be >= 0, got [{$seconds}].");
        }

        return $this->with(fn (self $c) => $c->expiresIn = $seconds);
    }

    /**
     * Tag the token with a purpose label (e.g. `reset`, `verify`). Signed into
     * the MAC so a token minted for one type can't be replayed as another.
     */
    public function type(string $type): self
    {
        return $this->with(fn (self $c) => $c->type = $type);
    }

    // --- Terminals -----------------------------------------------------------

    /**
     * Generate the token. Unsigned builders return `prefix . encoded`; signed
     * builders append the HMAC tag (and the type segment) per the class format.
     */
    public function generate(): string
    {
        $encoded = $this->encode(random_bytes($this->bytes));

        $body = $this->prefix . $encoded;

        if ($this->secret === null) {
            return $body;
        }

        $expiry = $this->expiresIn > 0 ? (string) (time() + $this->expiresIn) : '';

        $signedBody = $this->buildSignedBody($body, $expiry);
        $mac = $this->sign($signedBody);

        $segments = [$signedBody];
        $segments[] = $mac;

        return implode('.', $segments);
    }

    /**
     * Verify a signed token: recompute the HMAC over its body and compare in
     * constant time, then check any embedded expiry. Returns `false` on any
     * tampering (prefix/body/type/expiry), a wrong secret, or an elapsed expiry.
     *
     * @throws LogicException when called on an {@see unsigned()} builder
     */
    public function verify(string $token): bool
    {
        if ($this->secret === null) {
            throw new LogicException('Cannot verify a token on an unsigned builder; use Token::signed().');
        }

        $lastDot = strrpos($token, '.');
        if ($lastDot === false) {
            return false;
        }

        $signedBody = substr($token, 0, $lastDot);
        $presentedMac = substr($token, $lastDot + 1);

        $expectedMac = $this->sign($signedBody);

        if (!hash_equals($expectedMac, $presentedMac)) {
            return false;
        }

        // Enforce the purpose binding: a validly-signed token minted for another
        // type() (or none) must not verify under this builder's configured type.
        // The MAC alone does not prevent this — it authenticates the token's own
        // type, not that it matches what the verifier expects.
        if ($this->type !== '' && !str_ends_with($signedBody, '.' . $this->type)) {
            return false;
        }

        return $this->expiryIsValid($signedBody);
    }

    /** Convenience: a {@see generate()}d token. */
    public function __toString(): string
    {
        return $this->generate();
    }

    // --- Internals -----------------------------------------------------------

    /**
     * Assemble the HMAC-covered body: `prefix . encoded [ . expiry ] [ . type ]`.
     * The order is fixed so {@see verify()} can recompute it deterministically.
     */
    private function buildSignedBody(string $body, string $expiry): string
    {
        $signedBody = $body;

        if ($expiry !== '') {
            $signedBody .= '.' . $expiry;
        }

        if ($this->type !== '') {
            $signedBody .= '.' . $this->type;
        }

        return $signedBody;
    }

    /**
     * Validate any expiry embedded in the token. The expiry is authoritative and
     * self-describing: it is enforced whenever the token carries one, regardless
     * of whether this (verifying) builder set {@see expiresIn()} — otherwise an
     * under-configured verifier would silently accept an expired token. A token
     * with no expiry segment never expires.
     */
    private function expiryIsValid(string $signedBody): bool
    {
        // Strip the matched type suffix so the expiry (when present) is the
        // trailing segment. (verify() has already confirmed this suffix.)
        if ($this->type !== '' && str_ends_with($signedBody, '.' . $this->type)) {
            $signedBody = substr($signedBody, 0, -\strlen('.' . $this->type));
        }

        $segments = explode('.', $signedBody);

        // The body carries no dots, so >1 segment means a trailing expiry. A
        // non-numeric trailing segment is part of the body, not an expiry.
        if (count($segments) < 2) {
            return true;
        }

        $expiry = end($segments);
        if (!ctype_digit($expiry)) {
            return true;
        }

        return time() <= (int) $expiry;
    }

    /** HMAC-SHA256 over the signed body, rendered base64url (URL-safe, no pad). */
    private function sign(string $signedBody): string
    {
        $raw = hash_hmac('sha256', $signedBody, (string) $this->secret, true);

        return self::base64UrlEncode($raw);
    }

    /** Render `$bytes` of entropy in the configured encoding. */
    private function encode(string $bytes): string
    {
        return match ($this->encoding) {
            self::ENCODING_HEX => bin2hex($bytes),
            self::ENCODING_BASE64URL => self::base64UrlEncode($bytes),
            self::ENCODING_BASE32 => self::base32Encode($bytes),
            self::ENCODING_ALPHANUM => $this->alphabetEncode($bytes, self::ALPHANUM_ALPHABET),
            self::ENCODING_NUMERIC => $this->numericEncode($bytes),
            default => bin2hex($bytes), // unreachable: encoding() guards the set.
        };
    }

    /**
     * Map raw bytes onto an arbitrary alphabet, one symbol per input byte via an
     * unbiased modulo: each byte is folded into the alphabet using {@see random_int()}
     * to redraw the (rare) high bytes that would skew the distribution.
     */
    private function alphabetEncode(string $bytes, string $alphabet): string
    {
        $size = strlen($alphabet);
        $out = '';
        $limit = intdiv(256, $size) * $size;

        $chunks = str_split($bytes);
        foreach ($chunks as $char) {
            $value = ord($char);
            // Reject bytes in the biased tail; redraw with the CSPRNG so the
            // mapping onto the alphabet stays uniform.
            if ($value >= $limit) {
                $value = random_int(0, $limit - 1);
            }

            $out .= $alphabet[$value % $size];
        }

        return $out;
    }

    /** Decimal OTP rendering: one digit per byte, unbiased over `[0-9]`. */
    private function numericEncode(string $bytes): string
    {
        return $this->alphabetEncode($bytes, '0123456789');
    }

    /** RFC 4648 §6 base32 (uppercase, no padding). */
    private static function base32Encode(string $bytes): string
    {
        $bits = '';
        $chunks = str_split($bytes);
        foreach ($chunks as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $group) {
            $group = str_pad($group, 5, '0', STR_PAD_RIGHT);
            $out .= self::BASE32_ALPHABET[(int) bindec($group)];
        }

        return $out;
    }

    /** RFC 4648 §5 URL-safe base64 with the `=` padding stripped. */
    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
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

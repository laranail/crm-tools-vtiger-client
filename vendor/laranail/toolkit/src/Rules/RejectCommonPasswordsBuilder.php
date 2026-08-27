<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Rules;

/**
 * Fluent factory for {@see RejectCommonPasswords}.
 *
 * Example:
 * ```php
 * $rule = RejectCommonPasswords::config()
 *     ->minLength(12)
 *     ->minEntropy(60)
 *     ->withHibp($apiKey)
 *     ->rule();
 * ```
 *
 * The builder is immutable: each method returns a fresh instance.
 */
final readonly class RejectCommonPasswordsBuilder
{
    /**
     * @param int         $minLength      Minimum length gate (0 = off).
     * @param int         $minEntropy     Minimum Shannon-entropy gate in bits (0 = off).
     * @param bool        $checkHibp      Enable the opt-in HIBP k-anonymity breach check.
     * @param string|null $hibpApiKey     Optional HIBP API key for the range request.
     * @param int         $minZxcvbnScore Minimum zxcvbn strength score 0–4 (0 = off).
     */
    public function __construct(
        private int $minLength = 0,
        private int $minEntropy = 0,
        private bool $checkHibp = false,
        private ?string $hibpApiKey = null,
        private int $minZxcvbnScore = 0,
    ) {}

    /** Require at least `$length` characters (0 disables the gate). */
    public function minLength(int $length): self
    {
        return new self($length, $this->minEntropy, $this->checkHibp, $this->hibpApiKey, $this->minZxcvbnScore);
    }

    /** Require at least `$bits` of estimated entropy (0 disables the gate). */
    public function minEntropy(int $bits): self
    {
        return new self($this->minLength, $bits, $this->checkHibp, $this->hibpApiKey, $this->minZxcvbnScore);
    }

    /** Enable the opt-in HIBP k-anonymity breach check (fail-open, offline by default). */
    public function withHibp(?string $apiKey = null): self
    {
        return new self($this->minLength, $this->minEntropy, true, $apiKey, $this->minZxcvbnScore);
    }

    /**
     * Require a minimum zxcvbn strength score 0–4 (0 disables the gate). Opt-in and
     * silently skipped when `bjeavons/zxcvbn-php` is not installed.
     *
     * @throws \InvalidArgumentException when `$score` is outside 0–4
     */
    public function minZxcvbnScore(int $score): self
    {
        if ($score < 0 || $score > 4) {
            throw new \InvalidArgumentException("minZxcvbnScore must be between 0 and 4, got [{$score}].");
        }

        return new self($this->minLength, $this->minEntropy, $this->checkHibp, $this->hibpApiKey, $score);
    }

    /** Build the configured {@see RejectCommonPasswords} rule. */
    public function rule(): RejectCommonPasswords
    {
        return new RejectCommonPasswords(
            $this->minLength,
            $this->minEntropy,
            $this->checkHibp,
            $this->hibpApiKey,
            $this->minZxcvbnScore,
        );
    }
}

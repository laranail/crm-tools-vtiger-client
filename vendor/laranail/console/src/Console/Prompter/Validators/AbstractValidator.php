<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

use Simtabi\Laranail\Console\Prompter\Contracts\ValidatorInterface;

/**
 * Abstract base class for validators.
 *
 * Constructors take only validator-specific (domain) arguments. The failure
 * message, translation replacements and locale are configured fluently after
 * construction — uniform across every validator — and the message is resolved
 * lazily (at {@see validate()} time) via {@see resolvedMessage()}.
 *
 *   new StringFieldValidator(0, 64)->errorMessage('Too long')->locale('fr');
 */
abstract class AbstractValidator implements ValidatorInterface
{
    private ?string $customMessage = null;

    /** @var array<string, string> */
    private array $replace = [];

    private ?string $locale = null;

    public function __construct(protected string $messageKey = '') {}

    /**
     * Override the failure message (otherwise the translated default is used).
     */
    public function errorMessage(string $message): static
    {
        $this->customMessage = $message;

        return $this;
    }

    /**
     * Placeholders to substitute into the translated default message.
     *
     * @param array<string, string> $replace
     */
    public function replace(array $replace): static
    {
        $this->replace = $replace;

        return $this;
    }

    /**
     * Resolve the default message in a specific locale (null = configured/app locale).
     */
    public function locale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * The failure message: the fluent override if set, else the translated default
     * for this validator's message key. Resolved lazily so the active locale and
     * any fluent config are honoured at validation time.
     */
    protected function resolvedMessage(): string
    {
        return $this->customMessage
            ?? __('laranail-console::validators.' . $this->messageKey, $this->replace, $this->locale ?? self::configuredLocale());
    }

    /**
     * The fluent override message, or null if none was set. For subclasses that
     * build their own default (e.g. {@see LaravelRule}) instead of a translation key.
     */
    protected function customMessageOrNull(): ?string
    {
        return $this->customMessage;
    }

    /**
     * The package's configured translation locale (`console.locale`), or null to
     * follow the application locale. Read defensively so validators also work
     * outside a booted application.
     */
    protected static function configuredLocale(): ?string
    {
        if (function_exists('app') && app()->bound('config')) {
            $locale = config('laranail.console.locale');

            return is_string($locale) ? $locale : null;
        }

        return null;
    }

    /**
     * Validate the given value.
     *
     * @return string|null The error message if validation fails, or null if it passes.
     */
    abstract public function validate(mixed $value): ?string;
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

use Simtabi\Laranail\Console\Tools\Exceptions\NonInteractiveException;
use Simtabi\Laranail\Console\Tools\Support\Config;

/**
 * Handles interactive user input for console commands via Laravel Prompts.
 *
 * In non-interactive mode, asking for a required value throws (configurable)
 * rather than silently returning an empty string — so a command can never
 * proceed with, say, an empty password collected from a pipe or CI.
 */
class CommandInteractionService
{
    protected bool $nonInteractive = false;

    public function setNonInteractive(bool $nonInteractive): self
    {
        $this->nonInteractive = $nonInteractive;

        return $this;
    }

    public function isNonInteractive(): bool
    {
        return $this->nonInteractive;
    }

    /**
     * Ask for text input.
     */
    public function askText(string $label, string $placeholder = '', string $default = '', bool $required = false): string
    {
        if ($this->nonInteractive) {
            if ($required && $default === '') {
                $this->failNonInteractive($label);
            }

            return $default;
        }

        return text(
            label: $label,
            placeholder: $placeholder,
            default: $default,
            required: $required,
        );
    }

    /**
     * Ask for a password. Never returns an empty secret silently in
     * non-interactive mode.
     */
    public function askPassword(string $label, string $placeholder = ''): string
    {
        if ($this->nonInteractive) {
            $this->failNonInteractive($label);

            return '';
        }

        return password(
            label: $label,
            placeholder: $placeholder,
        );
    }

    public function askConfirm(string $label, bool $default = false): bool
    {
        if ($this->nonInteractive) {
            return $default;
        }

        return confirm(label: $label, default: $default);
    }

    /**
     * @param array<int|string, string> $options
     */
    public function askSelect(string $label, array $options, int $default = 0): string
    {
        if ($this->nonInteractive) {
            return (string) (array_values($options)[$default] ?? '');
        }

        return (string) select(label: $label, options: $options, default: $default);
    }

    /**
     * @param array<int|string, string> $options
     * @param array<int, int|string> $default
     * @return array<int, int|string>
     */
    public function askMultiSelect(string $label, array $options, array $default = []): array
    {
        if ($this->nonInteractive) {
            return $default;
        }

        return multiselect(label: $label, options: $options, default: $default);
    }

    /**
     * Show a spinner while a callback runs.
     */
    public function showSpinner(string $message, callable $callback): mixed
    {
        if ($this->nonInteractive) {
            return $callback();
        }

        return spin(static fn () => $callback(), $message);
    }

    /**
     * Ask for input, re-prompting until a validator passes.
     */
    public function askWithValidation(string $question, ?callable $validator = null, mixed $default = null): mixed
    {
        if ($this->nonInteractive) {
            return $default;
        }

        do {
            $answer = $this->askText($question, default: (string) $default);

            if ($validator === null || $validator($answer)) {
                return $answer;
            }

            error(__('laranail-console::console.invalid_input'));
        } while (true);
    }

    public function confirmAction(string $question, bool $default = false): bool
    {
        if ($this->nonInteractive) {
            return $default;
        }

        return $this->askConfirm($question, $default);
    }

    public function showLoading(string $message, callable $callback): mixed
    {
        return $this->showSpinner($message, $callback);
    }

    /**
     * Either throw (when configured) or fall through for a required value that
     * cannot be collected without a TTY.
     */
    protected function failNonInteractive(string $label): void
    {
        if ($this->requiredThrows()) {
            throw NonInteractiveException::forValue($label);
        }
    }

    /**
     * Whether requesting a required value non-interactively should throw.
     * Defaults to true when no configuration container is available.
     */
    protected function requiredThrows(): bool
    {
        return (bool) Config::get('interaction.non_interactive_required_throws', true);
    }
}

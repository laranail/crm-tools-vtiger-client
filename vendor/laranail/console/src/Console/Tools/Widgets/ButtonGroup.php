<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use function Laravel\Prompts\select;

use Simtabi\Laranail\Console\Tools\Contracts\Interactive;

/**
 * An interactive single-choice control rendered as buttons. Selection is
 * delegated to laravel/prompts `select()` — arrow-key navigation in a TTY,
 * graceful non-interactive fallback, and fully fakeable in tests via
 * `\Laravel\Prompts\Prompt::fake()`.
 */
final class ButtonGroup implements Interactive
{
    /**
     * @param array<int|string, string>|list<string> $options
     */
    public function __construct(private array $options = []) {}

    /**
     * @param array<int|string, string>|list<string> $options
     */
    public static function make(array $options = []): self
    {
        return new self($options);
    }

    public function add(int|string $value, ?string $label = null): self
    {
        if ($label === null) {
            $this->options[] = (string) $value;
        } else {
            $this->options[$value] = $label;
        }

        return $this;
    }

    /**
     * Prompt the user to pick one option, returning its key (or value for a list).
     */
    public function prompt(string $label = 'Select', int|string|null $default = null): int|string
    {
        $default ??= array_key_first($this->options);

        return select(label: $label, options: $this->options, default: $default);
    }
}

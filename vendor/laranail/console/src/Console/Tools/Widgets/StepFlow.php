<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Stringable;

/**
 * A wizard/pipeline breadcrumb showing done / current / pending steps, e.g.
 * `✓ Detect → ● Plan → ○ Apply → ○ Verify`.
 */
final class StepFlow implements Stringable
{
    /** @var list<string> */
    private array $steps;

    private int $current = 0;

    private readonly Symbols $symbols;

    private readonly bool $unicode;

    /**
     * @param list<string> $steps
     */
    public function __construct(array $steps = [], ?Capabilities $capabilities = null)
    {
        $capabilities ??= Capabilities::detect();
        $this->symbols = Symbols::for($capabilities);
        $this->unicode = $capabilities->supportsUnicode();
        $this->steps = array_map(ConsoleUIFormatter::sanitizeText(...), $steps);
    }

    /**
     * @param list<string> $steps
     */
    public static function make(array $steps = []): self
    {
        return new self($steps);
    }

    public function step(string $label): self
    {
        $this->steps[] = ConsoleUIFormatter::sanitizeText($label);

        return $this;
    }

    /**
     * Mark the zero-based index of the current (in-progress) step.
     */
    public function current(int $index): self
    {
        $this->current = $index;

        return $this;
    }

    public function render(): string
    {
        $separator = ' ' . $this->symbols->get('arrow') . ' ';
        $rendered = [];

        foreach ($this->steps as $i => $label) {
            $glyph = match (true) {
                $i < $this->current => $this->symbols->get('success'),
                $i === $this->current => $this->unicode ? '●' : '[*]',
                default => $this->symbols->get('pending'),
            };

            $rendered[] = $glyph . ' ' . $label;
        }

        return implode($separator, $rendered);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

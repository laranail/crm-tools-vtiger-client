<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

use Simtabi\Laranail\Console\Tools\Contracts\Interactive;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Stringable;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A native live-render engine: redraws any {@see Renderable} in place via a
 * Symfony console section. TTY-guarded — in a non-interactive terminal (pipe/CI)
 * it emits a single static render with no cursor-control sequences, so logs stay
 * clean. The shared engine behind animated bars, spinners and pulsing badges.
 */
final class Live implements Interactive
{
    private readonly OutputInterface $output;

    private readonly Capabilities $capabilities;

    private ?ConsoleSectionOutput $section = null;

    public function __construct(?OutputInterface $output = null, ?Capabilities $capabilities = null)
    {
        $this->output = $output ?? new ConsoleOutput;
        $this->capabilities = $capabilities ?? Capabilities::detect();
    }

    public static function make(?OutputInterface $output = null): self
    {
        return new self($output);
    }

    /**
     * Draw content once (overwriting the live region in a TTY; a plain write
     * otherwise).
     */
    public function draw(Renderable|Stringable|string $content): self
    {
        $text = implode("\n", $this->lines($content));

        if ($this->capabilities->isInteractive() && $this->output instanceof ConsoleOutputInterface) {
            $this->section ??= $this->output->section();
            $this->section->overwrite($text);
        } else {
            $this->output->writeln($text);
        }

        return $this;
    }

    /**
     * Redraw the live region $steps times, calling $producer($step) for each frame.
     * Non-interactive: draws only the final frame, once.
     *
     * @param callable(int):(Renderable|Stringable|string) $producer
     */
    public function refresh(callable $producer, int $steps, int $intervalMs = 80): self
    {
        $steps = max($steps, 1);

        if (! $this->canAnimate()) {
            return $this->draw($producer($steps - 1));
        }

        for ($i = 0; $i < $steps; $i++) {
            $this->draw($producer($i));

            if ($intervalMs > 0 && $i < $steps - 1) {
                usleep($intervalMs * 1000);
            }
        }

        return $this;
    }

    /**
     * Cycle through pre-rendered frames.
     *
     * @param list<Renderable|Stringable|string> $frames
     */
    public function animate(array $frames, int $loops = 1, int $intervalMs = 80): self
    {
        if ($frames === []) {
            return $this;
        }

        $sequence = [];
        for ($l = 0; $l < max($loops, 1); $l++) {
            $sequence = [...$sequence, ...$frames];
        }

        return $this->refresh(static fn (int $i): Renderable|Stringable|string => $sequence[$i], count($sequence), $intervalMs);
    }

    private function canAnimate(): bool
    {
        return $this->capabilities->isInteractive() && $this->output instanceof ConsoleOutputInterface;
    }

    /**
     * @return list<string>
     */
    private function lines(Renderable|Stringable|string $content): array
    {
        if ($content instanceof Renderable) {
            return $content->renderLines();
        }

        return explode("\n", (string) $content);
    }
}

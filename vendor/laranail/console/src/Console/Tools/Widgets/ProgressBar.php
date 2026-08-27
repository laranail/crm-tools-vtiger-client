<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Enums\ProgressStyle;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Config;
use Simtabi\Laranail\Console\Tools\Support\TimeFormat;
use Symfony\Component\Console\Helper\ProgressBar as SymfonyProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A flavoured progress bar over Symfony's ProgressBar.
 *
 * Adds layout presets (percent / count / elapsed / ETA / rate), glyph styles
 * (Unicode blocks, ASCII, dots, arrows, gradient) with automatic ASCII
 * fallback, a transfer-rate placeholder, and three-tier time formatting for the
 * elapsed and ETA fields.
 */
final readonly class ProgressBar
{
    /** @var array<string, array{0:string,1:string,2:string}> bar, empty, progress */
    private const array GLYPHS = [
        'blocks' => ['█', '░', '█'],
        'ascii' => ['#', '-', '>'],
        'dots' => ['●', '○', '●'],
        'arrows' => ['►', '▷', '►'],
        'gradient' => ['▓', '░', '▒'],
    ];

    private SymfonyProgressBar $bar;

    private Capabilities $capabilities;

    public function __construct(OutputInterface $output, int $max = 0, ?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->bar = new SymfonyProgressBar($output, $max);

        $this->registerPlaceholders();
        $this->format((string) Config::get('progress.format', 'detailed'));
        $this->glyphs((string) Config::get('progress.glyphs', 'blocks'));
    }

    public static function make(?OutputInterface $output = null, int $max = 0): self
    {
        return new self($output ?? new ConsoleOutput, $max);
    }

    public function format(ProgressStyle|string $style): self
    {
        $style = $style instanceof ProgressStyle ? $style : ProgressStyle::fromName($style);
        $this->bar->setFormat($style->format());

        return $this;
    }

    /**
     * Apply a named glyph style, falling back to ASCII without Unicode support.
     */
    public function glyphs(string $style): self
    {
        if (! $this->capabilities->supportsUnicode()) {
            $style = 'ascii';
        }

        [$bar, $empty, $progress] = self::GLYPHS[$style] ?? self::GLYPHS['blocks'];

        $this->bar->setBarCharacter($bar);
        $this->bar->setEmptyBarCharacter($empty);
        $this->bar->setProgressCharacter($progress);

        return $this;
    }

    public function start(?int $max = null): self
    {
        $this->bar->start($max);

        return $this;
    }

    public function advance(int $step = 1): self
    {
        $this->bar->advance($step);

        return $this;
    }

    public function setProgress(int $step): self
    {
        $this->bar->setProgress($step);

        return $this;
    }

    public function finish(): self
    {
        $this->bar->finish();

        return $this;
    }

    public function raw(): SymfonyProgressBar
    {
        return $this->bar;
    }

    /**
     * Register the rate/elapsed/estimated placeholders on THIS bar only.
     *
     * Instance scoping (vs the static setPlaceholderFormatterDefinition) is
     * deliberate: it must not mutate the process-wide defaults that every other
     * Symfony progress bar in the host app relies on.
     */
    private function registerPlaceholders(): void
    {
        $this->bar->setPlaceholderFormatter('rate', static function (SymfonyProgressBar $bar): string {
            $elapsed = max(microtime(true) - (float) $bar->getStartTime(), 0.0001);

            return number_format($bar->getProgress() / $elapsed, 1, '.', '');
        });

        $this->bar->setPlaceholderFormatter('elapsed', static fn (SymfonyProgressBar $bar): string => TimeFormat::duration(microtime(true) - (float) $bar->getStartTime()));

        $this->bar->setPlaceholderFormatter('estimated', static function (SymfonyProgressBar $bar): string {
            if ($bar->getMaxSteps() === 0 || $bar->getProgress() === 0) {
                return '∞';
            }

            $elapsed = microtime(true) - (float) $bar->getStartTime();
            $estimated = $elapsed / $bar->getProgress() * $bar->getMaxSteps();

            return TimeFormat::duration($estimated - $elapsed);
        });
    }
}

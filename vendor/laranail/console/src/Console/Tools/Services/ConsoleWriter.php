<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Services;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Emoji;
use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Simtabi\Laranail\Console\Tools\Widgets\StatusLine;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A fluent, immutable wrapper over a Symfony {@see OutputInterface}.
 *
 * Every styling/config method returns a new instance (clone), so a configured
 * writer can be reused safely. On top of raw styling it offers ready-to-use
 * **context statuses** (`success()`/`error()`/`warning()`/`info()`/`note()`/
 * `danger()`/`pending()`) rendered through {@see StatusLine} (coloured glyph +
 * message; `error`/`danger` go to stderr), and **emoji/symbol** support via
 * {@see Emoji} and {@see Symbols} plus inline `:shortcode:` rendering.
 *
 * @api
 */
class ConsoleWriter
{
    private ?string $style = null;

    private ?string $foreground = null;

    private ?string $background = null;

    /** @var string[] */
    private array $options = [];

    private bool $escape = false;

    /** @var int-mask-of<OutputInterface::OUTPUT_*|OutputInterface::VERBOSITY_*> */
    private int $verbosity = OutputInterface::VERBOSITY_NORMAL;

    private bool $stderr = false;

    private ?Capabilities $capabilities = null;

    private ?string $prefix = null;

    private bool $renderEmoji = true;

    public function __construct(private OutputInterface $output) {}

    public static function make(OutputInterface $output): self
    {
        return new self($output);
    }

    private function with(callable $mutate): self
    {
        $clone = clone $this;
        $mutate($clone);

        return $clone;
    }

    // getters

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function getForeground(): ?string
    {
        return $this->foreground;
    }

    public function getBackground(): ?string
    {
        return $this->background;
    }

    /** @return string[] */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** @return int-mask-of<OutputInterface::OUTPUT_*|OutputInterface::VERBOSITY_*> */
    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    // styling, each returns a new instance

    public function output(OutputInterface $output): self
    {
        return $this->with(function (self $w) use ($output): void {
            $w->output = $output;
        });
    }

    public function style(?string $style): self
    {
        return $this->with(function (self $w) use ($style): void {
            $w->style = $style;
            $w->foreground = null;
            $w->background = null;
        });
    }

    public function color(string $color): self
    {
        return $this->foreground($color);
    }

    public function foreground(?string $color): self
    {
        return $this->with(function (self $w) use ($color): void {
            $w->foreground = $color;
            $w->style = null;
        });
    }

    public function background(?string $color): self
    {
        return $this->with(function (self $w) use ($color): void {
            $w->background = $color;
            $w->style = null;
        });
    }

    public function options(string ...$options): self
    {
        return $this->with(function (self $w) use ($options): void {
            $w->options = array_values(array_unique([...$w->options, ...$options]));
        });
    }

    public function bold(): self
    {
        return $this->options('bold');
    }

    public function underline(): self
    {
        return $this->options('underscore');
    }

    public function escaped(bool $escape = true): self
    {
        return $this->with(function (self $w) use ($escape): void {
            $w->escape = $escape;
        });
    }

    /**
     * @param int-mask-of<OutputInterface::OUTPUT_*|OutputInterface::VERBOSITY_*> $level
     */
    public function verbosity(int $level): self
    {
        return $this->with(function (self $w) use ($level): void {
            $w->verbosity = $level;
        });
    }

    public function toStderr(bool $stderr = true): self
    {
        return $this->with(function (self $w) use ($stderr): void {
            $w->stderr = $stderr;
        });
    }

    public function plain(): self
    {
        return $this->with(function (self $w): void {
            $w->style = null;
            $w->foreground = null;
            $w->background = null;
            $w->options = [];
        });
    }

    // emoji / symbol prefix

    public function capabilities(Capabilities $capabilities): self
    {
        return $this->with(function (self $w) use ($capabilities): void {
            $w->capabilities = $capabilities;
        });
    }

    /**
     * Set a leading emoji. Accepts an Emoji name or `:shortcode:` (resolved via
     * {@see Emoji} for the active capabilities) or a literal string (passed through).
     */
    public function emoji(string $emoji): self
    {
        $resolved = Emoji::make($this->caps())->render(
            str_contains($emoji, ':') ? $emoji : ':' . $emoji . ':'
        );

        // Unknown name: render() leaves an unmatched ":name:" intact — fall back to the literal.
        return $this->prefix($resolved === ':' . trim($emoji, ':') . ':' ? $emoji : $resolved);
    }

    /** Set a leading glyph from the shared {@see Symbols} map (e.g. "arrow", "package"). */
    public function symbol(string $name): self
    {
        return $this->prefix(Symbols::for($this->caps())->get($name));
    }

    /** Set a raw leading prefix prepended to written text. Null clears it. */
    public function prefix(?string $prefix): self
    {
        return $this->with(function (self $w) use ($prefix): void {
            $w->prefix = ($prefix === null || $prefix === '') ? null : $prefix;
        });
    }

    /** Toggle inline `:shortcode:` emoji rendering inside written text (on by default). */
    public function emojis(bool $enabled = true): self
    {
        return $this->with(function (self $w) use ($enabled): void {
            $w->renderEmoji = $enabled;
        });
    }

    // context statuses (coloured glyph + message via StatusLine)

    public function success(string ...$lines): self
    {
        return $this->status('success', $lines);
    }

    public function error(string ...$lines): self
    {
        return $this->status('error', $lines);
    }

    public function danger(string ...$lines): self
    {
        return $this->status('danger', $lines);
    }

    public function warning(string ...$lines): self
    {
        return $this->status('warning', $lines);
    }

    public function info(string ...$lines): self
    {
        return $this->status('info', $lines);
    }

    public function note(string ...$lines): self
    {
        return $this->status('note', $lines);
    }

    public function pending(string ...$lines): self
    {
        return $this->status('pending', $lines);
    }

    // raw Symfony output styles (use ->style('info')->line(...) for arbitrary styles)

    public function comment(string ...$lines): self
    {
        return $this->style('comment')->lines(...$lines);
    }

    public function question(string ...$lines): self
    {
        return $this->style('question')->lines(...$lines);
    }

    public function when(bool $condition, callable $then, ?callable $else = null): self
    {
        if ($condition) {
            $result = $then($this);

            return $result instanceof self ? $result : $this;
        }

        if ($else !== null) {
            $result = $else($this);

            return $result instanceof self ? $result : $this;
        }

        return $this;
    }

    // output

    public function line(string $line): self
    {
        $this->stream()->writeln($this->format($this->decorate($line)), $this->verbosity);

        return $this;
    }

    public function lines(string ...$lines): self
    {
        foreach ($lines as $line) {
            $this->line($line);
        }

        return $this;
    }

    public function write(string $text): self
    {
        $this->stream()->write($this->format($this->decorate($text)), false, $this->verbosity);

        return $this;
    }

    public function newLine(int $count = 1): self
    {
        $this->stream()->write(str_repeat(PHP_EOL, max(1, $count)), false, $this->verbosity);

        return $this;
    }

    // wrap text in the active style and return it without writing
    public function format(string $text): string
    {
        if ($this->escape) {
            $text = OutputFormatter::escape($text);
        }

        if ($this->foreground !== null || $this->background !== null || $this->options !== []) {
            return sprintf('<%s>%s</>', $this->inlineTag(), $text);
        }

        if ($this->style !== null) {
            return sprintf('<%s>%s</%s>', $this->style, $text, $this->style);
        }

        return $text;
    }

    // internals

    /**
     * Render each line as a coloured glyph + message via {@see StatusLine}.
     * `error`/`danger` are routed to stderr. Status markup is already styled,
     * so it is written without the writer's active inline style.
     *
     * @param string[] $lines
     */
    private function status(string $status, array $lines): self
    {
        $forceErr = $status === 'error' || $status === 'danger';
        $stream = ($forceErr || $this->stderr) && $this->output instanceof ConsoleOutputInterface
            ? $this->output->getErrorOutput()
            : $this->output;

        $statusLine = StatusLine::make($this->caps());

        foreach ($lines as $line) {
            $stream->writeln($statusLine->line($status, $this->decorate($line)), $this->verbosity);
        }

        return $this;
    }

    // render inline :shortcode: emoji then prepend the active prefix
    private function decorate(string $text): string
    {
        if ($this->renderEmoji) {
            $text = Emoji::make($this->caps())->render($text);
        }

        if ($this->prefix !== null) {
            return $this->prefix . ' ' . $text;
        }

        return $text;
    }

    private function caps(): Capabilities
    {
        return $this->capabilities ?? Capabilities::detect();
    }

    private function stream(): OutputInterface
    {
        if ($this->stderr && $this->output instanceof ConsoleOutputInterface) {
            return $this->output->getErrorOutput();
        }

        return $this->output;
    }

    private function formatter(): OutputFormatterInterface
    {
        return $this->stream()->getFormatter();
    }

    private function inlineTag(): string
    {
        $parts = [];

        if ($this->foreground !== null) {
            $parts[] = 'fg=' . $this->foreground;
        }

        if ($this->background !== null) {
            $parts[] = 'bg=' . $this->background;
        }

        if ($this->options !== []) {
            $parts[] = 'options=' . implode(',', $this->options);
        }

        $name = 'cw_' . substr(md5(implode(';', $parts)), 0, 12);
        $formatter = $this->formatter();

        if (! $formatter->hasStyle($name)) {
            $formatter->setStyle($name, new OutputFormatterStyle(
                $this->foreground,
                $this->background,
                $this->options,
            ));
        }

        return $name;
    }
}

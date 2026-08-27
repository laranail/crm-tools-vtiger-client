<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

use Simtabi\Laranail\Console\Tools\Enums\ControlChars;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Low-level terminal control: bell, tab title, alternate-screen buffer, and
 * cursor/erase movement. Sequences are built from our own {@see Csi} /
 * {@see ControlChars} primitives (re-derived from ECMA-48) and written through an
 * {@see OutputInterface} (raw, so markup is never parsed).
 */
final readonly class Terminal
{
    public function __construct(private OutputInterface $output = new ConsoleOutput) {}

    public static function make(?OutputInterface $output = null): self
    {
        return new self($output ?? new ConsoleOutput);
    }

    /**
     * Ring the terminal bell.
     */
    public function bell(): self
    {
        return $this->emit(ControlChars::Bel->char());
    }

    /**
     * Set the terminal tab/window title (OSC 0).
     */
    public function tabTitle(string $title): self
    {
        $title = ConsoleUIFormatter::sanitizeText($title);

        return $this->emit(ControlChars::Esc->char() . ']0;' . $title . ControlChars::Bel->char());
    }

    public function restoreTabTitle(): self
    {
        return $this->tabTitle('Terminal');
    }

    /**
     * Switch to / from the alternate screen buffer (full-screen apps).
     */
    public function altScreen(bool $enabled = true): self
    {
        return $this->emit(Csi::sequence($enabled ? 'h' : 'l', '?1049'));
    }

    public function hideCursor(): self
    {
        return $this->emit(Csi::sequence('l', '?25'));
    }

    public function showCursor(): self
    {
        return $this->emit(Csi::sequence('h', '?25'));
    }

    /**
     * Move the cursor to an absolute (row, col), 1-based.
     */
    public function moveCursor(int $row, int $col): self
    {
        return $this->emit(Csi::sequence('H', max($row, 1), max($col, 1)));
    }

    /**
     * Clear the whole screen.
     */
    public function clear(): self
    {
        return $this->emit(Csi::sequence('J', 2));
    }

    /**
     * Clear the current line.
     */
    public function clearLine(): self
    {
        return $this->emit(Csi::sequence('K', 2));
    }

    private function emit(string $sequence): self
    {
        $this->output->write($sequence, false, OutputInterface::OUTPUT_RAW);

        return $this;
    }
}

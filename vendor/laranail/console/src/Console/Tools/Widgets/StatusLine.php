<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Symbols;

/**
 * One-line status messages with a coloured glyph prefix.
 *
 * Glyphs come from the shared {@see Symbols} map (Unicode or ASCII per
 * {@see Capabilities}). The returned string carries Symfony Console **markup**
 * (e.g. `<fg=green>…</>`), so write it through an output (`$output->writeln()`
 * or a command's `$this->line()`) to render — colour then resolves on a TTY and
 * is stripped when piped. Echoing it directly prints the literal tags.
 */
final readonly class StatusLine
{
    /** @var array<string, string> status => formatter colour */
    private const array COLORS = [
        'success' => 'green',
        'error' => 'red',
        'danger' => 'red',
        'warning' => 'yellow',
        'info' => 'blue',
        'note' => 'cyan',
        'pending' => 'gray',
    ];

    private Symbols $symbols;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->symbols = Symbols::for($capabilities ?? Capabilities::detect());
    }

    public static function make(?Capabilities $capabilities = null): self
    {
        return new self($capabilities);
    }

    public function success(string $message): string
    {
        return $this->line('success', $message);
    }

    public function error(string $message): string
    {
        return $this->line('error', $message);
    }

    public function warning(string $message): string
    {
        return $this->line('warning', $message);
    }

    public function info(string $message): string
    {
        return $this->line('info', $message);
    }

    public function pending(string $message): string
    {
        return $this->line('pending', $message);
    }

    /**
     * Render a status line for an arbitrary known status.
     */
    public function line(string $status, string $message): string
    {
        $glyph = $this->symbols->get($status);
        $color = self::COLORS[$status] ?? 'white';
        $message = ConsoleUIFormatter::sanitizeText($message);

        return ConsoleUIFormatter::create()
            ->addMessage($glyph . ' ' . $message)
            ->addTextColor($color)
            ->render();
    }
}

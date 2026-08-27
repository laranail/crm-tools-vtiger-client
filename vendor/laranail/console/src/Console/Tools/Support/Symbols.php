<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * A single source of glyphs (status icons, tree connectors) with Unicode and
 * ASCII variants. The variant is chosen once from {@see Capabilities} so the
 * rest of the package never sprinkles conditional "is Unicode supported?" checks.
 */
final readonly class Symbols
{
    private const array FANCY = [
        'success' => '✓',
        'error' => '✗',
        'danger' => '✖',
        'warning' => '⚠',
        'info' => 'ℹ',
        'note' => '●',
        'pending' => '○',
        'running' => '◉',
        'skipped' => '⊘',
        'package' => '📦',
        'bullet' => '•',
        'arrow' => '→',
        'branch' => '├─',
        'last' => '└─',
        'stem' => '│ ',
        'pipe' => '│',
        'gap' => '  ',
    ];

    private const array ASCII = [
        'success' => '[OK]',
        'error' => '[X]',
        'danger' => '[!!]',
        'warning' => '[!]',
        'info' => '[i]',
        'note' => '[note]',
        'pending' => '[ ]',
        'running' => '[*]',
        'skipped' => '[-]',
        'package' => '*',
        'bullet' => '*',
        'arrow' => '->',
        'branch' => '|-',
        'last' => '\\-',
        'stem' => '| ',
        'pipe' => '|',
        'gap' => '  ',
    ];

    public function __construct(private bool $unicode) {}

    public static function for(Capabilities $capabilities): self
    {
        return new self($capabilities->symbolMode() === 'fancy');
    }

    public static function fancy(): self
    {
        return new self(true);
    }

    public static function ascii(): self
    {
        return new self(false);
    }

    public function get(string $name): string
    {
        $set = $this->unicode ? self::FANCY : self::ASCII;

        return $set[$name] ?? '';
    }
}

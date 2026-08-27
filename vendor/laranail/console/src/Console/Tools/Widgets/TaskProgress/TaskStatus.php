<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\TaskProgress;

/**
 * Lifecycle states for a tracked task, each with a Unicode and ASCII glyph.
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Paused = 'paused';
    case Warning = 'warning';
    case Cancelled = 'cancelled';

    public function glyph(bool $unicode = true): string
    {
        return match ($this) {
            self::Pending => $unicode ? '○' : '[ ]',
            self::Running => $unicode ? '◉' : '[*]',
            self::Success => $unicode ? '✓' : '[OK]',
            self::Failed => $unicode ? '✗' : '[X]',
            self::Skipped => $unicode ? '⊘' : '[-]',
            self::Paused => $unicode ? '⏸' : '[=]',
            self::Warning => $unicode ? '⚠' : '[!]',
            self::Cancelled => $unicode ? '⊗' : '[c]',
        };
    }
}

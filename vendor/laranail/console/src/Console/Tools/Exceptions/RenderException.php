<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Exceptions;

use Simtabi\Laranail\Console\Exceptions\ConsoleException;

/**
 * Thrown when a widget cannot render the given input.
 */
final class RenderException extends ConsoleException
{
    public static function forWidget(string $widget, string $reason): self
    {
        return self::fromKey('console.render_failed', ['widget' => $widget, 'reason' => $reason]);
    }
}

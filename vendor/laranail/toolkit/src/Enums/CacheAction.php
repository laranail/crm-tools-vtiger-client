<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Enums;

use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;

/**
 * The lifecycle phase a {@see CacheEvents} instance represents.
 */
enum CacheAction: string
{
    case Clearing = 'clearing';
    case Cleared = 'cleared';
    case Failed = 'failed';

    /** Human-friendly display name for the action. */
    public function displayName(): string
    {
        return match ($this) {
            self::Clearing => 'Cache Clearing Started',
            self::Cleared => 'Cache Cleared',
            self::Failed => 'Cache Operation Failed',
        };
    }

    /** Short description of the action. */
    public function description(): string
    {
        return match ($this) {
            self::Clearing => 'Cache clearing operation has started.',
            self::Cleared => 'Cache has been successfully cleared.',
            self::Failed => 'Cache operation failed.',
        };
    }

    /** Relative priority/severity of the action. */
    public function priority(): string
    {
        return match ($this) {
            self::Clearing => 'low',
            self::Cleared => 'medium',
            self::Failed => 'high',
        };
    }

    /** Coarse outcome bucket for the action. */
    public function result(): string
    {
        return match ($this) {
            self::Clearing => 'in_progress',
            self::Cleared => 'success',
            self::Failed => 'failure',
        };
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Eventing\Events;

use Simtabi\Laranail\Toolkit\Enums\CacheAction;

/**
 * Dispatchable cache-lifecycle event.
 *
 * Consolidates the cache clear/clearing/failure signals into a single typed
 * event carrying a {@see CacheAction} and free-form metadata. Use the named
 * constructors to build and dispatch one fluently:
 *
 * ```php
 * CacheEvents::clearing()->dispatch();
 * CacheEvents::cleared(['tags' => ['users']])->dispatch();
 * CacheEvents::failed('store unreachable')->dispatch();
 * ```
 *
 * Being a {@see Event}, it is dispatchable, broadcastable and
 * queue-serializable out of the box.
 */
class CacheEvents extends Event
{
    /**
     * @param CacheAction          $action   The lifecycle phase this event represents.
     * @param array<string, mixed> $metadata Arbitrary, non-PII context for listeners.
     */
    public function __construct(
        public readonly CacheAction $action = CacheAction::Cleared,
        public readonly array $metadata = [],
    ) {}

    /**
     * Cache clearing has started.
     *
     * @param array<string, mixed> $metadata
     */
    public static function clearing(array $metadata = []): self
    {
        return new self(CacheAction::Clearing, $metadata);
    }

    /**
     * Cache clearing completed successfully.
     *
     * @param array<string, mixed> $metadata
     */
    public static function cleared(array $metadata = []): self
    {
        return new self(CacheAction::Cleared, $metadata);
    }

    /**
     * A cache operation failed.
     *
     * @param string               $reason   Why the operation failed.
     * @param array<string, mixed> $metadata Additional context (merged with `reason`).
     */
    public static function failed(string $reason, array $metadata = []): self
    {
        return new self(CacheAction::Failed, ['reason' => $reason, ...$metadata]);
    }

    /** Human-friendly display name for the event. */
    public function getDisplayName(): string
    {
        return $this->action->displayName();
    }

    /** Short description of the event (failures append the reason). */
    public function getDescription(): string
    {
        if ($this->action === CacheAction::Failed) {
            $reason = $this->metadata['reason'] ?? 'Unknown error';

            return 'Cache operation failed: ' . (is_string($reason) ? $reason : 'Unknown error');
        }

        return $this->action->description();
    }

    /** Relative priority/severity of the event. */
    public function getPriorityLevel(): string
    {
        return $this->action->priority();
    }

    /** Whether the cache operation succeeded. */
    public function isSuccessful(): bool
    {
        return $this->action === CacheAction::Cleared;
    }

    /** Coarse outcome bucket: success / failure / in_progress. */
    public function getResult(): string
    {
        return $this->action->result();
    }
}

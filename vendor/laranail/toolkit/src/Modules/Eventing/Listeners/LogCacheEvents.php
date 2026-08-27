<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Eventing\Listeners;

use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Enums\CacheAction;
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;

/**
 * Logs the cache lifecycle ({@see CacheEvents}) through the PSR-3 logger.
 *
 * Wired to {@see CacheEvents} by the toolkit provider but **gated** by
 * `config('laranail.toolkit.cache.log_events')` (default false) via
 * {@see shouldHandle()}, so it stays silent until a host opts in. Failures log
 * at `error`, clearing/cleared at `info`, keeping the maintenance surface
 * observable without adding noise by default.
 */
final class LogCacheEvents extends Listener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    protected function shouldHandle(object $event): bool
    {
        return $event instanceof CacheEvents
            && ToolkitConfig::bool('laranail.toolkit.cache.log_events', false);
    }

    protected function handleEvent(object $event): void
    {
        if (!$event instanceof CacheEvents) {
            return;
        }

        $context = ['action' => $event->action->value, ...$event->metadata];

        if ($event->action === CacheAction::Failed) {
            $this->logger->error($event->getDescription(), $context);

            return;
        }

        $this->logger->info($event->getDescription(), $context);
    }
}

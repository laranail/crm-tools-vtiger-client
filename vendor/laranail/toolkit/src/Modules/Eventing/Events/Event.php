<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Eventing\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Reusable event base.
 *
 * Bundles the conventional event traits so subclasses can be dispatched
 * (`MyEvent::dispatch(...)`), broadcast, and safely serialized for the queue
 * without re-declaring the boilerplate on every event.
 */
abstract class Event
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
}

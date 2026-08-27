# Eventing module

Reusable bases for events and listeners, plus one ready-made cache-lifecycle
event. Everything lives under `Simtabi\Laranail\Toolkit\Modules\Eventing`.

> **Provider-less by design.** The Eventing module ships **no service
> provider** and registers nothing at boot — it is a set of *base classes* you
> extend. Dispatching and listening happen through **native Laravel** (the
> `event()` helper, `Event::dispatch()`, `EventServiceProvider` / attribute
> discovery in your own app). There is nothing to publish or configure.

## `Event` — reusable event base

`abstract class Modules\Eventing\Events\Event` bundles the conventional event
trait trio so a concrete event only carries its payload:

- `Illuminate\Foundation\Events\Dispatchable` — `MyEvent::dispatch(...)`.
- `Illuminate\Broadcasting\InteractsWithSockets` — broadcasting helpers.
- `Illuminate\Queue\SerializesModels` — safe model serialization for the queue.

```php
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\Event;

class OrderShipped extends Event
{
    public function __construct(public readonly Order $order) {}
}

OrderShipped::dispatch($order);   // from Dispatchable
event(new OrderShipped($order));  // or the native helper
```

## `Listener` — gated listener base

`abstract class Modules\Eventing\Listeners\Listener` centralises the "should
this listener run?" decision. You implement **`handleEvent()`** (your real
logic) rather than `handle()` directly; the provided `handle()` consults
**`shouldHandle()`** first and short-circuits when it returns `false`:

```php
public function handle(object $event): void
{
    if (!$this->shouldHandle($event)) {
        return;
    }

    $this->handleEvent($event);
}
```

`shouldHandle(object $event): bool` defaults to `true` — override it to gate on
a feature flag, environment, or payload check without repeating the guard in
every listener.

```php
use Simtabi\Laranail\Toolkit\Modules\Eventing\Listeners\Listener;

class SendWelcome extends Listener
{
    protected function shouldHandle(object $event): bool
    {
        return $event->user->wantsEmail;
    }

    protected function handleEvent(object $event): void
    {
        // dispatch the welcome mail
    }
}
```

Register it the native way — autodiscovery, an `EventServiceProvider::$listen`
entry, or `Event::listen()`. Laravel calls `handle()`, which routes through the
gate.

## `CacheEvents` — ready-made cache-lifecycle event

`class Modules\Eventing\Events\CacheEvents extends Event` consolidates the cache
`clearing` / `cleared` / `failed` signals into a single typed event carrying a
`CacheAction` enum and free-form, non-PII metadata. Build and dispatch one with
the named constructors:

```php
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;

CacheEvents::clearing()->dispatch();
CacheEvents::cleared(['tags' => ['users']])->dispatch();
CacheEvents::failed('store unreachable')->dispatch();

// or via the native helper / the Dispatchable static with explicit args:
event(CacheEvents::cleared());
CacheEvents::dispatch(\Simtabi\Laranail\Toolkit\Enums\CacheAction::Cleared);
```

| Factory | Action | Notes |
|---|---|---|
| `CacheEvents::clearing(array $metadata = [])` | `CacheAction::Clearing` | Clearing has started. |
| `CacheEvents::cleared(array $metadata = [])` | `CacheAction::Cleared` | Cleared successfully. |
| `CacheEvents::failed(string $reason, array $metadata = [])` | `CacheAction::Failed` | `reason` is merged into `metadata`. |

Read-only members: `public readonly CacheAction $action`,
`public readonly array $metadata`.

Accessors (each delegates to the `CacheAction` enum):

| Method | Returns |
|---|---|
| `getDisplayName()` | e.g. `Cache Cleared`. |
| `getDescription()` | Human description (failures append the `reason`). |
| `getPriorityLevel()` | `low` / `medium` / `high`. |
| `getResult()` | `in_progress` / `success` / `failure`. |
| `isSuccessful()` | `true` only for `CacheAction::Cleared`. |

### Dispatched by the cache maintenance surface

The [`CacheService`](../cache.md) maintenance methods (`clearConfig()`,
`optimize()`, …) fire these events automatically — `clearing` before each op and
`cleared` / `failed` after — so you can observe cache housekeeping without adding
any dispatch calls yourself.

### `LogCacheEvents` — bundled, opt-in listener

`class Modules\Eventing\Listeners\LogCacheEvents extends Listener` logs the cache
lifecycle through the PSR-3 logger (`failed` at `error`, the rest at `info`). The
toolkit provider wires it to `CacheEvents`, but its `shouldHandle()` gate keeps it
**silent unless** `config('laranail.toolkit.cache.log_events')` is `true` — so it
adds no noise by default. Enable it to trace cache clears/optimizes in logs.

### The `CacheAction` enum

`Simtabi\Laranail\Toolkit\Enums\CacheAction` is a backed `string` enum with three
cases and four helper methods used by `CacheEvents`:

| Case | `displayName()` | `priority()` | `result()` |
|------|-----------------|--------------|------------|
| `Clearing` (`'clearing'`) | Cache Clearing Started | `low` | `in_progress` |
| `Cleared` (`'cleared'`) | Cache Cleared | `medium` | `success` |
| `Failed` (`'failed'`) | Cache Operation Failed | `high` | `failure` |

(`description()` returns the matching one-line phrase for each case.)

[← Docs index](../../README.md#documentation)

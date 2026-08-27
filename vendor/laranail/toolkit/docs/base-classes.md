# Base classes

Five abstract base classes that bundle the boilerplate of common Laravel
building blocks behind sensible defaults. Extend them so your concrete
controllers, jobs, listeners, observers, and events only carry their own logic.

| Base class | Extend it to… |
|------------|---------------|
| `Http\Controllers\BaseController` | …build a controller with auth + validation + API responses ready. |
| `Jobs\BaseJob` | …queue work with retries, backoff, timeout, and failure logging. |
| `Modules\Eventing\Listeners\Listener` | …handle an event with a built-in "should I run?" gate. |
| `Observers\Observer` | …observe only the model events you care about. |
| `Modules\Eventing\Events\Event` | …dispatch/broadcast an event without re-declaring trait boilerplate. |

## BaseController

`abstract class BaseController extends Illuminate\Routing\Controller`

Mixes in `ApiResponseTrait` (consistent `success`/`error` JSON envelopes),
`AuthorizesRequests` (`$this->authorize(...)`), and `ValidatesRequests`
(`$this->validate(...)`). Extend it for any controller that wants those three out
of the box.

```php
use Simtabi\Laranail\Toolkit\Http\Controllers\BaseController;

class ReportController extends BaseController
{
    public function show(Request $request): JsonResponse
    {
        $this->authorize('view-reports');
        $data = $this->validate($request, ['from' => 'required|date']);

        return $this->successResponse($report); // from ApiResponseTrait
    }
}
```

> For full CRUD scaffolding (pagination, search, sorting) use the richer
> [`CrudController`](crud-controller.md); `BaseController` is the lean base for
> bespoke controllers.

## BaseJob

`abstract class BaseJob implements ShouldQueue`

Uses `Dispatchable`, `InteractsWithQueue`, `Queueable`, and `SerializesModels`,
and ships resilient defaults: `$tries = 3`, `$backoff = 10` (seconds),
`$timeout = 120` (seconds). `failed(Throwable $e)` logs the exhausted job via
`LogService` — override it for bespoke cleanup (call `parent::failed($e)` to keep
the logging).

```php
use Simtabi\Laranail\Toolkit\Jobs\BaseJob;

class ImportFeed extends BaseJob
{
    public function handle(): void { /* … */ }
}

ImportFeed::dispatch(); // 3 tries, 10s backoff, 120s timeout, auto-logged on failure
```

## Listener

`abstract class Listener`

Centralises the "should this listener run?" decision. `handle()` consults
`shouldHandle()` (defaults to `true`) and short-circuits when it returns `false`;
otherwise it calls your `handleEvent()`. Override `shouldHandle()` for feature
flags, environment, or payload gating without repeating the guard in every
listener.

```php
use Simtabi\Laranail\Toolkit\Modules\Eventing\Listeners\Listener;

class SendWelcome extends Listener
{
    protected function shouldHandle(object $event): bool
    {
        return $event->user->wantsEmail;
    }

    protected function handleEvent(object $event): void { /* … */ }
}
```

## Observer

`abstract class Observer`

Declares all twelve Eloquent lifecycle hooks (`retrieved`, `creating`, `created`,
`updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`, `restoring`,
`restored`, `forceDeleted`) as no-ops. Extend it and override **only** the events
you need — no empty stubs.

```php
use Simtabi\Laranail\Toolkit\Observers\Observer;

class PostObserver extends Observer
{
    public function saving(Model $model): void
    {
        $model->slug ??= str($model->title)->slug();
    }
}
```

## Event

`abstract class Event`

Lives in the `Modules\Eventing` module. Uses `Dispatchable`,
`InteractsWithSockets`, and `SerializesModels` — the standard event trait trio —
so a concrete event only carries its payload.

```php
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\Event;

class OrderShipped extends Event
{
    public function __construct(public readonly Order $order) {}
}

OrderShipped::dispatch($order);
```

### CacheEvents

`class CacheEvents extends Event` — a ready-made cache-lifecycle event built
on the base. It carries a typed `CacheAction` (`Clearing` / `Cleared` /
`Failed`) plus free-form metadata, with named constructors:

```php
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;

event(CacheEvents::clearing());
event(CacheEvents::cleared(['tags' => ['users']]));
event(CacheEvents::failed('store unreachable'));

// or build it from constructor args via the Dispatchable static helper:
CacheEvents::dispatch(\Simtabi\Laranail\Toolkit\Enums\CacheAction::Cleared);
```

Each instance exposes `getDisplayName()`, `getDescription()`,
`getPriorityLevel()` (`low`/`medium`/`high`), `getResult()`
(`in_progress`/`success`/`failure`) and `isSuccessful()`.

> The `Event`, `Listener` and `CacheEvents` classes live in the **provider-less**
> [Eventing module](modules/eventing.md) — see that page for the full
> `CacheAction` enum reference and the native dispatch/listen wiring.

[← Docs index](../README.md#documentation)

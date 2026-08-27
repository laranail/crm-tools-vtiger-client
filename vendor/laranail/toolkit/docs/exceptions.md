# Exceptions

The toolkit ships a small, structured exception hierarchy under
`Simtabi\Laranail\Toolkit\Exceptions`. A rich base — `LaranailException` —
carries structured context, metadata, an optional user-facing message and an
optional HTTP status, renders cleanly to JSON, and exposes a PSR-3 log context.
The domain-specific exceptions build on it (or on plain SPL bases) with named
factories so the call site reads intent-first.

> Every exception class below is **non-`final`** — extend any of them in your
> app to add your own factories, context or rendering. `LaranailException` is
> annotated `@phpstan-consistent-constructor`, so its static factories
> (`from()`, `wrap()`, `fromArray()`) return `static` and keep working in a
> subclass.

## Hierarchy at a glance

| Exception | Extends | Thrown when |
|-----------|---------|-------------|
| `LaranailException` | `\Exception` (`JsonSerializable`, `Stringable`) | Base — structured, loggable, JSON-renderable error. |
| `AuthenticationException` | `LaranailException` | An authentication operation fails (missing/invalid guard, unauthenticated). Self-renders a JSON `401`. |
| `ModelException` | `LaranailException` | An Eloquent model operation fails (missing PK, not found, invalid state). |
| `CollectionItemNotFound` | `\Exception` | A collection lookup finds no item for a key. |
| `ImmutableDataException` | `\Exception` | Code mutates a value object meant to be immutable. |
| `FileTooLargeException` | `\RuntimeException` | A file read exceeds the configured size limit. |
| `InvalidPathException` | `\RuntimeException` | An invalid / unsafe path is detected (traversal, null byte, outside allowed dir, bad chars). |

## `LaranailException` — the structured base

```php
use Simtabi\Laranail\Toolkit\Exceptions\LaranailException;

throw new LaranailException(
    message: 'Widget render failed',
    code: 0,
    context: ['widget' => $id],     // structured, safe to log
    meta: ['request_id' => $rid],   // reference IDs, tags, etc.
    userMessage: 'Something went wrong rendering that widget.',
    status: 500,
);
```

Static factories:

| Factory | Effect |
|---|---|
| `LaranailException::from(Throwable $previous)` | Wrap an existing throwable (alias of `wrap()`). |
| `LaranailException::wrap($previous, $message = '', $context = [], $meta = [], $userMessage = null, $status = null)` | Wrap and enrich; reuses the previous message/code when not overridden. |
| `LaranailException::fromArray($payload)` | Build from `['message', 'code', 'context', 'meta', 'userMessage', 'status', 'previous']`; unknown keys fold into `meta`. |

Fluent enrichers all return `static` (chainable): `withContext($key, $value)`,
`mergeContext($array)`, `withMeta($key, $value)`, `mergeMeta($array)`,
`withUserMessage(?$msg)`, `withStatus(?$status)`.

Accessors / serialization: `getContext()`, `getMeta()`, `getUserMessage()`,
`getStatus()`, `toArray(bool $withTrace = false)`, `toLogContext(bool $withTrace
= false)` (PSR-3 friendly), `jsonSerialize()` (the array form without a trace),
and `__toString()` (a one-line summary with JSON-encoded context/meta).

### Catching and recovering

```php
use Simtabi\Laranail\Toolkit\Exceptions\LaranailException;

try {
    $service->run();
} catch (LaranailException $e) {
    logger()->error($e->getMessage(), $e->toLogContext()); // structured context
    return response()->error($e->getUserMessage() ?? 'Request failed', $e->getStatus() ?? 500);
}
```

Because every toolkit exception (except the plain SPL-based ones) extends this
base, a single `catch (LaranailException $e)` recovers them all with the same
structured shape.

## `AuthenticationException`

Auth-specific factories on top of the base, each defaulting to HTTP `401`:

```php
use Simtabi\Laranail\Toolkit\Exceptions\AuthenticationException;

throw AuthenticationException::missingGuard();        // code 2001
throw AuthenticationException::invalidGuard('web');   // code 2002, context['guard']
throw AuthenticationException::unauthenticated('api'); // code 2003
```

It is **self-rendering**: on Laravel 11+ the framework handler calls `render()`
directly on the exception, so no custom `Handler` subclass is needed. When the
request **expects JSON** it emits a standardised envelope:

```json
{ "success": false, "message": "Please log in to continue.", "errors": {} }
```

(`debug` is added only when `app.debug` is on.) For non-JSON requests `render()`
returns `false`, deferring to Laravel's default web rendering (e.g. a redirect to
the login route) — the toolkit never hijacks web responses. Out-of-range statuses
fall back to `401`.

## `ModelException`

```php
use Simtabi\Laranail\Toolkit\Exceptions\ModelException;

throw ModelException::missingPrimaryKey(Post::class);   // code 3001
throw ModelException::notFound(Post::class, $id);        // code 3002, status 404
throw ModelException::invalidState(Post::class, 'draft'); // code 3003
```

`notFound()` carries a `404` status and a safe, stringified identifier in the
message; `context` always includes the `model` (and `identifier` / `reason`).

## SPL-based exceptions

These extend plain SPL classes (so they are *not* caught by
`catch (LaranailException)`), each with a single named factory:

```php
use Simtabi\Laranail\Toolkit\Exceptions\CollectionItemNotFound;
use Simtabi\Laranail\Toolkit\Exceptions\ImmutableDataException;
use Simtabi\Laranail\Toolkit\Exceptions\FileTooLargeException;
use Simtabi\Laranail\Toolkit\Exceptions\InvalidPathException;

throw CollectionItemNotFound::forKey('missing');           // \Exception
throw ImmutableDataException::forProperty('id');           // \Exception
throw FileTooLargeException::create($path, $size, $max);   // \RuntimeException, human-readable byte sizes
throw InvalidPathException::directoryTraversal($path);     // \RuntimeException
```

`InvalidPathException` adds reason-specific factories on top of `create()`:
`directoryTraversal()`, `nullByteDetected()`, `outsideAllowedDirectory()`, and
`invalidCharacters()` — used by the path-confinement guards across the file,
database, and tidy code paths.

`FileTooLargeException::create()` formats both the actual and maximum size as
human-readable strings (e.g. `1.50 MB`) in the message.

## App-side rendering helpers (`RendersApiExceptions`)

Laravel 11+ moved exception configuration into the app's own
`bootstrap/app.php` `withExceptions()` closure, so a package can no longer ship a
competing `Handler`. `Exceptions\Concerns\RendersApiExceptions` is a **final,
all-static registrar** exposing the reusable rendering/reporting logic as opt-in
registrars you wire in from that closure:

```php
use Illuminate\Foundation\Configuration\Exceptions;
use Simtabi\Laranail\Toolkit\Exceptions\Concerns\RendersApiExceptions;

->withExceptions(function (Exceptions $exceptions): void {
    RendersApiExceptions::register($exceptions);
    // or pick one:
    // RendersApiExceptions::registerSlackReporter($exceptions, throttleMinutes: 5);
    // RendersApiExceptions::registerMethodNotAllowedRenderer($exceptions);
})
```

- **`registerSlackReporter()`** — throttled Slack `critical` alert for uncaught
  exceptions. Skips console / maintenance-mode runs, no-ops when no Slack channel
  is configured, and de-duplicates bursts via a short-lived cache lock so a crash
  loop never floods the channel. The exception still flows to Laravel's default
  logging stack untouched.
- **`registerMethodNotAllowedRenderer()`** — renders `405 Method Not Allowed` as
  a JSON envelope for API (`expectsJson()`) requests, deferring to the default
  HTML rendering otherwise.

[← Docs index](../README.md#documentation)

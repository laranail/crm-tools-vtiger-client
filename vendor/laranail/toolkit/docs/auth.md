# Authenticated user

Guard-aware, null-safe accessors for the current user, on the `Toolkit`/`Laranail`
facade. They improve on a hard-coded `auth()->user()` / `App\Models\User`: they are
multi-guard, never assume the model's FQCN, and are statically typed.

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;

$user = Toolkit::user();                    // ?Authenticatable — null when guest
$user = Toolkit::userAs(User::class);       // ?User — statically inferred
$user = Toolkit::userOrFail();              // User — or throws (login redirect / 401)
```

## Accessors

| Method | Returns | |
|--------|---------|---|
| `user(?string $guard = null)` | `?Authenticatable` | The current user, or `null`. |
| `userAs(class-string<T> $model, ?string $guard = null)` | `?T` | The user, statically typed to `$model` (inferred `?User`); `null` when absent or not that type. |
| `userOrFail(?string $guard = null)` | `Authenticatable` | The user, or throws Laravel's `AuthenticationException` — a **login redirect** for web requests and a **401** for JSON/API (same as the `auth` middleware). |
| `withGuard(?string $guard)` | `ToolkitManager` | A clone whose accessors resolve against `$guard` (see below). |

## Swappable guard

Every accessor takes an optional per-call `$guard`. To resolve several calls
against the same guard without repeating it, swap it with **`withGuard()`** — it
returns a scoped clone, so it never mutates the shared manager:

```php
$admin = Toolkit::withGuard('admin');

$admin->user();          // resolved against the 'admin' guard
$admin->userOrFail();    // "

Toolkit::user();         // unchanged — still the default guard
```

Guard resolution order for each accessor: an explicit per-call `$guard` wins,
then a `withGuard()` swap, then `config('laranail.toolkit.auth.default_guard')`,
then the framework default (`auth.defaults.guard`).

## Configuration

See [configuration](configuration.md) — `laranail.toolkit.auth`:

- `default_guard` — the guard used when none is passed/swapped (`null` = framework
  default). Env: `LARANAIL_TOOLKIT_AUTH_GUARD`.
- `user_model` — a reserved, informational hint; **not** read at runtime (the
  `userAs()` generic provides the IDE typing).

## Optional global `user()` helper

An opt-in global `user()` helper delegates to `Toolkit::user()`. It is **not**
autoloaded; require it explicitly if you want it:

```php
require base_path('vendor/laranail/toolkit/helpers/user.php');
```

---

[← Docs index](../README.md#documentation)

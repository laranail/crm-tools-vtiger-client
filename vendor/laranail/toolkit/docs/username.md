# Username builder

`Simtabi\Laranail\Toolkit\Support\Username` is a fluent, immutable, fully
reusable builder for usernames / handles. It replaces the legacy pheg-bound
`Username::name2username()` with a native generator built only on Laravel's
`Str` helpers — no third-party name library, no hard Eloquent coupling.

Every chain method returns a fresh instance (clone-and-mutate), so a configured
builder is safe to share and re-run.

## Entry points

```php
use Simtabi\Laranail\Toolkit\Support\Username;

Username::for('John Doe');                 // any source string
Username::fromEmail('jane.doe@acme.test'); // the local part, '.' separator
Username::fromName('Jane', 'Doe');         // name mode (powers candidates())
Username::random('user', 4);               // a fresh anonymous handle
```

## Generating a single handle

`generate()` runs the full pipeline — ASCII transliteration → strip unsafe
characters → collapse/trim separators → prefix/suffix → leading-letter
enforcement → casing → length clamp → reserved check → a **bounded** uniqueness
loop (max 100 attempts, appending random digits each retry).

```php
Username::for('John Doe')->generate();                 // 'johndoe'
Username::fromEmail('Jane.Doe@acme.test')->generate(); // 'jane.doe'
Username::fromName('João', 'Müller')->generate();      // 'joaomuller' (transliterated)
Username::for('123')->generate();                      // 'user123'   (leading-letter)
```

`__toString()` is generation that never throws — it yields `''` on failure:

```php
(string) Username::for('jane');                        // 'jane'
```

## Configuration chain

```php
Username::fromName('Jane', 'Doe')
    ->separator('.')        // '' | '.' | '_' | '-'  (validated)
    ->lowercase()           // ->uppercase() | ->preserveCase()
    ->ascii(true)           // transliterate unicode/accents (default on)
    ->minLength(3)          // short handles are padded with random digits
    ->maxLength(20)         // long handles are truncated
    ->prefix('dev_')        // sanitised prefix
    ->suffix('_v2')         // sanitised suffix
    ->withRandomSuffix(4)   // append N random digits to every handle
    ->allow('._')           // restrict which of '._-' survive (default '._-')
    ->alphanumericOnly()    // strip to bare [a-z0-9] — no separators at all
    ->rejectSpaces()        // fail loudly if the SOURCE contains whitespace
    ->reserved(['admin'])   // names to avoid (treated like "taken")
    ->generate();
```

Invalid configuration throws `InvalidArgumentException` (bad separator,
`maxLength < minLength`, a non-`._-` character passed to `allow()`, …).

## Spaces are never allowed

A generated handle is **guaranteed** to contain no space character. Three
guards back that promise:

1. **Silent strip (default).** Whitespace in the source is removed during
   sanitisation, so `Username::for('john doe')->generate()` yields `johndoe`.
2. **`allow(' ')` throws.** A space is never an allowable handle character, so
   passing one (or any char outside `._-`) to `allow()` raises
   `InvalidArgumentException` — a space can't be opted back in.
3. **`rejectSpaces()` — strict mode.** For callers who want to fail loudly
   instead of silently stripping, `rejectSpaces()` throws
   `InvalidArgumentException` when the **source** string (or any name part)
   contains whitespace.

```php
Username::for('john doe')->generate();              // 'johndoe' (stripped)
Username::for('x')->allow(' ');                     // throws InvalidArgumentException
Username::for('john doe')->rejectSpaces()->generate(); // throws InvalidArgumentException
```

`alphanumericOnly()` is a convenience for `->separator('')->allow('')`: the
result is restricted to bare `[a-z0-9]` with no separators of any kind.

```php
Username::for('jane.doe')->alphanumericOnly()->generate(); // 'janedoe'
```

## Candidates

`candidates(int $n = 10)` returns deterministic, ordered suggestions. In name
mode the canonical variants come first:

```php
Username::fromName('Jane', 'Doe')->candidates();
// ['janedoe', 'jane.doe', 'jane_doe', 'jdoe', 'jane.d', 'jane', 'doe', ...numeric variants]
```

The list is de-duplicated, never contains empty strings, and is padded with
numeric-suffixed variants of the primary handle up to `$n`.

## Uniqueness — any backend

`unique(callable $checker)` plugs in a backend-agnostic availability check.
`$checker($username)` returns **true when the handle is AVAILABLE**. It works
with Eloquent, the cache, a raw DB query — anything:

```php
// Eloquent
$handle = Username::fromName('Jane', 'Doe')
    ->unique(fn (string $u): bool => ! User::query()->where('username', $u)->exists())
    ->generate();

// Cache
$handle = Username::random('guest')
    ->unique(fn (string $u): bool => ! Cache::has("handle:$u"))
    ->generate();
```

When every deterministic attempt is taken, the bounded loop appends random
digits; if it still cannot find a free handle within 100 attempts it throws a
`RuntimeException` (the legacy version recursed forever instead).

## Reserved / blacklist

```php
Username::for('admin')
    ->reserved(['admin', 'root', 'support'])
    ->generate(); // 'admin####' — never the bare reserved word
```

## Inspecting the build

```php
Username::fromName('Jane', 'Doe')->separator('.')->uppercase()->toArray();
// ['username' => 'JANE.DOE', 'separator' => '.', 'case' => 'upper', ...]
```

## Helper shortcuts

The `Helper` facade exposes thin wrappers for the common cases (they all
delegate here):

| Helper | Equivalent |
|--------|------------|
| `Helper::usernameFromEmail($email)` | `Username::for(<local part>)->preserveCase()->…->generate()` |
| `Helper::nameToUsernames($first, $last)` | `Username::fromName($first, $last)->candidates(10)` |
| `Helper::generateUsername($prefix, $digits)` | `Username::random($prefix, $digits)->generate()` |

The `HasFormatters` model trait's `suggestUsername()` also delegates here,
wiring the model's own availability check in via `unique()`.

[← Docs index](../README.md#documentation)

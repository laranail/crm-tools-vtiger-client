# Helpers: static pure functions vs. injectable services

The toolkit splits its day-to-day helpers along a single line:

- **Pure-function domains stay static** on `Simtabi\Laranail\Toolkit\Helpers\Helper`
  (arrays, strings & identity, dates, geo, console). Every method is `static`
  and side-effect free — call them directly (`Helper::ucWords('jane doe')`), no
  container resolution required. `Helper` is a `final` class composed of per-domain
  `InteractsWith*` traits under `Simtabi\Laranail\Toolkit\Helpers\Concerns`; the
  trait split is an internal organising detail — the public surface is `Helper::`.
- **Stateful / swappable domains are injectable services** (the primary API),
  resolved from the container or fronted by the `Toolkit` facade:
  - **Files** → `Services\Contracts\FileServiceInterface` (`Toolkit::file()`)
  - **System** → `Services\Contracts\SystemServiceInterface` (`Toolkit::system()`)
  - **Session** → `Services\Contracts\SessionServiceInterface` (`Toolkit::session()`)

```php
use Simtabi\Laranail\Toolkit\Helpers\Helper;          // pure static helpers
use Simtabi\Laranail\Toolkit\Facades\Toolkit;         // service accessors
```

> The static `Helper::` methods are deliberately **not** fronted by the
> [`Toolkit` facade](../README.md#unified-entry--the-toolkit-facade) — they are
> pure utilities, so call them on `Helper` directly. The file/system
> services ARE on the facade (and injectable by interface).

## Arrays

```php
Helper::arrayTrim(['  a ', 'b ']);              // ['a', 'b']
Helper::arrayFlatten($nested);                  // single level of leaf values
Helper::arrayToDotNotation('a[b][c]');          // 'a.b.c'
```

## Strings & identity

```php
Helper::strBetween('[tag]hi[/tag]', '[tag]', '[/tag]'); // 'hi'
Helper::strSlugify('Héllo World');              // 'hello-world'
Helper::ucWords('śćż leçon');                   // mb-aware title case
Helper::usernameFromEmail('Jane.Doe@x.io');     // 'Jane.Doe'  (delegates to Support\Username)
Helper::emailFromUsername('jane', 'acme.test'); // 'jane@acme.test'
Helper::nameToUsernames('Imani', 'Manyara');    // suggestion list (Username::candidates)
Helper::generateUsername('guest', 4);           // 'guest4821'  (Username::random)
Helper::escapeHtml($dirty);                     // XSS-safe HtmlString
Helper::classBasename($model);                  // short class name
Helper::randomIntExcept(1, 6, [3]);             // 1,2,4,5 or 6 (bounded; throws if impossible)
Helper::faker('en_US');                         // a Faker generator
```

> The three username generators (`usernameFromEmail`, `nameToUsernames`,
> `generateUsername`) are thin wrappers over the fluent
> [`Support\Username`](username.md) builder — reach for `Username` directly when
> you need separators, casing, ASCII transliteration, reserved lists, length
> limits, or a pluggable uniqueness check.

## Dates

```php
Helper::carbonParse('2026-01-02');              // '2026-01-02 00:00:00'
Helper::carbonHumanDiff($date);                 // '3 days ago'
```

## System (service)

Read-only runtime / environment introspection (no `config()` mutation, no I/O
beyond reading PHP/ini/`$_SERVER` state). Resolve via the container or
`Toolkit::system()`; inject `SystemServiceInterface` by type. Byte formatting in
`memoryUsage()` delegates to the file service (single formatter, no duplication).

```php
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;

$system = app(SystemServiceInterface::class);    // or Toolkit::system()

$system->parseMemoryLimit('256M');               // 268435456 (bytes; -1 = unlimited)
$system->memoryLimit();                          // '256M'
$system->memoryUsage();                          // ['current', 'peak', 'limit', 'current_formatted', 'peak_formatted']
$system->phpVersion();                           // '8.4.3'
$system->isPhpVersionSupported('8.4');           // bool
$system->isCli();                                // bool
$system->isHttps();                              // bool (alias: isSslInstalled())
$system->composer();                             // app composer.json as array (never throws)
$system->composerPackageVersion('laravel/framework'); // declared constraint or null
$system->systemInfo();                           // PHP/OS/SAPI/Laravel/env snapshot
$system->serverEnv();                            // read-only server settings snapshot
```

## Files (service)

File-**name** / size inspection plus a few thin, path-guarded filesystem probes.
Resolve via the container or `Toolkit::file()`; inject `FileServiceInterface`.

```php
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;

$files = app(FileServiceInterface::class);       // or Toolkit::file()

$files->formatFileSize(1024);                    // '1 KB'
$files->extension('/a/b/photo.JPG');             // 'jpg'
$files->filenameWithoutExtension('photo.jpg');   // 'photo'
$files->isImage('photo.png');                    // true
$files->sanitizeFilename($uploadedName);         // strips separators / null bytes

$files->exists('/srv/app/.env');                 // bool — safe path + File::exists
$files->size('/srv/app/dump.sql');               // int bytes (0 if missing/unsafe)
$files->lastModified('/srv/app/dump.sql');       // int UNIX ts (0 if missing/unsafe)
$files->hasAllowedExtension('db.sqlite', ['sql', 'sqlite', 'db']); // true
$files->validateSize('/srv/app/dump.sql', maxMb: 10);             // bool — within size limit
$files->validate('/srv/app/dump.sql', ['sql'], maxMb: 10);        // bool — extension + size
$files->fileInfo('/srv/app/dump.sql');           // path/size/extension/name/… or []
$files->generateName('pdf');                     // 'Xa9…q2.pdf' (random name)
$files->toDataUri('/srv/app/logo.png');          // 'data:image/png;base64,…' or ''
$files->fromJson('{"a":1}');                     // ['a' => 1] (file path or raw string)
```

`sanitizeFilename()` cleans a file **name** (apply after `basename()`, before
storing an uploaded name) — it is not a path sanitizer.

The probes (`exists`, `size`, `lastModified`, `fileInfo`, `toDataUri`,
`fromJson`) are read-only and exception-safe: each rejects `..` traversal
segments and null bytes via the canonical `Traits\FilePathGuard` (returning
`false`/`0`/`[]`/`null` rather than throwing). `hasAllowedExtension()` is
generic — pass whatever allow-list you need; it lower-cases and strips a leading
dot before comparing. Plain read/write/copy/move/delete are deliberately not
wrapped — use the `Storage` / `File` facades or `Traits\FileProcessingTrait`
directly.

## Session (service)

Session / query-string **filter-key** helpers (the `&`-joined filter tokens
list/search screens carry in the query string) plus a JavaScript-readable cookie
bridge. The three filter-key methods are pure string ops; `saveJavaScriptCookies`
is the genuinely stateful one — it writes through the **injected** session store
and cookie jar (no `session()`/`Cookie::` facades). Resolve via the container or
`Toolkit::session()`.

```php
use Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface;

$session = app(SessionServiceInterface::class);          // or Toolkit::session()

$key = $session->joinInFilterKey('status', 'active');    // 'status&active'
$session->existsInFilterKey($key, 'active');             // true
$session->removeFromFilterKey($key, 'active');           // 'status' (null if empty)

// Persist a request input into the session + queue a cookie (minutes) for JS.
$session->saveJavaScriptCookies('theme', duration: 60);
```

> `removeFromFilterKey()` also strips the reserved `page` token; the cookie helper
> is a no-op when the named request input is absent. The cookie duration is in
> **minutes** (Laravel's `CookieJar::queue` convention).

## Geo

Native geospatial math (no third-party geo dependency).

```php
Helper::distanceBetween(-1.2921, 36.8219, -4.0435, 39.6682);        // km
Helper::distanceBetween($lat1, $lng1, $lat2, $lng2, unit: 'mi');    // miles
```

Great-circle (Haversine) distance, returned in `$unit` (`km` | `mi` | `m` | `nmi`).

## Console

Styled output onto a Symfony console stream.

```php
Helper::write($output, 'info', 'Done.', 'All green.');
// emits <info>Done.</info> and <info>All green.</info>
```

Wraps each line in the given Symfony style tag (`info`, `comment`, `error`, …).

[← Docs index](../README.md#documentation)

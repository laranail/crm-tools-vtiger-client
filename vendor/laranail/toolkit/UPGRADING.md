# Upgrade guide

## Atlas, Avatar and Gravatar moved out

Three modules left this package. `Modules\Atlas` became
[`laranail/atlas`](https://opensource.simtabi.com/documentation/laranail/atlas/); `Modules\Avatar`
and `Modules\Gravatar` merged into
[`laranail/avatar`](https://opensource.simtabi.com/documentation/laranail/avatar/).

That drops `rinvex/countries` (~17 MB) and `intervention/image` from this package's dependency tree
outright — they had exactly one consumer each.

```diff
+   composer require laranail/atlas    # if you used Toolkit::atlas()
+   composer require laranail/avatar   # if you used Toolkit::avatar() or ::gravatar()
```

### Atlas

```diff
-   use Simtabi\Laranail\Toolkit\Facades\Toolkit;
+   use Simtabi\Laranail\Atlas\Facades\Atlas;

-   Toolkit::atlas()->countries();
+   Atlas::countries();

-   Toolkit::atlas()->forSelectBox('name');
+   Atlas::options('iso2', 'name');
```

The fourteen fixed methods became a composable query, so anything the old module had not anticipated
no longer means filtering its array output by hand:

```php
Atlas::query()->inContinent('AF')->usingCurrency('KES')->sortedByName()->get();
```

Return types changed from bare arrays to `CountryRecord` objects. That is the point of the move —
the old arrays were shaped by whatever `rinvex/countries` exposed, so the data package was
load-bearing in every call site rather than only in the loader.

`config/laranail/toolkit/atlas.php` is gone. Atlas publishes its own
`config/laranail/atlas.php`; the continent list is no longer configurable, because trimming it used
to make `countriesByContinent()` silently drop every country on a removed continent — a display
preference deleting data.

> **`availableLocales()` was broken and is fixed.** It scanned `resource_path('lang')`, which
> Laravel abandoned in version 9, so it returned an empty list on every modern application. Atlas's
> `LocaleRegistry` reads `lang_path()` first. If you were working around the empty list, stop.

### Avatar and Gravatar

```diff
-   use Simtabi\Laranail\Toolkit\Facades\Toolkit;
+   use Simtabi\Laranail\Avatar\Facades\Avatar;

-   Toolkit::avatar()->setName('Ada Lovelace')->setSize(128, 128)->generateDataUri();
+   Avatar::builder()->size(128)->for('Ada Lovelace')->src();

-   Toolkit::gravatar()->setEmail($email)->setSize(120)->generate();
+   Avatar::builder('gravatar-url')->size(120)->for($email)->src();
```

**The builder is immutable.** The old service held nineteen mutable properties with setters
returning `$this` and was a container singleton, so two components rendering avatars on one page
disagreed about the size and the second one won. Every method now returns a new instance, so a
partially-configured builder is safe to share.

Four behaviours changed, all of them deliberately:

| Was | Now | Why |
|---|---|---|
| Gravatar hashes were MD5 | SHA-256 | An MD5 of an email is not a privacy measure — rainbow tables cover most real addresses, so `<img src=".../avatar/<md5>">` publishes your users' addresses. Gravatar has accepted SHA-256 since 2023. Pass `algorithm: 'md5'` if you stored the old hash. |
| `getGravatar()` defaulted `$isHttps = false` | `https` everywhere | `GravatarService` defaulted `true` and two `AvatarService` methods defaulted `false`, so one application emitted both. `withHttps(false)` is the only way down. |
| Initials rendered as PNG via `intervention/image` | SVG | No GD, no Imagick, no font file, and resolution-independent. The raster renderer is still available with `intervention/image` installed. |
| `substr()` on names | multibyte-correct | Any non-Latin name produced a mojibake avatar. |

`HasAvatar` moved with the module. `Simtabi\Laranail\Toolkit\Traits\HasAvatar` is gone.

> **Two bundled fonts are not carried over, for licensing reasons.** `FreeSerif.ttf` is GPL-3.0 —
> its font exception covers documents that *embed* the font, not redistribution of the file — so
> shipping it inside an MIT package distributed GPL software under an MIT banner. `msyh.ttf` is not
> Microsoft YaHei despite the filename; it is Droid Sans Fallback carrying an Ascender Corporation
> EULA whose text reads *"you may not copy this font software"*. Only `Roboto-Bold.ttf`
> (Apache-2.0) ships with `laranail/avatar`. If you rendered CJK initials, supply your own font.

### `Helper::distanceBetween()`

Moved to `laranail/atlas`, and it now returns a `Distance` rather than a bare float:

```diff
-   Helper::distanceBetween($lat1, $lng1, $lat2, $lng2, 'km');
+   Atlas::distance(new Coordinates($lat1, $lng1), new Coordinates($lat2, $lng2))->kilometres();
```

The old signature returned a number whose unit was decided by a string argument several lines
earlier, so `$d > 100` could not be read without scrolling and changing the unit silently rescaled
every comparison below it.

## `Toolkit::config()` moved to `laranail/package-tools`

Config machinery belongs with the package-authoring runtime, which already owned the config file
resolver, merger, validator and pattern resolver under `Services/Config/`. Keeping a second
`ConfigMerger` here meant two classes with the same short name and the same four-method API, free to
drift apart — and two container-resolvable services with **opposite** semantics both authoritative
over `config()`: `ConfigService::merge()` is `mergeConfigFrom`-style where app config wins, while
`ConfigManager::override()` force-overrides. Provider boot order decided which one won.

```diff
+   composer require laranail/package-tools
```

```diff
-   use Simtabi\Laranail\Toolkit\Services\Contracts\ConfigManagerInterface;
+   use Simtabi\Laranail\Package\Tools\Contracts\ConfigManagerInterface;

-   Toolkit::config()->set('services.stripe.key', $key);
+   app(ConfigManagerInterface::class)->set('services.stripe.key', $key);
+   // or the facade package-tools registers:
+   PackageConfig::set('services.stripe.key', $key);
```

- `Toolkit::config()` and `Laranail::config()` are removed.
- `Simtabi\Laranail\Toolkit\Services\ConfigManager`, its contract,
  `Support\ConfigMerger` and `Exceptions\ConfigException` are removed.
  `ConfigException` had exactly one thrower, so a `catch` for it was already dead code.
- The boundary is now documented rather than implicit: `ConfigService` is boot-time merge where the
  **app** wins; `ConfigManager` is runtime override where the **caller** wins.

**One behaviour change.** `remove()` now makes both `get()` and `has()` miss for a top-level key.
Here it could only ever *null* the value — it passed the whole config array as `Repository::set()`'s
key — so `get()` returned null while `has()` still returned `true`. Code that relied on the key
surviving as null will now see it absent.

## `Toolkit::pythonApi()` moved to `laranail/python`

The HTTP client was only ever a third of the problem. `laranail/python` is a bidirectional bridge:
Laravel to Python over HTTP, Laravel to Python as a hardened local process, and Python back to Laravel
through HMAC-signed callbacks — with an allow-list of interpreters, payloads on stdin rather than
argv, an env allow-list for the child process, and a redactor that masks the literal secret values the
package injected.

```diff
+   composer require laranail/python
```

```diff
-   Toolkit::pythonApi()->fastapi()->post('/predict', $payload);
+   Python::service('fastapi')->post('/predict', $payload);
```

- `Toolkit::pythonApi()` and `Laranail::pythonApi()` are removed, along with
  `Services\PythonApiService`, its contract, `Services\PythonServiceDefinition` and
  `Exceptions\PythonApiException`.
- `config('laranail.toolkit.python.services.*')` becomes `config('laranail.python.services.*')`.
  **Env var names are unchanged**, so an existing `.env` keeps working. Run
  `php artisan laranail::python.install`.
- `fastapi()` and `flask()` are no longer hardcoded methods — `service('fastapi')` takes the name.
  That was the extraction blocker in the client implementation this generalises.

**One behaviour change.** `timeout` used to fall back to `laranail.toolkit.http.request_timeout`;
`laranail/python` has its own `defaults.timeout` and does not read toolkit's HTTP config.

## The captcha module moved to `laranail/captcha`

It outgrew a toolkit module. The replacement covers eleven providers rather than five, adds
environment-scoped credentials, a database-backed settings store, replay and hostname enforcement,
and an edge bot-management middleware — none of which belongs behind `Toolkit::captcha()`.

```diff
+   composer require laranail/captcha
```

- `Toolkit::captcha()` is removed. Use the `Captcha` facade, which `laranail/captcha` registers.
- `config('laranail.toolkit.captcha.*')` becomes `config('laranail.captcha.*')`, with per-provider
  credentials under an environment block. Run `php artisan laranail::captcha.install`.
- The `Captcha` alias is no longer registered here, so the two packages no longer collide.

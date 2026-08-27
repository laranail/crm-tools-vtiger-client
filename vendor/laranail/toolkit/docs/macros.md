# Macros & Blade directives

All macros are registered eagerly by `MacroServiceProvider`, and all directives
by `BladeServiceProvider`. Only additions with **no native Laravel equivalent**
are included — native duplicates were intentionally dropped.

## Str / Stringable macros

`kebabToTitle`, `snakeToTitle`, `camelToTitle`, `truncateMiddle($len = 50,
$middle = '...')`, `isEmail`, `stripWhitespace`, `normalizeWhitespace`, `toBool`,
`wrapWith($wrapper = '"')`, `replaceMany($replacements)` (Str only),
`matches($pattern)` (whole-string PCRE test → bool), `reverseString`,
`countWords`, `removeAccents`, `readingMinutes($wordsPerMinute = 200)`,
`highlightWords($words)`, `linesCount`, `interpolate($replacements)`
(`:placeholder` interpolation), and `stripTags($allowedTags = null)` (Str only —
`Stringable::stripTags` is native).

```php
Str::camelToTitle('helloWorld');          // "Hello World"
Str::truncateMiddle('a-very-long-name');  // "a-very-...name"
str('Café')->removeAccents();             // "Cafe"
Str::readingMinutes($article);            // 4
Str::highlightWords('the quick fox', 'quick'); // HtmlString: "the <mark>quick</mark> fox"
Str::interpolate('Hi :name', ['name' => 'Ada']); // "Hi Ada"
Str::linesCount("a\nb\nc");               // 3
Str::stripTags('<p>Hello <b>x</b></p>', '<b>'); // "Hello <b>x</b>"
```

`highlightWords` is XSS-safe: the input text is `e()`-escaped, only matched terms
are wrapped in `<mark>…</mark>`, and the result is returned as an
`Illuminate\Support\HtmlString`. `interpolate` replaces the longest placeholder
first, so `:foo_bar` is never partially matched by `:foo`.

### String similarity

Native (no third-party / pheg dependency) fuzzy-matching helpers — also on
`Stringable`:

| Macro | Returns | Notes |
|---|---|---|
| `levenshtein($other)` | `int` | Edit distance (PHP's native `levenshtein`, byte-based). |
| `similarText($other)` | `float` | Similarity as a **percentage** (0–100) via `similar_text`. |
| `jaroWinkler($other)` | `float` | Jaro–Winkler similarity (0–1), pure-PHP, favours a common prefix. |
| `closest($candidates)` | `?string` | The nearest candidate by Levenshtein distance (ties → first; `null` for an empty list). |

```php
Str::levenshtein('kitten', 'sitting');               // 3
Str::similarText('World', 'word');                   // 80.0
Str::jaroWinkler('MARTHA', 'MARHTA');                // 0.9611
Str::closest('appel', ['apple', 'grape', 'apply']);  // "apple"
str('appel')->closest(['apple', 'grape']);           // "apple"
```

## Carbon macros

Date helpers and ~90 national-calendar predicates (15 countries + multinational
feasts) are registered on `Carbon` / `CarbonImmutable`. See the dedicated
**[Carbon macros](carbon-macros.md)** reference.

```php
Carbon::parse('2026-06-25')->addBusinessDays(3);   // skips weekends
Carbon::now()->isFrenchNationalDay();              // bool
```

## Arr macros

`filterNulls`, `filterEmpty`, `mapKeys($cb)`, `insertAfter($key, $insert)`,
`insertBefore($key, $insert)`, `removeValue($value)`, `removeValues($values)`,
`renameKey($old, $new)`, `renameKeys($changes)` (multi-rename from an
`[old => new]` map), `average($key = null)`, `median($key = null)`,
`groupByKey($key)`, `uniqueBy($key)`, `sortByKeys($keys)`.

```php
Arr::filterNulls(['a' => 1, 'b' => null]); // ['a' => 1]
Arr::average($rows, 'score');
```

## Collection macros

`transpose`, `recursive`, `mapToKey($cb)`, `filterRecursive($cb = null)`,
`firstOrFail($cb = null, $default = null)`, `sumRecursive($key = null)`,
`averageBy($cb)`, `toCsv($delimiter = ',', $enclosure = '"', $escape = '\\')`,
`prioritize($cb)`, `rotateLeft($count = 1)`, `rotateRight($count = 1)`,
`toTree($parentKey = 'parent_id', $childrenKey = 'children')`,
`insertAfter($key, $value)`, `insertBefore($key, $value)`,
`before($current, $strict = false)`, `insertAt($index, $item, $key = null)`,
`rotate($offset)` (signed), `firstOrPush($cb, $value, $instance = null)`,
`eachCons($size, $preserveKeys = false)` (overlapping windows),
`sliceBefore($cb, $preserveKeys = false)`, `chunkBy($cb, $preserveKeys = false)`,
`groupByModel($cb, $modelKey = 0, $itemsKey = 1)`,
`forSelectBox($key, $value, $addEmpty = true)`, `extract($keys)`,
`tail($preserveKeys = false)`, `toPairs()`, `fromPairs()`, `ifEmpty($cb)`,
`mapKeyValuePairs()` (map `{key, value}` rows into an associative collection),
`sortSearchResults($searchTerms, $column)` (relevance sort: exact +100,
starts-with +50, contains +25, else a `similar_text()` weight),
`pluckMany($keys)` (reduce each item to only the given keys),
`replaceInKeys($search, $replace)` (`str_replace` over every key, values kept),
`collectBy($key, $default = null)` (wrap the value at `$key` in a new
collection), `filterMap($cb)` (map then drop falsy results),
`ifAny($cb)` (run `$cb` only when not empty — complement of `ifEmpty`),
`none($key, $value = null)` (inverse of `contains`), `pluckToArray($value, $key =
null)` (`pluck` returning a plain array), `withSize($size)` (a `[1..$size]`
range), `insertAfterKey($afterKey, $item, $key = null)` /
`insertBeforeKey($beforeKey, $item, $key = null)` (key-preserving positional
insert by key), `sectionBy($key, $preserveKeys = false, $sectionKey = 0,
$itemsKey = 1)` (split into consecutive sections each time the resolved key
changes), and the deep-path string filters
`whereContains($key, $value, $caseSensitive = true)` /
`whereStartsWith($key, $value, $caseSensitive = true)` /
`whereEndsWith($key, $value, $caseSensitive = true)` (keep items whose value
resolved at the dot-path `$key` is a **string** matching the substring test).
The string filters guard strictly: a non-string value at `$key`
(int / null / array / missing key) is **excluded**, never coerced, and the
case-insensitive form lowercases multibyte-safely via `Str::lower`.

```php
collect($flatRows)->toTree('parent_id'); // nested children tree
collect($items)->prioritize(fn ($i) => $i->pinned);
collect([1, 1, 2, 3, 3])->chunkBy(fn ($n) => $n); // [[1,1],[2],[3,3]]
collect($rows)->forSelectBox('id', 'name');       // ['' => '', 5 => 'Apple', ...]
collect($posts)->sortSearchResults('laravel api', 'title'); // most relevant first
collect([1, 2, 3, 4])->filterMap(fn ($n) => $n % 2 ? false : $n * 10); // [1 => 20, 3 => 40]
collect(['a' => 1, 'b' => 2])->insertAfterKey('a', 99, 'x'); // ['a'=>1,'x'=>99,'b'=>2]
collect($users)->whereContains('user.email', 'example');       // deep-path substring
collect($files)->whereEndsWith('name', '.pdf', caseSensitive: false); // 'A.PDF' matches
```

## Query builder macros

On both the base query builder and Eloquent builder: `whenFilled($value, $cb)`,
`whereBetweenDates($column, $start, $end)`, `orderByNullsLast($column,
$direction = 'asc')` / `orderByNullsFirst(...)` (base builder), `log($channel =
null)` (base builder). Eloquent builder also adds `existsOr($cb)` and
`doesntExistOr($cb)`.

```php
Post::query()
    ->whenFilled($request->status, fn ($q, $v) => $q->where('status', $v))
    ->whereBetweenDates('published_at', $from, $to)
    ->get();
```

## Request macros

Registered on `Illuminate\Http\Request` (callable on any injected/`request()` instance):

| Macro | Returns | Purpose |
|-------|---------|---------|
| `expectsJsonOrAjax()` | `bool` | Request expects JSON or is XHR/AJAX. |
| `isJsonRequest()` | `bool` | Has a JSON content type. |
| `isBot()` | `bool` | User-agent looks like a known bot/crawler. |
| `isFromMobile()` | `bool` | User-agent looks like a mobile device. |
| `getReferer(?string $default = null)` | `?string` | The HTTP referer, or the default. |
| `isFromDomain(string $domain)` | `bool` | Referer/origin belongs to `$domain`. |
| `hasFiles(array $keys)` | `bool` | Every named input is an uploaded file. |
| `hasValidFile(string $key)` | `bool` | The input at `$key` is a valid upload. |
| `hasAny(array $keys)` | `bool` | At least one of the named inputs is present. |
| `onlyFilled(array $keys)` | `array` | The named inputs that are non-empty. |
| `mergeIfMissing(array $values)` | `Request` | Merge defaults only for absent keys. |

```php
if ($request->expectsJsonOrAjax() && ! $request->isBot()) {
    $data = $request->onlyFilled(['name', 'email']);
    $request->mergeIfMissing(['locale' => 'en']);
}
```

## Response macros

Registered on the response factory (callable via the `response()` helper or the
`Response` facade). `success`, `error` and `message` delegate to the canonical
`ApiResponseTrait` envelope (one place shapes the JSON), and `pdf` streams raw
PDF bytes with the correct headers — never echoing unescaped HTML.

- `response()->success($data = null, $message = 'Request successful.', $status =
  200, $meta = [])` → `{ success: true, message, data, meta }`.
- `response()->error($message = 'Bad Request', $status = 400, $errors = [],
  $debug = null)` → `{ success: false, message, errors }` (with `debug` only when
  `app.debug` is on).
- `response()->message($message, $status = 200)` → a bodyless acknowledgement
  (the success envelope with `data: null`).
- `response()->pdf($pdf, $fileName = 'document.pdf', $download = false)` →
  `application/pdf` with an `inline`/`attachment` `Content-Disposition`.

```php
return response()->success($user, 'Created', 201);
return response()->error('Validation failed', 422, $errors);
return response()->pdf($binary, 'invoice.pdf', download: true);
```

## Factory macro

`withoutEvents()` — flush model event listeners for the factory chain.

## IDE autocomplete (macro stub)

Because every macro above is registered at runtime via `Macroable::macro()` /
`Factory::mixin()`, IDEs and static analysers cannot see them — you get no
autocomplete and no parameter hints. The package ships a committed stub at
[`ide-helper/_ide_helper_macros.php`](../ide-helper/_ide_helper_macros.php) that
re-opens each Illuminate namespace and declares every macro as a `@method` /
`@method static` tag on the real class. PhpStorm and VS Code (Intelephense)
**merge** those tags onto the framework class when indexing, restoring full
autocomplete and signatures.

### Regenerating the stub

The stub is generated from the **live, registered** macros by the
`laranail::toolkit.ide-helper-macros` command (alias `ide-helper:macros`). Run
it after adding, renaming or removing a macro to refresh the committed file:

```bash
php artisan laranail::toolkit.ide-helper-macros
# alias:
php artisan ide-helper:macros

# write to a custom location (absolute, or relative to the base path):
php artisan ide-helper:macros --path=ide-helper/_ide_helper_macros.php
```

Unlike the legacy `ide-helper:macros` (which walked a static class list), this
reflects the macros actually registered at boot on every macroable target
(`Str`, `Stringable`, `Collection`, `Arr`, the query / Eloquent builders,
`Request`, `Carbon`, the response factory) plus the
`Factory::withoutEvents()` mixin, so
the stub can never list a macro the toolkit does not register. The
`IdeHelperStubTest` drift test still guards the committed file in both
directions.

The stub is a **static aid only** — it is deliberately **not** loaded at runtime:

- it is **not** listed in `composer.json` `autoload.files`;
- it lives **outside** the `src/` PSR-4 root, so it is never autoloaded;
- IDEs parse it; they never execute it, so the re-declared classes never collide
  with the framework's real ones.

> **Do not** add it to `composer autoload` and do not `require` it — that would
> redeclare core classes and fatal. If your IDE does not auto-index the project
> root, point it at the `ide-helper/` directory (PhpStorm:
> *Settings → PHP → Include Path*; or rely on barryvdh-style ide-helper tooling
> that discovers `_ide_helper*.php` files).

A drift test (`tests/Unit/Laravel/Macros/IdeHelperStubTest.php`) parses the stub's
`@method` names and asserts they match the registered macros **exactly in both
directions**, so the stub can never silently go stale: add a macro and the build
fails until the provider, the registration inventory, and the stub all agree.

## Blade directives

Registered eagerly by `Providers\BladeServiceProvider`. Only directives with
**no native Laravel equivalent** are included. Conditional directives ship with
their matching `@end…` close tag.

### Conditionals & control flow

| Directive | Purpose |
|-----------|---------|
| `@istrue($v)` / `@isfalse($v)` / `@isnull($v)` / `@isnotnull($v)` (+ `@end…`) | Null-safe truthiness / null checks. |
| `@routeis('pattern')` / `@routeisnot('pattern')` (+ `@end…`) | `fnmatch` current-route-name checks. |
| `@activeifroute('pattern')` | Echo `active` when the current route name matches the pattern. |
| `@instanceof($var, Class)` / `@typeof($var, 'type')` (+ `@end…`) | `instanceof` / `gettype` assertions. |
| `@haserror('field') … @endhaserror` | Block shown only when the field has a validation error. |
| `@repeat(n) … @endrepeat` | Loop N times. |
| `@returnifempty($value)` | Bail out of the view when the value is empty. |

### Form value helpers

| Directive | Purpose |
|-----------|---------|
| `@inputvalue('field'[, $default])` | Echo the `old()` / model value, escaped with `e()`. |
| `@optionvalue('field', $value)` | Echo `selected` when the old/model value matches `$value`. |
| `@selectedif($condition)` | Echo `selected` when the condition is truthy. |
| `@checkboxvalue($value)` | Echo `checked` when truthy. |
| `@checkboxvaluefromarray($id, $array)` | Echo `checked` when the model id is present in the array. |

### Assets, icons & output

| Directive | Purpose |
|-----------|---------|
| `@fa` `@fas` `@far` `@fal` `@fab` `@fad` `@mdi` `@glyph` `@bi` | Icon shorthands (Font Awesome variants, Material Design, Glyphicons, Bootstrap Icons), each `('icon'[, 'extra-classes'])` rendering an `<i>` tag. |
| `@addstyle('href') … @endaddstyle` | Emit a `<link>` for a stylesheet URL, or wrap an inline `<style>` block. |
| `@addscript('src') … @endaddscript` | Emit a `<script src>`, or wrap an inline `<script>` block. |
| `@inline('file')` | Inline a file read from `public_path()`. |
| `@base64image($path)` | Inline an image file as a base64 data URI. |
| `@window('name', $value)` | Expose a PHP value onto `window` in JS. |
| `@nl2br($text)` | Convert newlines to `<br>`. |
| `@dataAttributes($array)` | Render an array as `data-key="value"` attributes. |

```blade
@routeis('admin.*')
    <span class="badge">admin</span>
@endrouteis

<i class="@activeifroute('posts.*')">@fa('rss')</i>

<input name="title" value="@inputvalue('title')">
@haserror('title') <span class="error">@enderror @endhaserror</span>
```

[← Docs index](../README.md#documentation)

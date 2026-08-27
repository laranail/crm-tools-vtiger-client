# API middleware & `BaseRequest`

The toolkit ships a small, **opt-in** API layer for JSON endpoints:

| Class | Alias | What it does |
|-------|-------|--------------|
| `Http\Middleware\ApiRequestMiddleware` | `laranail-toolkit.api-request` | Recursively rewrites **incoming** request keys to `snake_case`. |
| `Http\Middleware\ApiResponseMiddleware` | `laranail-toolkit.api-response` | Wraps the **outgoing** JSON in a standard envelope and rewrites data keys to `camelCase`. |
| `Http\Requests\BaseRequest` | — | A `FormRequest` base that sanitizes every string input before validation. |
| `Http\Requests\ApiRequest` | — | `BaseRequest` subtype that returns a JSON 422 envelope on validation failure. |
| `Http\Middleware\EmailObfuscatorMiddleware` | `laranail-toolkit.email-obfuscate` | HTML-entity–encodes email addresses in non-JSON responses (anti-harvesting). |
| `Http\Middleware\ApiMiddleware` | — | Abstract base shared by the two middleware (the recursive key walker). |
| `Http\Concerns\MutatesPayloadKeys` | — | Reusable trait for the `snake_case ⇄ camelCase` key conversion. |
| `Http\Contracts\HttpStatusInterface` | — | HTTP status-code constants + reason-phrase map used for `meta`. |

None of these is registered on the global middleware stack — you attach them per
route or group.

## Request / response middleware

```php
Route::middleware(['laranail-toolkit.api-request', 'laranail-toolkit.api-response'])->group(function () {
    Route::get('/users/{user}', [UserController::class, 'show']);
});
```

`laranail-toolkit.api-request` converts a camelCase JSON body (`{"firstName": "Jane"}`) into
snake_case (`first_name`) so it lines up with Laravel's validation / `$fillable`
conventions. `laranail-toolkit.api-response` does the reverse on the way out and wraps the body:

```json
{
  "success": true,
  "message": "OK",
  "meta": { "code": 200, "status": "success" },
  "data": { "userId": 7, "displayName": "Jane" }
}
```

When the original response is a `LengthAwarePaginator` (or a resource collection
wrapping one), a `meta.pagination` block is added with the same field names as
`ApiResponseTrait::paginatedResponse()` (`total`, `count`, `per_page`,
`current_page`, `total_pages`), and the items are unwrapped into `data`.

### Envelope shape is consistent with `ApiResponseTrait`

The middleware produces the **same** top-level shape
(`success` / `message` / `data` / `meta`) as the
[`ApiResponseTrait`](traits.md) helpers. Use the **trait** when you control the
controller and want to build the envelope explicitly per action; use the
**middleware** when you want to envelope existing handlers (e.g. a legacy API
group) without touching each one. The snake/camel key walker lives in the shared
`MutatesPayloadKeys` concern so neither path duplicates it.

> **This middleware transforms the payload of every response on the routes it is
> attached to.** That is why it is opt-in (an alias you add per route/group)
> rather than a global kernel entry.

### Hardening

`laranail-toolkit.api-response` decodes the response body with `json_decode` guarded by
`json_last_error()`. If the body is **not** valid JSON (e.g. a streamed file or
an HTML error page that slipped through), the response is returned **untouched**
— it is never corrupted, and the middleware never fatals. Non-`JsonResponse`
responses (views, redirects, streams) also pass straight through.

### Customising

Both middleware extend `ApiMiddleware`; override `mutateKey()` to change the
casing convention, or `hook()` to mutate the request/response further. The
optional positional middleware parameters rename the envelope tags:
`->middleware('laranail-toolkit.api-response:meta,data,pagination')`.

## `BaseRequest`

`BaseRequest` is an abstract `FormRequest` that sanitizes input in
`prepareForValidation()` (always on — defence in depth):

- Every string field: `strip_tags` + `trim`.
- `*email*` → lowercased.
- `*username*` → ASCII handle (`[a-z0-9_-]`), lowercased.
- `*url*` → `FILTER_SANITIZE_URL`.
- `db_*` → conservative identifier charset.
- `*name*` → **Unicode-aware**: keeps letters in any script, combining marks,
  spaces, apostrophes and hyphens. International names (`José`, `O'Brien`,
  `Müller`, `Renée-Claire`, `Đặng`, `Светлана`, `李明`) survive intact while
  HTML and punctuation noise are stripped.

```php
use Simtabi\Laranail\Toolkit\Http\Requests\BaseRequest;

class StoreUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['email' => 'required|email', 'full_name' => 'required|string'];
    }
}
```

> **Note on the legacy port:** the original `BaseRequest` discarded the return
> value of its field-specific sanitizer (the rules never actually applied) and
> used `[^a-zA-Z\s'-]` for names, which corrupted accented/non-Latin names. Both
> are fixed here. For a JSON-error envelope on failed validation (the legacy
> `ApiRequest`), override `failedValidation()` or use `ApiResponseTrait`.

## `ApiRequest`

`ApiRequest extends BaseRequest` — it inherits **all** of `BaseRequest`'s
always-on input sanitization and adds one thing: a JSON error envelope. Where
`BaseRequest` falls back to Laravel's default (redirect / HTML) flow on a failed
validation, `ApiRequest` overrides `failedValidation()` to throw a
`ValidationException` carrying a **JSON response at HTTP 422**:

```json
{
  "success": false,
  "message": "The provided data failed validation.",
  "errors": { "email": ["The email field is required."] }
}
```

Reach for it on JSON API endpoints; use the plain `BaseRequest` for HTML forms.

```php
use Simtabi\Laranail\Toolkit\Http\Requests\ApiRequest;

class StoreUserRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['email' => 'required|email', 'full_name' => 'required|string'];
    }
}
```

## `laranail-toolkit.email-obfuscate` middleware

`EmailObfuscatorMiddleware` (alias `laranail-toolkit.email-obfuscate`) is a lightweight
anti-harvesting measure: on the way out it replaces every email address in the
response body with its HTML-entity encoding (each character as `&#<codepoint>;`).
Browsers render the entities as the original text, but naive scrapers see only
entity noise.

```php
Route::middleware('laranail-toolkit.email-obfuscate')->group(function () {
    Route::get('/team', [TeamController::class, 'index']);
});
```

It is **opt-in** and reads no config. JSON responses are skipped (the
`Content-Type` is checked for `json`) so structured API payloads are never
mangled — only HTML/text responses are rewritten.

[← Docs index](../README.md#documentation)

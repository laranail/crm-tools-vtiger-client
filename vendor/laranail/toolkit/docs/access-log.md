# Access log middleware

`AccessLogMiddleware` records incoming requests to the `access_logs` table. It is
registered under the route-middleware alias **`laranail-toolkit.access-log`** and runs in the
`terminate` phase, so it never adds latency to the response.

## Usage

```php
// routes/web.php or routes/api.php
Route::middleware('laranail-toolkit.access-log')->group(function () {
    Route::get('/dashboard', DashboardController::class);
});
```

Apply it globally by adding `'laranail-toolkit.access-log'` to a middleware group in your
application's bootstrap/HTTP kernel.

## What is stored

Each request persists an `AccessLog` row:

| Column | |
|--------|---|
| `ip` | Client IP. |
| `method` | HTTP method. |
| `url` | Request URL (query string stripped). |
| `user_agent` | User-Agent header. |
| `request_data` | Request payload, **redacted** (cast to `array`). |

Run the published migration first:

```bash
php artisan vendor:publish --tag=laranail::toolkit-migrations
php artisan migrate
```

## Configuration

```php
// config/laranail/toolkit.php
'access_log' => [
    'enabled' => env('LARANAIL_ACCESS_LOG_ENABLED', true),
    'redact'  => null, // null = use the built-in deny-list
],
```

Set `enabled` to `false` to keep the alias available but skip persistence. Set
`redact` to an array of keys to replace the default deny-list with your own.

## Security: redaction

Sensitive request keys are redacted before storage. When `redact` is `null` (or
not an array), the middleware falls back to `SecurityData::redactKeys()` — the
`redact_keys` set from the merged security data (`config('laranail.toolkit.security')`):

```
password, password_confirmation, current_password, token, _token, secret,
authorization, api_key, access_token, refresh_token, credit_card, card_number,
cvv, ssn
```

Redaction is **case-insensitive** and applied **recursively** to nested arrays,
so secrets in deeply-nested payloads are scrubbed too. Any exception while
logging is caught and reported — logging never breaks the request.

[← Docs index](../README.md#documentation)

# Traits

Drop-in traits under `Simtabi\Laranail\Toolkit\Traits` for models and
controllers.

## ApiResponseTrait

Standardised JSON responses for API controllers. Use it directly from the
package (it is not published — `use` it on a controller).

```php
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;

class PostController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->successResponse($posts, 'Posts fetched.');
    }
}
```

| Method | Signature |
|--------|-----------|
| `successResponse` | `(mixed $data = null, string $message = 'Request successful.', int $statusCode = 200, array $meta = []): JsonResponse` |
| `errorResponse` | `(string $message = 'Something went wrong.', int $statusCode = 500, array $errors = [], mixed $debug = null): JsonResponse` (debug only when `app.debug`) |
| `exceptionResponse` | `(\Throwable $e, int $statusCode = 500): JsonResponse` (logs + centralised handling) |
| `paginatedResponse` | `($paginator, string $message = 'Data fetched successfully.'): JsonResponse` |

## Auditable

Automatic change history for an Eloquent model. Hooks `created`, `updated`, and
`deleted`, writing rows to the **`model_audits`** table (`model_type`,
`model_id`, `event`, `old_values`, `new_values`, `user_id`, timestamps).

```php
use Simtabi\Laranail\Toolkit\Traits\Auditable;

class Post extends Model
{
    use Auditable;
}
```

**Security:** attributes listed in the model's `$hidden` (passwords, tokens, …)
are masked as `[REDACTED]` before being persisted to the audit table.

## FileProcessingTrait

Upload / fetch / delete files on the configured filesystem with
directory-traversal protection (via `Traits\FilePathGuard`).

```php
use Simtabi\Laranail\Toolkit\Traits\FileProcessingTrait;

$name  = $this->uploadFile($request->file('doc'), 'uploads');
$names = $this->uploadFiles($request->file('docs'));
$body  = $this->getFile($name, 'uploads');
$this->deleteFile($name, 'uploads');
$this->deleteFiles($names);
```

## HasFormatters

Presentation helpers built on native `Carbon` / `Str`.

```php
use Simtabi\Laranail\Toolkit\Traits\HasFormatters;

$model->formattedCreatedAt();          // default 'm/d/Y h:i:s a'
$model->formattedUpdatedAt('Y-m-d');
$model->formattedFullName();           // first_name + last_name
$model->formattedUsername();           // "@handle" or false
$model->excerpt(150);                  // truncated `content`
$model->formattedContent(['strip_html' => true, 'truncate' => 200]); // cleaned `content`
$model->formatAddress(['street' => '1 A St', 'city' => 'Town', 'state' => 'ST', 'zip' => '00001']);
$model->formatAddressLine('1 A St', 'Apt 2');        // "1 A St, Apt 2"
$model->formatCityStateZip('Town', 'ST', '00001');   // "Town, ST 00001"
$model->suggestUsername('Imani', 'Manyara', column: 'username'); // unique handle suggestion
```

## HasAuth

Thin accessor that delegates authentication context to a per-instance
[`AuthenticationContextService`](utilities.md#authenticationcontextservice)
resolved (and memoised) from the container — useful on a service object that
needs to read the current user/guard without pulling in the `Auth` facade.

```php
use Simtabi\Laranail\Toolkit\Traits\HasAuth;

class ReportBuilder
{
    use HasAuth;

    public function build(): void
    {
        if ($this->isAuthenticated('web')) {
            $userId = $this->getUserId();      // int|string|null
            $email  = $this->getUserEmail();   // ?string
            $user   = $this->getUserProperty(); // resolved Authenticatable|Model|null
        }
    }
}

// Override the context fluently (each setter returns $this):
$builder->setGuard('api')->setUserId(42)->setUserEmail('a@b.test');
```

## HasErrorStorage

Thin accessor over a per-instance
[`ErrorStorageService`](utilities.md#errorstorageservice) — a fluent, key-based
error bag for non-validation flows (importers, batch jobs, domain services).

```php
use Simtabi\Laranail\Toolkit\Traits\HasErrorStorage;

class ImportRunner
{
    use HasErrorStorage;

    public function run(): bool
    {
        $this->addError('row.5', 'Missing email');
        // ... setErrors([...]) | getErrors('row.5') | getErrorCount()

        if ($this->hasErrors()) {
            report($this->getFirstError());
            $this->clearErrors();

            return false;
        }

        return true;
    }
}
```

## HasGuzzleConfig

One-method accessor exposing a fresh
[`HttpConfigurationService`](utilities.md#httpconfigurationservice) so a class
can build a Guzzle/HTTP-client config seeded from `config('laranail.toolkit.http.*')`.

```php
use Simtabi\Laranail\Toolkit\Traits\HasGuzzleConfig;

class WeatherClient
{
    use HasGuzzleConfig;

    public function client(): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client(
            $this->httpConfig()->setRequestTimeout(10)->toGuzzleConfig()
        );
    }
}
```

## RunsConditionally

Context-aware conditional execution without instantiating
[`ConditionalRunner`](utilities.md#conditionalrunner) by hand. Each helper queues
one callback against a context predicate, runs it immediately, and returns the
callback's result (or `null` when the context does not hold).

```php
use Simtabi\Laranail\Toolkit\Traits\RunsConditionally;

class Notifier
{
    use RunsConditionally;

    public function ping(): void
    {
        $this->runForApi(fn () => $this->jsonPing());
        $this->runForWeb(fn () => $this->flashPing());
        $this->runInConsole(fn () => $this->line('pinged'));
        $this->runOutsideConsole(fn () => $this->flashPing());
        $this->runWhenAuthenticated(fn () => $this->logUserPing(), guard: 'web');
        $this->runWhenGuest(fn () => $this->promptLogin());
        $this->runForRole('admin', fn () => $this->auditPing());
    }
}
```

For multiple chained predicates, drive the runner directly via
`$this->conditional()` (see [ConditionalRunner](utilities.md#conditionalrunner)).

## FilePathGuard

Dependency-free path-shape guard rejecting directory-traversal segments (`..`)
and null bytes before a path string reaches a lower-level file API. Used by
[`FileProcessingTrait`](#fileprocessingtrait); mix it in directly where you
handle caller-supplied paths.

```php
use Simtabi\Laranail\Toolkit\Traits\FilePathGuard;

class ExportWriter
{
    use FilePathGuard;

    public function write(string $relativePath, string $body): void
    {
        $safe = $this->assertSafePath($relativePath); // throws on ".." / null byte
        Storage::put($safe, $body);
    }
}

// Or probe without throwing:
$this->isSafePath('../../etc/passwd'); // false
```

It validates the *shape* of a path only — it does not touch the filesystem or
re-implement Laravel's `Storage` abstraction.

[← Docs index](../README.md#documentation)

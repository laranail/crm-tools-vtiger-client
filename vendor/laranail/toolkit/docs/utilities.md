# Utilities

The toolkit's focused helper classes are split by responsibility:

- **Services** (`Simtabi\Laranail\Toolkit\Services`) — stateful, interface-backed
  helpers bound in the container. Resolve them with `app(...)`, constructor
  injection, or their contract interface.
- **Support** (`Simtabi\Laranail\Toolkit\Support`) — pure, static helpers with no
  container binding; call their static methods directly.

All of these are used **directly from the package** — there is no publish step;
resolve a service by its contract (or `Toolkit::*()`), and call a Support helper
statically.

## Services (stateful, interface-backed)

### CacheService

Taggable-store caching with a configured default expiration, key namespacing and
a resilient (log-and-fall-back) failure mode. Bound to
`Services\Contracts\CacheRepositoryInterface`.

```php
$cache = app(CacheService::class);
$value = $cache->cache('key', $data, minutes: 30, tags: ['reports']);
$cache->get('key', default: null);
$cache->forget('key');
$cache->remember('key', fn () => compute(), minutes: 30);
$cache->rememberForever('key', fn () => compute());
$cache->put('key', $data, minutes: 30);
$cache->many(['a', 'b']);                 // ['a' => ..., 'b' => ...]
$cache->increment('hits');                // and ->decrement('hits')
$cache->tags(['reports'])->get('key');    // fluent tag scoping
```

| Method | Effect |
|---|---|
| `cache(string $key, mixed $value, ?int $minutes = null, array $tags = []): mixed` | Read-through write: store + return `$value`. |
| `get(string $key, mixed $default = null): mixed` / `put(string $key, mixed $value, ?int $minutes = null): bool` | Read / write a single key. |
| `remember(string $key, Closure $callback, ?int $minutes = null): mixed` / `rememberForever(string $key, Closure $callback): mixed` | Cache-or-compute, with/without expiry. |
| `many(array $keys): array` | Fetch several keys at once. |
| `increment(string $key, int $value = 1): int` / `decrement(string $key, int $value = 1): int` | Atomic counters. |
| `forget(string $key): bool` | Delete a key. |
| `tags(array $tags): static` | Return a tag-scoped clone (chain a read/write after it). |

Constructor: `__construct(int $defaultExpiration, array $defaultTags, ?LoggerInterface $logger = null, string $namespace = '')`
— wired from `config('laranail.toolkit.cache')`. All operations log-and-fall-back on a
store failure; the optional `namespace` is prefixed to every key.

### LogService

Structured logging with context, keyed off the `LogLevel` enum
(`Debug`, `Info`, `Warning`, `Error`, `Critical`). Each record is enriched with a
timestamp and the current environment. Bound to
`Services\Contracts\LoggerServiceInterface` (and registered as a singleton).

```php
$log = app(LogService::class);
$log->debug('Cache miss', ['key' => $key]);
$log->info('User registered', ['id' => $user->id]);
$log->warning('Low disk', ['free' => $bytes]);
$log->error('Import failed', ['file' => $name], channel: 'imports');
$log->critical('Payment gateway down');
$log->log(LogLevel::Warning, 'Low disk');
$log->exception($throwable);
```

Level methods: `debug()`, `info()`, `warning()`, `error()`, `critical()` (each
`(string $message, array $context = [], ?string $channel = null)`), plus the
enum-keyed `log(LogLevel $level, ...)` and `exception(Throwable $e, ...)`.

### SettingsStore

Read/write dynamic settings persisted as a JSON file on a configured disk — a
runtime store, deliberately separate from Laravel's static `config()`. Keys use
dot notation. Bound to `Services\Contracts\SettingsStoreInterface`.

```php
$settings = app(SettingsStore::class);
$settings->all();
$settings->get('feature.enabled', false);
$settings->has('feature.enabled');
$settings->set('feature.enabled', true);
$settings->forget('feature.enabled');
```

### RateLimiterService

Thin wrapper over Laravel's rate limiter. Bound to
`Services\Contracts\RateLimiterServiceInterface` (named `RateLimiterService` to
avoid colliding with Illuminate's `RateLimiter`).

```php
$limiter = app(RateLimiterService::class);

if ($limiter->attempt("login:{$ip}", maxAttempts: 5, decayMinutes: 15)) {
    // allowed
}

$limiter->remaining($key, 5);
$limiter->availableIn($key);
$limiter->tooManyAttempts($key, 5);
$limiter->attempts($key);            // current attempt count
$limiter->hit($key, decaySeconds: 60);
$limiter->clear($key);
$limiter->getRateLimiter();          // the underlying Illuminate RateLimiter
```

### SchedulerService

Inspect the scheduler and find overdue tasks. Bound to
`Services\Contracts\SchedulerServiceInterface`.

```php
$scheduler = app(SchedulerService::class);
$summary   = $scheduler->getScheduleSummary();
$overdue   = $scheduler->hasOverdueTasks();
```

### ModelService

Eloquent convenience helpers — building select-box / display lists from models,
sorting a flat parent/child list into a depth-annotated tree, safe model reads,
and observer registration. Reachable via `Toolkit::model()` or `app(ModelService::class)`.

```php
$models = app(ModelService::class);

$models->getFormableUsersList($usersModel);                 // ['id' => 'display-name', ...]
$models->getUsersFromModel($model, keyed: true, asJson: false);
$models->eloquent2selectbox($posts, columnName: 'title', idColumnName: 'id');
$models->sortItemWithChildren($flatNodes);                  // depth-annotated parent→child order
$models->getModelItem($model, 'profile.phone', default: null); // dot-path read
$models->registerModelObserver(Post::class, PostObserver::class);
$models->concatName('users');                               // CONCAT(first_name,' ',last_name) AS name expression
```

| Method | Effect |
|---|---|
| `getFormableUsersList(Model $usersModel): array` | `id => display-name` list from a users model. |
| `getUsersFromModel(Model $model, bool $keyed = true, bool $asJson = false): array` | Users with a SQL-concatenated `name` column. |
| `eloquent2selectbox(Collection\|Model $data, string $columnName = 'name', string $idColumnName = 'id', ?string $placeholderText = 'Select something', string $emptyDataText = 'Nothing to select'): array` | Collection/model → select-box array. |
| `sortItemWithChildren(array\|Collection $list, array &$result = [], int\|string\|null $parent = null, int $depth = 0): array` | Flat nodes → depth-annotated parent→child order. |
| `getModelItem(object $model, string $key, mixed $default = null): mixed` | Dot-path read with default fallback. |
| `registerModelObserver(string $modelClass, string $observerClass): void` | Register an observer when both classes exist. |
| `concatName(string $table, ?string $connection = null): Expression` | `CONCAT(first_name,' ',last_name) AS name` for a table. |

### AuthenticationContextService

Fluent, per-instance authentication context across guards — set/read the active
user email, id and guard, and resolve the current user. Bound to
`Services\Contracts\AuthenticationContextServiceInterface`; also reachable via
`Toolkit::auth()` or the [`HasAuth`](traits.md#hasauth) trait.

```php
$auth = app(AuthenticationContextServiceInterface::class);

$auth->setGuard('web')->setUserId(42);
$auth->getUser();                  // Authenticatable|Model|null
$auth->isAuthenticated('web');     // bool
$auth->getCurrentUserId();         // int|string|null (live, from the guard)
```

### ErrorStorageService

Fluent, key-based error bag for non-validation flows (importers, batch jobs,
domain services). Bound to `Services\Contracts\ErrorStorageServiceInterface`;
also reachable via the [`HasErrorStorage`](traits.md#haserrorstorage) trait.

```php
$errors = app(ErrorStorageServiceInterface::class);

$errors->addError('row.5', 'Missing email')->setErrors(['file' => 'unreadable']);
$errors->hasErrors();        // true
$errors->getErrors('row.5'); // ['Missing email']
$errors->getErrorCount();    // 2
$errors->getFirstError();    // 'Missing email'
$errors->clearErrors();
```

### HttpConfigurationService

Fluent builder for Guzzle/HTTP-client configuration, seeded once from
`config('laranail.toolkit.http.*')`. Bound to
`Services\Contracts\HttpConfigurationServiceInterface`; also reachable via the
[`HasGuzzleConfig`](traits.md#hasguzzleconfig) trait.

```php
$http = app(HttpConfigurationServiceInterface::class);

$config = $http
    ->setPersistConnection(true)
    ->setRequestTimeout(10)
    ->setMaxRetries(3)
    ->setCacheTtl(60)
    ->toGuzzleConfig();        // array ready for new GuzzleHttp\Client(...)
```

### RouteService

Request-scoped routing helpers — current-route checks, active-CSS-class
resolution for nav menus, URL-segment inspection and query-string reads. Bound
to `Services\Contracts\RouteServiceInterface`; also reachable via `Toolkit::route()`.

```php
$route = app(RouteServiceInterface::class);

$route->isCurrentRoute('dashboard');                 // bool
$route->getActiveCssClassForRoute('dashboard');      // 'active' on a match, else ''
$route->getActiveMenuClassName(['users.*', 'roles.*']); // active for a group
$route->isUrlSegment('admin', position: 0);
$route->getInputValueFromQueryString('q');           // escaped query value
$route->getCurrentRouteInfo();                        // object {name, parameters, ...}
```

| Method | Effect |
|---|---|
| `getAppUrl(): string` | The configured application URL. |
| `isCurrentRoute(string $routeName): bool` / `isRoute(string $routeName): bool` | Whether the current request matches a route name. |
| `getActiveCssClassForRoute(string $routeName, string $class = 'active'): string` / `getActiveCssClass(...)` | The class on a route match, else `''`. |
| `getActiveMenuClassName(string\|array $route, string $className = 'active'): string` | Active class for a single route or a group of route patterns. |
| `isUrlSegment(string $segment, int $position = 0): bool` / `isLastUrlSegment(string $segment): bool` / `isUrlRequestSegment(string $segment, int $position = 0): bool` | URL-segment inspection by position / last / request. |
| `isRequestParameter(string $key, mixed $value): bool` / `getRequestParameterValue(string $key): mixed` | Test / read a route+query parameter. |
| `getActiveCssClassForUrlParameter(string $key, mixed $value, string $class = 'active'): string` | Active class when a parameter matches. |
| `isRequestOnPage(string $link, string $type = 'name'): bool` / `isRequest(string $request, string $class = '', bool $returnBool = false): bool\|string` | Page/route matching helpers (by name or URL). |
| `getCurrentRouteInfo(): object` / `getRouteNameFromUrl(string $url): ?string` | Current-route metadata / resolve a route name from a URL. |
| `getInputValueFromQueryString(string $name): string` | Escaped query-string value. |

### ValidationService

View-layer validation helpers — escaped error-bag messages, error CSS classes,
old-input / model-value fallbacks for re-populating forms. Bound to
`Services\Contracts\ValidationServiceInterface`; also reachable via
`Toolkit::validation()`.

```php
$v = app(ValidationServiceInterface::class);

$v->getErrorBagMessage('email');         // e()-escaped first error or ''
$v->getHasErrorCssClass('email');        // 'is-invalid' style class when errored
$v->oldInput('email', $user);            // old() → model → default fallback
$v->getCheckboxStatus($model->active, 'active'); // 'checked' | null
$v->isValidDatabaseConnection('settings');
$v->getErrorBagMessageClass('email', 'text-danger'); // class string when the field errored
$v->fetchModelData($model, 'email', default: '');    // model attribute with a fallback
```

## Support (pure, static)

### AuthHelper (per guard)

Typed accessor for a single named guard (named `AuthHelper` to avoid the `Auth`
facade collision). Built via the `for()` factory, not the container.

```php
$auth = AuthHelper::for('web');

$auth->check();   // bool
$auth->user();    // ?Authenticatable
$auth->id();      // int|string|null
$auth->email();   // ?string
$auth->guard();   // 'web'
```

> Session-backed filter-key + JavaScript-cookie helpers now live in the
> injectable `SessionService` (see [helpers.md](helpers.md#session-service)),
> not in a static utility.

### Environment

Environment predicates over the app's own resolver — adds the named buckets and
`isNonProduction()` aggregate the framework does not ship.

```php
Environment::isLocal();            // local / staging / development
Environment::isProduction();
Environment::isNonProduction();
Environment::isTesting();
Environment::isEnvironment(['beta', 'alpha']);
Environment::isRunningUnitTests();
Environment::current();            // 'testing'
```

### CollectionFilter

Filter a collection by operator (`equals`, `contains`, `starts_with`,
`ends_with`).

```php
$matches = CollectionFilter::filter($collection, 'name', 'contains', 'lara');
```

### QueryParameters

Parse and filter request query params against an allow-list.

```php
$params = QueryParameters::parse($request, ['status', 'sort', 'page']);
```

### FeatureToggle

```php
FeatureToggle::isEnabled('example_feature'); // bool
```

Honours per-user and per-environment overrides — see [configuration](configuration.md).

### RequirementsDiagnostics

Environment / requirements probes that also back the toolkit's
`php artisan about` section (wired in `boot()`). Use it to check PHP version,
extensions, writable storage and free disk space.

```php
$diag = new RequirementsDiagnostics();

$diag->checkPhpVersion();                     // ['version' => '8.4.x', 'meets' => true, ...]
$diag->checkExtensions(['pdo', 'mbstring']);  // which of the given extensions are loaded
$diag->missingExtensions(['gd', 'imagick']);  // which are missing
$diag->checkWritableDirectories([storage_path()]);
$diag->isDirectoryWritable(storage_path('logs'));
$diag->checkDiskSpace(base_path(), minimumBytes: 100 * 1024 * 1024);
$diag->diskSpace([storage_path()], minMb: 200, recommendedMb: 1024, warnAtPercent: 90);
$diag->toAboutArray();                        // the `php artisan about` payload
```

| Method | Effect |
|---|---|
| `checkPhpVersion(?string $minimumVersion = null): array` | PHP version vs. the (default) floor. |
| `checkExtensions(?array $extensions = null): array` | Which of the given extensions are loaded. |
| `missingExtensions(?array $extensions = null): array` | Which of the given extensions are missing. |
| `checkWritableDirectories(array $paths): array` | Writability of each directory. |
| `isDirectoryWritable(string $path): bool` | Whether a directory (or where it would be created) is writable. |
| `checkDiskSpace(?string $path = null, ?int $minimumBytes = null): array` | Free disk space vs. an optional minimum. |
| `diskSpace(array $paths = [], ?int $minMb = null, ?int $recommendedMb = null, int $warnAtPercent = 90): array` | Free space with minimum / recommended / warning thresholds. |
| `toAboutArray(): array` | The `php artisan about` payload (PHP version, minimum/supported PHP, required extensions, storage writability, free space). |

The toolkit registers this under `php artisan about` automatically, surfacing
**PHP Version**, **Minimum PHP**, **PHP Supported**, **Required Extensions**,
**Storage Writable**, and **Storage Free Space** rows.

### Cast

Safe scalar coercion for `mixed` values (config, request input, decoded JSON)
that strict static analysis refuses to cast directly. Each helper narrows the
value and falls back to the supplied default when it is of an unusable type.

```php
Cast::toString($value, default: '');
Cast::toInt($value, default: 0);
Cast::toFloat($value, default: 0.0);
Cast::toBool($value, default: false);
```

### Config

Typed accessors over Laravel's `mixed`-returning `config()` helper — narrow once,
here, instead of at every call site.

```php
Config::string('app.name', 'Laravel');
Config::int('laranail.toolkit.cache.default_expiration', 60);
Config::bool('app.debug', false);
Config::float('services.rate', 0.0);
Config::array('laranail.toolkit.cache.default_tags', []);
Config::stringList('laranail.toolkit.redaction.keys', []); // drops non-string members
```

### ConditionalRunner

Chainable, context-aware execution: queue callbacks against context predicates
then `run()` them all, collecting the results of the predicates that held. For a
single predicate on a host class, the [`RunsConditionally`](traits.md#runsconditionally)
trait is the shorthand.

```php
$results = ConditionalRunner::make()
    ->whenApi(fn () => 'api')
    ->whenWeb(fn () => 'web')
    ->whenConsole(fn () => 'cli')
    ->whenAuthenticated(fn () => 'user', guard: 'web')
    ->whenRole('admin', fn () => 'admin')
    ->whenRoleIsNot('admin', fn () => 'non-admin')
    ->when($flag, fn () => 'flagged')
    ->run();   // list of results for the predicates that matched
```

### ApiResponder

Public adapter exposing the canonical [`ApiResponseTrait`](traits.md#apiresponsetrait)
JSON envelope so non-controller code (notably the Response macros) can build the
same `{ success, message, data, errors, meta }` shape. Every method forwards to
the trait — there is no duplicate JSON shaping.

```php
$responder = new ApiResponder();

$responder->success($data, 'Fetched.', status: 200, meta: ['page' => 1]);
$responder->error('Validation failed.', status: 422, errors: $bag->toArray());
```

It backs the `response()->success(...)` / `response()->error(...)` /
`response()->message(...)` macros — see [macros](macros.md).

[← Docs index](../README.md#documentation)

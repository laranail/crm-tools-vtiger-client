# Architecture

One client object, four services over a shared session, configured entirely from the environment.

## Components

| Class | Role |
|---|---|
| `VtWsClient` | Entry point. Builds the session and services, logs in, and exposes `invokeOperation()` and `runQuery()`. |
| `Services\Session` | Holds the Guzzle client and credentials, logs in, sends every HTTP request, and logs out after a call unless the connection is persistent. |
| `Services\Entities` | Record-level work: find, create, update, delete, sync. |
| `Services\Modules` | Module metadata: list, describe, typed IDs. |
| `Services\Operations` | Thin wrappers over individual Web Services operations, plus account helpers. |
| `Services\Fetchers` | Collection shortcuts for common modules. |
| `Helpers\Helpers` | Reads configuration and names the cache keys. |
| `Providers\VtWsClientServiceProvider` | Registers config and translations through `laranail/package-tools`, and binds `VtWsClient` as a singleton. |

Every service calls back into `VtWsClient::invokeOperation()`, which runs inside `Session::sessionHandler()`, so login state and logout behaviour live in one place.

## Session lifecycle

The constructor logs in immediately — with the access key or the password, depending on `auth.login_with_access_key` — so resolving the client needs a reachable Vtiger instance. The resulting session (token, expiry, session ID, user ID) is cached for `cache_ttl` seconds under a vendor-scoped cache key. With `persist_connection` off, the session is closed with a `logout` call after each operation.

## Why the names are vendor-scoped

Config keys, translation namespaces, cache prefixes and publish tags are flat global registries, so a sibling package or the consuming application claiming the same bare name silently replaces this one. The package therefore uses:

| Surface | Name |
|---|---|
| Config key | `laranail.crm-tools-vtiger-client` |
| Translation namespace | `laranail/crm-tools-vtiger-client` |
| Config publish tag | `laranail::crm-tools-vtiger-client-config` |
| Cache key prefix | `laranail-crm-tools-vtiger-client` (snake-cased) |

All of these were once the bare `vtiger-rest-api-client`. `tests/Feature/BootTest.php` reads the live registries to keep them scoped. The `VTWSCLIENT_*` environment variables are not yet vendor-prefixed.

## Credits

- [Vtiger Cloud RestApi SDK](https://extend.vtiger.com/sdk/restapi-php), which this client builds on.
- [All contributors](../CONTRIBUTORS.md).

---

[← Docs index](../README.md#documentation)

# Configuration

Twelve settings, all read from `config('laranail.crm-tools-vtiger-client.*')` and each backed by a `VTWSCLIENT_*` environment variable.

## Settings

| Key | Environment variable | Default | Purpose |
|---|---|---|---|
| `base_uri` | `VTWSCLIENT_BASE_URI` | none | URL of the Vtiger instance. |
| `persist_connection` | `VTWSCLIENT_PERSIST_CONNECTION` | `true` | Keep the session open between calls instead of logging out after each one. |
| `request_timeout` | `VTWSCLIENT_REQUEST_TIMEOUT` | `60` | Seconds after which a request times out. |
| `maximum_retries` | `VTWSCLIENT_MAXIMUM_RETRIES` | `10` | Maximum number of retries for a request. |
| `cache_ttl` | `VTWSCLIENT_CACHE_TTL` | `21600` | Seconds the session is cached (21600 = 6 hours). |
| `http_errors` | `VTWSCLIENT_GUZZLE_HTTP_ERRORS` | `true` | Passed to Guzzle: raise on HTTP error responses. |
| `verify` | `VTWSCLIENT_GUZZLE_VERIFY` | `false` | Passed to Guzzle: verify TLS certificates. |
| `throw_errors` | `VTWSCLIENT_THROW_ERRORS` | `false` | Throw runtime errors as `VtWsClientException` instead of collecting them for `getErrors()`. |
| `auth.username` | `VTWSCLIENT_USERNAME` | none | API user name. |
| `auth.access_key` | `VTWSCLIENT_ACCESS_KEY` | none | API user's access key. |
| `auth.password` | `VTWSCLIENT_PASSWORD` | none | API user's password. |
| `auth.login_with_access_key` | `VTWSCLIENT_LOGIN_WITH_ACCESS_KEY` | none (reads as `false`) | Log in with the access key rather than the password. |

> `verify` defaults to `false`, which disables TLS certificate verification. Set `VTWSCLIENT_GUZZLE_VERIFY=true` against any instance reachable over a network you do not control.

## Error handling

With `throw_errors` off, a failed login does not throw: the error is collected and returned by `$client->getErrors()`. Turn it on to fail fast.

---

[← Docs index](../README.md#documentation)

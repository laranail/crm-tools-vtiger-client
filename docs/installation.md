# Installation

Install `laranail/crm-tools-vtiger-client` from its VCS repositories and point it at a Vtiger instance.

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.4.1 \|\| ^8.5` |
| Laravel (`illuminate/support`) | `^13.0` |
| Guzzle | `^7.8 \|\| ^8.0` |
| A Vtiger instance | with Web Services enabled and an API user |

## Add the VCS repositories

The package is not on Packagist. Composer ignores a dependency's own `repositories`, so the root `composer.json` of the consuming application has to declare the package and every non-Packagist package it pulls in:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/crm-tools-vtiger-client" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools" },
    { "type": "vcs", "url": "https://github.com/laranail/toolkit" },
    { "type": "vcs", "url": "https://github.com/laranail/console" }
]
```

Earlier versions also needed `simtabi/pheg` and `simtabi/json-objects`. Neither is required any more; an application that declared them only for this package can drop both entries.

## Require the package

```bash
composer require laranail/crm-tools-vtiger-client:^0.1
```

`VtWsClientServiceProvider` is auto-discovered and binds `VtWsClient` as a singleton. The `VtWsClient` facade alias (`Simtabi\Laranail\CrmTools\VtigerClient\Facades\VtWsClientFacade`) is registered too.

## Set the credentials

Add these to `.env`, replacing the placeholders with your own values:

```dotenv
VTWSCLIENT_BASE_URI="https://crm.example.com"
VTWSCLIENT_USERNAME="your API username"
VTWSCLIENT_ACCESS_KEY="your API access key"
VTWSCLIENT_PASSWORD="your API access password"
VTWSCLIENT_LOGIN_WITH_ACCESS_KEY=true
VTWSCLIENT_PERSIST_CONNECTION=true
VTWSCLIENT_REQUEST_TIMEOUT=60
VTWSCLIENT_MAXIMUM_RETRIES=10
VTWSCLIENT_CACHE_TTL=21600
VTWSCLIENT_GUZZLE_HTTP_ERRORS=true
VTWSCLIENT_GUZZLE_VERIFY=false
VTWSCLIENT_THROW_ERRORS=true
```

Set `VTWSCLIENT_LOGIN_WITH_ACCESS_KEY` explicitly. It has no default in the shipped config, and an unset value reads as `false`, which logs in with the password instead of the access key. Every variable is described in [Configuration](configuration.md).

## Publish the config

Optional — the packaged defaults are read from the environment either way:

```bash
php artisan vendor:publish --tag=laranail::crm-tools-vtiger-client-config
```

The file lands at `config/laranail/crm-tools-vtiger-client.php` and is read under the `laranail.crm-tools-vtiger-client` key.

## Constructing the client logs in

> `VtWsClient`'s constructor calls `Session::login()`, so resolving it from the container makes a request to `VTWSCLIENT_BASE_URI` straight away. Set the credentials before anything resolves the client: a failed login surfaces there, not at the first query.

---

[← Docs index](../README.md#documentation)

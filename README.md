# laranail/crm-tools-vtiger-client

[![Tests](https://github.com/laranail/crm-tools-vtiger-client/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/crm-tools-vtiger-client/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Not published to Packagist, so there is no registry-version badge, and the repository has no static-analysis workflow, so there is no static-analysis badge: see [Install](#install).

> A [Vtiger](https://www.vtiger.com/) [Web Services API](https://wiki.vtiger.com/index.php/Webservices_tutorials) client for Laravel.

Requires PHP `^8.4.1 || ^8.5` on Laravel `^13` (`illuminate/support ^13.0`).

## Install

The package resolves through VCS repositories rather than Packagist; add them to your root `composer.json` first, as described in [Installation](docs/installation.md), then:

```bash
composer require laranail/crm-tools-vtiger-client:^0.1
```

The service provider and the `VtWsClient` facade alias are auto-discovered.

## Quick start

```php
use Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient;

$client = app(VtWsClient::class); // logs in with the VTWSCLIENT_* credentials from .env

$lead = $client->entities->findOne('Leads', ['firstname' => 'Amina', 'lastname' => 'Odhiambo'])
    ?? $client->entities->createOne('Leads', [
        'firstname' => 'Amina',
        'lastname'  => 'Odhiambo',
        'email'     => 'amina.odhiambo@example.com',
    ]);
```

The full walkthrough is in [Getting started](docs/getting-started.md); everything else is in the [documentation index](#documentation).

## <a name="documentation"></a>Documentation

Hosted at **[opensource.simtabi.com/documentation/laranail/crm-tools-vtiger-client](https://opensource.simtabi.com/documentation/laranail/crm-tools-vtiger-client/)**.

### Guides

- [Installation](docs/installation.md) — requirements, VCS repositories, publishing the config.
- [Getting started](docs/getting-started.md) — connect, describe a module, create and look up records.
- [Configuration](docs/configuration.md) — every config key and the `VTWSCLIENT_*` variable behind it.
- [Architecture](docs/architecture.md) — how the client, its services and the session fit together.
- [Release](docs/release.md) — versioning and how a release is cut.

### Reference

- [`VtWsClient`](docs/tools/vtwsclient.md) — the client and its `entities`, `modules`, `operations` and `fetchers` services.

### Recipes

- [Invoke a custom operation](docs/recipes/custom-operations.md) — register a Web Services operation in Vtiger and call it.
- [Retrieve related records](docs/recipes/related-records.md) — list the records related to an entity.
- [Sync changes since a date](docs/recipes/sync-changes.md) — fetch records updated or deleted since a timestamp.

## Contributing & security

Issues, pull requests and stars are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) and [CONTRIBUTORS.md](CONTRIBUTORS.md); report vulnerabilities per [SECURITY.md](SECURITY.md) (`security@simtabi.com`), never in the issue tracker.

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).

# laranail/crm-tools-vtiger-client

[![Tests](https://github.com/laranail/crm-tools-vtiger-client/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/crm-tools-vtiger-client/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Two badges, not four: the package is not on Packagist (`repo.packagist.org` answers 404, checked 2026-10-04), so there is no registry-version badge, and the repository has no static-analysis workflow, so there is no static-analysis badge.

> A [Vtiger](https://www.vtiger.com/) [Web Services API](https://wiki.vtiger.com/index.php/Webservices_tutorials) client for Laravel.

Requires PHP `^8.4.1 || ^8.5` on Laravel `^13` (`illuminate/support ^13.0`).

## Install

The package is not on Packagist. Composer ignores a dependency's own `repositories`, so add the package and its whole `laranail/*` closure to your root `composer.json`, and keep Packagist from answering for the family's names:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/crm-tools-vtiger-client" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools" },
    { "type": "vcs", "url": "https://github.com/laranail/toolkit" },
    { "type": "vcs", "url": "https://github.com/laranail/console" },
    { "type": "composer", "url": "https://repo.packagist.org", "exclude": ["laranail/*"] },
    { "packagist.org": false }
]
```

Then require it:

```bash
composer require laranail/crm-tools-vtiger-client:^0.1
```

The service provider and the `VtWsClient` facade alias are auto-discovered. More in [Installation](docs/installation.md).

## Quick start guide and usage

### Getting started

1. Set the connection in `.env`; the client logs in with these as soon as it is resolved:

   ```dotenv
   VTWSCLIENT_BASE_URI=https://crm.example.com
   VTWSCLIENT_USERNAME=api-user
   VTWSCLIENT_ACCESS_KEY=your-access-key
   VTWSCLIENT_LOGIN_WITH_ACCESS_KEY=true
   ```

   Set `VTWSCLIENT_LOGIN_WITH_ACCESS_KEY` explicitly: unset, it reads as `false` and the client logs in with `VTWSCLIENT_PASSWORD` instead of the access key. The other `VTWSCLIENT_*` keys (timeouts, retries, cache TTL, TLS verification, error throwing) are listed in [Configuration](docs/configuration.md).

2. Optionally publish the config, or the translations:

   ```bash
   php artisan vendor:publish --tag=laranail::crm-tools-vtiger-client-config
   php artisan vendor:publish --tag=laranail::crm-tools-vtiger-client-translations
   ```

   The config lands at `config/laranail/crm-tools-vtiger-client.php` and is read under `laranail.crm-tools-vtiger-client`. There are no migrations or views.

### Usage

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

Describe a module to see which fields `findOne()` and `createOne()` accept:

```php
print_r($client->modules->getOne('Leads')); // label, permissions and every field, required or optional
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

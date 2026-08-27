# laranail/package-tools

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/package-tools.svg)](https://packagist.org/packages/laranail/package-tools)
[![Tests](https://github.com/laranail/package-tools/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/package-tools/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/package-tools/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/package-tools/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Runtime base library for building Laravel packages — a fluent `Package` builder and an abstract `PackageServiceProvider` (in the spirit of `spatie/laravel-package-tools`), plus attribute-driven discovery, declarative + array-batch registration helpers, `package-tools.*` Artisan commands, abstract HTTP controllers, and a testing harness.

Requires PHP `^8.4.1 || ^8.5` on Laravel `^13`.

## Install

```bash
composer require laranail/package-tools
```

## <a name="documentation"></a>Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/package-tools](https://opensource.simtabi.com/documentation/laranail/package-tools/)** — getting started, the fluent builder, declarative registration & batch helpers (scheduled commands, policies, morph maps, about sections, doctor checks, install commands), the fluent provider builders (force HTTPS, locale, pagination, config decoration, gates, route groups & bindings, events), the `PackageAction{Started,Succeeded,Failed}` lifecycle events with the `PackageActions` facade, the failure-handling standard (classify by consequence — Critical fails fast, Degradable reports & continues — with a `boot:health` doctor gate), fluent rate limiters, the seeding subsystem (`db:seed`-time bundles, opt-in autorun after migrations, background/scheduled execution with completion events), per-package logging via `$package->log()`, the fluent runtime config manager, attribute discovery, the package registry and its `packages` command (every package built on the toolkit, what each claimed, and whether two claimed the same name), the caller-relative path resolver (replacing `__DIR__ . '/../..'` with an explicit level count and direction), the public-name registries (why views and translations take `laranail/atlas::` while Blade component tags cannot), the Artisan commands, HTTP controllers, provenance/SBOM, asset publishing & orphan pruning (a `--force` that overwrites, a `--clean` that deletes, and a guard that refuses a publish root of `public`), seeder file helpers, the testing harness, configuration, and the release process.

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).

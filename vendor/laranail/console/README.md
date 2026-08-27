# laranail/console

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/console.svg)](https://packagist.org/packages/laranail/console)
[![Tests](https://github.com/laranail/console/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/console/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/console/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/console/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> A Rich-class console toolkit for Laravel — fluent terminal **output** (formatter, spinners, progress bars, boxes, trees, tables, charts, a Markdown + typography layer) and **input** (a `laravel/prompts` wrapper with a form builder and 26 validators).

Requires PHP `^8.4.1` on Laravel `^13` (Symfony 8).

## Install

```bash
composer require laranail/console
```

The service provider, the `Console`/`Prompter` facades, and the `prompter()` helper are auto-discovered.
Publish config/lang if you want to customise them:

```bash
php artisan vendor:publish --tag=console-config
php artisan vendor:publish --tag=console-lang
```

## Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/console](https://opensource.simtabi.com/documentation/laranail/console/)** — installation, getting started, the design system, architecture, configuration, and per-subsystem reference (theming, colours, typography, Markdown, charts, widgets, banners, panels, menus, the full-screen TUI, prompts & forms, and more).

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).

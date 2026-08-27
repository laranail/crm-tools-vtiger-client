# Changelog

All notable changes to `laranail/console` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-15

### Changed

- **The config key is `laranail.console`,** published to `config/laranail/console.php`. Every read
  moves with it — `config('console.theme.preset')` is now
  `config('laranail.console.theme.preset')`. Laravel's config repository is a flat map and `console`
  is a name an application could plausibly use for its own file.

- **Publish tags are vendor-scoped:** `console-config` → `laranail::console-config`, `console-lang`
  → `laranail::console-lang`.

### Fixed

- **Published translations went to the lang root** rather than to
  `lang/vendor/laranail-console`, which is where the namespaced loader looks — so every published
  override was silently ignored while the packaged default kept answering.

Initial public release.

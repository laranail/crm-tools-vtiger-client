# Release process

`laranail/toolkit` is released **tag-driven**: pushing a `vX.Y.Z` tag triggers the release workflow, which publishes the GitHub Release with the tagged version's `CHANGELOG.md` block as its body.

## Versioning & stability

Pre-1.0, the package follows SemVer's 0.x convention: breaking changes bump the **minor** (`0.X.0`), fixes and additive features bump the patch. The PHP floor (`^8.4.1`) and the `laranail/console` constraint live in `composer.json` — a breaking bump in that upstream is itself a breaking change here. (The toolkit is self-contained and no longer depends on `laranail/package-tools`.)

**What the version contract covers:** the documented module surface (`Modules\*` public classes and facades) and the shipped Artisan commands. Anything marked `@internal` and module internals' constructor signatures are excluded.

## Cutting a release

1. Land everything on `main` with the full suite green (`vendor/bin/pest`) and PHPStan clean — CI runs both on every push.
2. Add the `## [0.X.Y]` block to `CHANGELOG.md` (Keep a Changelog).
3. Commit, push, wait for CI green.
4. Tag and release with the CHANGELOG block as the body (never a bare stub):

   ```bash
   git tag v0.X.Y && git push origin v0.X.Y
   gh release create v0.X.Y --title "v0.X.Y" --notes-file <(awk '/^## \[0.X.Y\]/{f=1;next} /^## \[/{f=0} f' CHANGELOG.md) --generate-notes
   ```

The repo is GitHub-only (not on Packagist); consumers resolve tags via a `vcs` repository entry, so the tag IS the release channel.

---

[← Docs index](../README.md#documentation)

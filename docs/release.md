# Release

The package is pre-1.0 and ships from a single moving `v0.1.0` tag.

## Versioning

Like most of the `laranail` family, the repository carries one tag, `v0.1.0`, which moves forward on each change; `composer.json` aliases `dev-main` to `0.1.x-dev`. Consumers require `^0.1`.

Composer caches a dist archive per package reference, and a moved tag keeps its name, so after a tag move consumers should run `composer clear-cache` before `composer update` to pick up the new code.

## Cutting a release

1. Merge the change into `main` through a pull request; the `Tests` workflow must pass.
2. Record the change in `CHANGELOG.md`.
3. Move `v0.1.0` to the merge commit on `main` — land the branch first, then move the tag, so the tag never points at a commit outside `main`.

The repository has no `release.yml`, so moving the tag publishes no GitHub release.

---

[← Docs index](../README.md#documentation)

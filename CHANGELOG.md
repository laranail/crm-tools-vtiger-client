# Changelog

All notable changes to `vtwsclient` will be documented in this file

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Requires `laranail/toolkit ^0.2`** (was `^0.1`). Under 0.x, `^0.1` stops below `0.2.0`, so
  this package kept resolving toolkit `v0.1.0` and never received 0.2's fixes. 0.2's breaking
  changes are three renamed collection macros (`chunkBy`, `firstOrFail`, `before`); this package
  calls none of them.

### Removed

- **`simtabi/pheg` is no longer a dependency**, and neither is its `vcs` repository entry. pheg
  is archived, and it imports `Simtabi\Enekia\...` without requiring `simtabi/enekia`, so on a
  fresh install `VtWsClient` could not be constructed at all: `Session::login()` reached pheg's
  `Transfigure`, whose constructor failed with "Class not found". Applications that declared
  `simtabi/pheg` or `simtabi/json-objects` only for this package can drop both.

### Changed

- The three pheg calls are replaced by in-package code under `Support\`, with the same results:
  `Inflector::fromCamelCase()` (module name to fetcher key), `Transfigure::toObject()` (the cached
  session, array to nested `stdClass`) and `ArrayQuery` (the records returned by
  `Operations::fetchDeepWithPagination()`).
- `Operations::fetchDeepWithPagination()` now returns `Support\ArrayQuery` instead of pheg's
  `QueryEngine`, with the same list-query surface: the `where()` family (`orWhere`, `whereIn`,
  `whereNotIn`, `whereNull`, `whereNotNull`, `whereExists`, `whereStartsWith`, `whereEndsWith`,
  `whereContains`, `whereLike`, `whereMatch`, `whereAny`, `callableWhere`), `select`, `except`,
  `offset`, `take`, `sortBy`, `sort`, `groupBy`, `distinct`, `map`, `each`, `filter`, `get`,
  `first`, `last`, `nth`, `column`, `implode`, the aggregates (`count`, `sum`, `min`, `max`, `avg`,
  `exists`), array access, iteration, `count()` and JSON. Checked side by side against pheg's own
  engine on the same records; two deliberate differences, both where pheg was broken: `get($columns)`
  returns those columns (pheg ignored the list), and the string clauses treat a missing key as no
  match (pheg threw a `TypeError`). Not carried over: pheg's JSON-file reading and document-path
  methods (`from()`, `at()`, `find()`), `whereDate()`, `whereDataType()`, `whereInstance()`,
  `whereCount()`, `then()` and `copy()`.

### Added

- `tests/Feature/DocumentedClassesExistTest.php` resolves every class named in the PHP fences of
  `README.md` and `docs/**` (imports, `new`, static access, fully-qualified names) and every
  backticked `Simtabi\...` name in their prose, and fails on one that does not exist. It fails on
  the README that opened with `use Simtabi\Laranail\CrmTools\VtigerClient;`, a namespace rather
  than a class. `docs/index.md` is exempt pending an owner decision on that page.

- `existsInModule()` accepts VTQL's `IN` with a list of values, each quoted:
  `existsInModule('Leads', 'leadstatus', ['Hot', 'Warm'], 'IN')`.

### Fixed

- **The README quick start logged in with the password, not the access key.** Its `.env` block
  set `VTWSCLIENT_ACCESS_KEY` but not `VTWSCLIENT_LOGIN_WITH_ACCESS_KEY`, which reads as `false`
  when unset. It now sets both and says why.

- The README's `Install` section carries the VCS repositories block (the package, `package-tools`,
  `toolkit` and `console`, plus the Packagist `exclude` and `packagist.org: false` lines) instead
  of deferring to `docs/installation.md`, whose block now has the same two Packagist lines. Both
  document the `laranail::crm-tools-vtiger-client-translations` publish tag, which the provider
  registers and neither mentioned.

- **`Entities::getNumericID()` returns `-1` when no record matches**, as its `int` return type and
  its existing `-1` fallback intended. It passed `null` to `explode()` instead, which threw a
  `TypeError` under `strict_types`.

- **`Operations::search()` quotes bindings for VTQL, not with the application database.** It filled
  `?` bindings with `DB::connection()->getPdo()->quote()`: MySQL's quoter escapes a quote with a
  backslash, which VTQL does not honour, so a crafted value could leave the literal; it also needed a
  working database connection to build a CRM query, and a binding containing `$1` or `\1` was read
  as a regex back-reference. Bindings are now quoted with `Vtql::literal()` and inserted verbatim.
  The string-building half is `Operations::compileSearch()`, which `search()` delegates to.

- **VTQL queries no longer interpolate caller values raw.** Every builder put values straight
  between single quotes, so a value containing a quote ended the literal and the rest became
  query text. `Support\Vtql` now quotes values by doubling the quote, which is how VTQL escapes
  it; module and field names must look like Vtiger names, and operators come from a fixed set
  (`=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`). `count(*)` stays accepted as a
  select column.

## Internal history (not published)

- An entry headed `1.0.0 - 201X-XX-XX` ("initial release") stood here: a placeholder from the
  package template, with no date and no matching tag. No `1.0.0` was ever tagged; the repository's
  first commit is `Initial release` (2022-11-20 in git history), and the only published tag is
  `v0.1.0`.

[Unreleased]: https://github.com/laranail/crm-tools-vtiger-client/compare/v0.1.0...HEAD

# Changelog

All notable changes to `vtwsclient` will be documented in this file

## [Unreleased]

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
  `QueryEngine`. It implements what this package used -- `where()` with comparison operators,
  `filter()` and `toArray()` -- and none of pheg's other query methods (`select()`, `sortBy()`,
  `count()`, array access, iteration, ...). Code calling those on the result should call
  `toArray()` and work on the array.

### Added

- `existsInModule()` accepts VTQL's `IN` with a list of values, each quoted:
  `existsInModule('Leads', 'leadstatus', ['Hot', 'Warm'], 'IN')`.

### Fixed

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

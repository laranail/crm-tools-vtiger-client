# Changelog

All notable changes to `vtwsclient` will be documented in this file

## [Unreleased]

### Fixed

- **`Operations::search()` quotes bindings for VTQL, not with the application database.** It filled
  `?` bindings with `DB::connection()->getPdo()->quote()`: MySQL's quoter escapes a quote with a
  backslash, which VTQL does not honour, so a crafted value could leave the literal; it also needed a
  working database connection to build a CRM query, and a binding containing `$1` or `\1` was read
  as a regex back-reference. Bindings are now quoted with `Vtql::literal()` and inserted verbatim.
  The string-building half is `Operations::compileSearch()`, which `search()` delegates to.

### Added

- `existsInModule()` accepts VTQL's `IN` with a list of values, each quoted:
  `existsInModule('Leads', 'leadstatus', ['Hot', 'Warm'], 'IN')`.

### Fixed

- **VTQL queries no longer interpolate caller values raw.** Every builder put values straight
  between single quotes, so a value containing a quote ended the literal and the rest became
  query text. `Support\Vtql` now quotes values by doubling the quote, which is how VTQL escapes
  it; module and field names must look like Vtiger names, and operators come from a fixed set
  (`=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`). `count(*)` stays accepted as a
  select column.

## 1.0.0 - 201X-XX-XX

- initial release

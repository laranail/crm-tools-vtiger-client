# Changelog

All notable changes to `vtwsclient` will be documented in this file

## [Unreleased]

### Fixed

- **VTQL queries no longer interpolate caller values raw.** Every builder put values straight
  between single quotes, so a value containing a quote ended the literal and the rest became
  query text. `Support\Vtql` now quotes values by doubling the quote, which is how VTQL escapes
  it; module and field names must look like Vtiger names, and operators come from a fixed set
  (`=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`). `count(*)` stays accepted as a
  select column.

## 1.0.0 - 201X-XX-XX

- initial release

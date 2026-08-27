# Getting started

Install the toolkit, publish what you need, and reach any module through the unified `Toolkit` facade. For
the full reference see the [Documentation index](../README.md#documentation).

## 1. Install + publish

```bash
composer require laranail/toolkit
php artisan vendor:publish --tag=laranail::toolkit-config
```

`ToolkitServiceProvider` is auto-discovered; feature modules are deferred (they boot on demand). See
[Installation](installation.md) for every publish tag.

## 2. Use a module (three ways)

Every module is reachable by DI (its contract), its own facade, or the unified `Toolkit` facade:

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;

$archive = Toolkit::archiver()->zip();
Toolkit::archiver()->extract($zip, $dest);
```

## 3. Generate a CRUD resource

```bash
php artisan laranail::toolkit.make-crud Post \
  --fields="title:string:required,body:text:nullable" \
  --searchable=title,body --soft-deletes --register-routes
# alias: php artisan make:crud Post ...
```

See [make-crud](make-crud.md) and [CrudController](crud-controller.md).

## Next steps

- [Configuration](configuration.md) — the `laranail.toolkit.*` reference.
- [Architecture](architecture.md) — modules, deferred providers, layout.
- [LLM module](modules/llm.md) · [Archiver](modules/archiver.md) — feature modules.
- [Macros](macros.md) · [Utilities](utilities.md) · [Static helpers](helpers.md) — the building blocks.

---

[← Docs index](../README.md#documentation)

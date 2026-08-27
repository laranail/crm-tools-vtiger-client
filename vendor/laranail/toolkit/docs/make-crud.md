# make-crud

Generate a full API CRUD scaffold — Model, Controller, and Migration — from a
single field spec.

```bash
php artisan laranail::toolkit.make-crud Post
# retained alias:
php artisan make:crud Post
```

## Options

| Option | Description |
|--------|-------------|
| `name` (argument) | Model name, e.g. `Post`, `UserProfile`. |
| `--fields=` | Comma-separated `column:type:validation`, e.g. `title:string:required,body:text:nullable,price:decimal:required\|min:0`. |
| `--belongs-to=*` | BelongsTo relationship (also adds the `*_id` FK column + validation). |
| `--has-many=*` | HasMany relationship method. |
| `--has-one=*` | HasOne relationship method. |
| `--belongs-to-many=*` | BelongsToMany relationship method. |
| `--soft-deletes` | Add `SoftDeletes` to the model and `softDeletes()` to the migration. |
| `--searchable=` | Comma-separated fields to enable search + sorting on. |
| `--per-page=15` | Default items per page for the index endpoint. |
| `--register-routes` | Append the `apiResource` route to `routes/api.php`. |
| `--migrate` | Run `php artisan migrate` after generating the migration. |
| `--force` | Overwrite existing Model/Controller files. |

## What is generated

- **Migration** — `database/migrations/*_create_<table>_table.php`. Column types
  are mapped from the field `type`; `nullable`/`unique` are read from the
  validation segment; `--belongs-to` adds `foreignId()->constrained()->cascadeOnDelete()`.
  Supported `type` values (anything else falls back to `string`):

  | `type` | Migration column |
  |--------|------------------|
  | `string` / `varchar` | `string()` (honours `unique`) |
  | `text` / `longtext` / `mediumtext` | `text()` / `longText()` / `mediumText()` |
  | `integer` / `int`, `biginteger` / `bigint`, `unsignedbiginteger` / `unsignedbigint`, `tinyinteger` / `tinyint`, `smallinteger` / `smallint` | the matching integer column |
  | `float`, `double`, `decimal` | `float()` / `double()` / `decimal(10, 2)` |
  | `boolean` / `bool` | `boolean()->default(false)` |
  | `date`, `datetime`, `timestamp` | `date()` / `dateTime()` / `timestamp()` |
  | `json` | `json()` (cast to `array`) |
  | `uuid` | `uuid()` |
  | `enum` | `enum('<col>', [])` — **fill in the allowed values yourself** (generated empty) |
- **Model** — `app/Models/<Name>.php` with `$fillable`, a `$casts` block derived
  from field types, relationship methods, and optional `SoftDeletes`.
- **Controller** — `app/Http/Controllers/<Name>Controller.php`, an
  `apiResource`-shaped controller with index/show/store/update/destroy.

## Example

```bash
php artisan laranail::toolkit.make-crud Post \
  --fields="title:string:required|unique,body:text:nullable,price:decimal:required|min:0,published:boolean" \
  --belongs-to=User \
  --has-many=Comment \
  --searchable=title,body \
  --soft-deletes \
  --register-routes
```

This generates the migration, model, and controller, registers
`Route::apiResource('posts', \App\Http\Controllers\PostController::class)`, and
warns about any related tables that do not exist yet.

## Generated controller behaviour

The generated `index` action is security-conscious by construction:

- search escapes LIKE wildcards (`addcslashes($term, '%_\\')`) so user input
  cannot broaden the match;
- `sort_by` is restricted to the whitelisted searchable columns, with the
  direction clamped to `asc`/`desc`;
- `per_page` is clamped to `[1, 100]`;
- `--belongs-to` relations are eager-loaded.

`store`/`update` build `$request->validate(...)` from the field rules; on
`update`, `unique:` rules are rewritten to ignore the current record's id.

## Custom stubs

Publish the stubs to override the generated output:

```bash
php artisan vendor:publish --tag=laranail::toolkit-stubs
# edit stubs/vendor/laranail-toolkit/crud.{migration,model,controller}.stub
```

[← Docs index](../README.md#documentation)

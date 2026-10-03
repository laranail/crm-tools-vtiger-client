# `VtWsClient`

`VtWsClient` — `Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient`, resolved with `app(VtWsClient::class)` or the `VtWsClient` facade — carries seven methods of its own and four services.

## Client methods

| Method | Returns | Purpose |
|---|---|---|
| `invokeOperation(string $operation, ?array $params = null, string $method = 'POST')` | `array` | Call any Web Services operation, including custom ones. |
| `runQuery(string $query)` | `array` | Run a Vtiger SQL-like query; a trailing `;` is added if missing. Vtiger caps results at 100 rows unless the query sets `LIMIT`. |
| `query(string $query)` | `array` | Alias of `runQuery()`. |
| `existsInModule(string $module, string $column, $value, string $operand = '=')` | `bool` | Whether any record in the module matches the condition. |
| `getCurrentUser()` | `array` | Basic information about the API user. |
| `getVtigerInfo()` | `array` | The Vtiger and API versions of the connected instance. |
| `getErrors()` | `?array` | Errors collected while `throw_errors` is off. |

> `existsInModule()` interpolates `$value` into the query string unescaped. Never pass it user input.

## `$client->entities`

| Method | Returns | Purpose |
|---|---|---|
| `findOne(string $moduleName, array $params, array $select = [])` | `?array` | First record matching the constraints, or `null`. |
| `findOneByID(string $moduleName, string $entityID, array $select = [])` | `?array` | A record by ID. |
| `findMany(string $moduleName, array $params, array $select = [], int $limit = 0, int $offset = 0)` | `?array` | All matching records, or `null`. |
| `getID(string $moduleName, array $params)` | `?string` | Typed ID (`<module_id>x<id>`) of the first match, or `null`. |
| `getNumericID(string $moduleName, array $params)` | `int` | Numeric part of the ID. Throws a `TypeError` when nothing matches — use `getID()` instead. |
| `createOne(string $moduleName, array $params)` | `array` | Create a record; assigns it to the API user unless `assigned_user_id` is set. |
| `updateOne(string $moduleName, $entityID, array $params)` | `array` | Merge `$params` into the existing record and save it. |
| `deleteOne(string $moduleName, string $entityID)` | `array` | Delete a record. |
| `sync(?int $modifiedTime = null, ?string $moduleName = null, ?string $syncType = null)` | `array` | Records updated or deleted since a time. |

## `$client->modules`

| Method | Returns | Purpose |
|---|---|---|
| `getAll()` | `array` | Every module available to the API user, keyed by name. |
| `listTypes()` | `array` | Alias of `getAll()`. |
| `getOne(string $moduleName)` | `array` | Describe a module: label, permissions and fields. |
| `getTypedID(string $moduleName, string $entityID)` | `string` | Prefix a numeric ID with the module's ID prefix. |

## `$client->operations`

Lower-level wrappers over single Web Services calls: `search()`, `lookup()`, `retrieve()`, `relatedTypes()`, `retrieveRelated()`, `create()`, `update()`, `delete()`, `describe()`, `getAllInSummary()` and `fetchDeepWithPagination()`, plus account helpers — `getAccountInfo()`, `getAccountId2emailOrViceVersa()`, `getAllAccountsRelatedToAccountId()`, `getAllAccountContacts()` and `getAllRelatedAccountEntities()`.

## `$client->fetchers`

Collection-returning shortcuts for common modules: `fetchAccounts()`, `fetchActiveAccountsEmail()`, `fetchContacts()`, `fetchAssets()`, `fetchCases()`, `fetchProducts()`, `fetchProjects()`, `fetchProductCategories()`, `fetchRelatedAccountEntities()` and `fetchEntityInfoBy()`.

## Exceptions

Failures throw `Simtabi\Laranail\CrmTools\VtigerClient\Exceptions\VtWsClientException` — always for invalid arguments, and for login and request errors when `throw_errors` is on.

---

[← Docs index](../../README.md#documentation)

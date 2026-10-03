# Sync changes since a date

Fetch the records modified or deleted since a given time, using Vtiger's `sync` operation.

## Example

```php
// Fetch entities updated and/or deleted since
// the midnight of the first day of this month
$lastModTime = strtotime('first day of this month midnight');
$leadsSyncInfo = $client->entities->sync($lastModTime, 'Leads');

if (isset($leadsSyncInfo['updated'])) {
    // ... do something with updated entries
}

if (isset($leadsSyncInfo['deleted'])) {
    // ... and something else with deleted entries
}
```

`sync(?int $modifiedTime = null, ?string $moduleName = null, ?string $syncType = null)` defaults the time to today's midnight and, without a module name, covers every module. See the [`VtWsClient` reference](../tools/vtwsclient.md).

---

[← Docs index](../../README.md#documentation)

# Retrieve related records

List the records related to an entity — for example, the tickets created for a contact.

## Example

```php
$johnSmithId = $client->entities->getID('Contacts', [
    'firstname' => 'John',
    'lastname'  => 'Smith',
]);

// List ALL the tickets created for John Smith
$entities = $client->invokeOperation('retrieve_related', [
    'id'           => $johnSmithId,
    'relatedType'  => 'HelpDesk',
    'relatedLabel' => 'HelpDesk',
], 'GET');

// ...or use SQL-like queries with WHERE, ORDER BY, LIMIT etc.
$entities = $client->invokeOperation('query_related', [
    'query'        => "SELECT * FROM HelpDesk WHERE ticket_title LIKE '%problem%' ORDERBY modifiedtime LIMIT 10",
    'id'           => $johnSmithId,
    'relatedLabel' => 'HelpDesk',
], 'GET');

// The output
array(1) {
  [0]=>
  array(23) {
    ["ticket_no"]=>
    string(3) "TT1"
    ["ticketpriorities"]=>
    string(3) "Low"
    ["ticketstatus"]=>
    string(11) "In Progress"
    ["ticket_title"]=>
    string(17) "Problem with APIs"
    ["contact_id"]=>
    string(4) "12x8"
    ...
   }
}
```

`getID()` returns `null` when no contact matches; check it before calling the operation. `$client->operations->retrieveRelated($id, $targetLabel, $targetModule)` wraps the first call. See the [`VtWsClient` reference](../tools/vtwsclient.md).

---

[← Docs index](../../README.md#documentation)

# Getting started

Resolve the client, describe a module, then create and look up records.

## Resolve the client

```php
use Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient;

$client = app(VtWsClient::class);
```

The container binds `VtWsClient` as a singleton, and its constructor logs in straight away with the credentials from [Configuration](configuration.md). `new VtWsClient()` works too, outside the container. The client exposes four services as public properties: `entities`, `modules`, `operations` and `fetchers` — see the [`VtWsClient` reference](tools/vtwsclient.md).

## Describe a module

The most basic call "defines" a module: it retrieves the module's fields, required and optional. The output is useful when choosing the constraints to pass to `entities->findOne()` and `entities->findMany()`.

```php
// Print the information about the Leads module,
// including the list of required and optional fields
print_r($client->modules->getOne('Leads'));

// The output
Array
(
  [label] => Leads
  [name] => Leads
  [createable] => 1
  [updateable] => 1
  [deleteable] => 1
  [retrieveable] => 1
  [fields] => Array
    (
        // ...
        [1] => Array
        (
          [name] => firstname
          [label] => First Name
          [mandatory] =>
          [type] => Array
            (
              [name] => string
            )

          [nullable] => 1
          [editable] => 1
          [default] =>
        )
        // ...
    )
)
```

## Create a record if it does not exist

Check whether a lead exists, using the first and last name as constraints, and create it if not. `getID()` returns `null` when nothing matches.

```php
// Look for John Smith among Leads
$johnSmithId = $client->entities->getID('Leads', [
    'firstname' => 'John',
    'lastname'  => 'Smith',
]);

// Add his record if it doesn't exist
if ($johnSmithId === null) {
    $johnSmith = $client->entities->createOne('Leads', [
        'salutationtype' => 'Mr.',
        'firstname'      => 'John',
        'lastname'       => 'Smith',
        'phone'          => '+1 919 000 0000',
        'fax'            => '+1 919 000 0000',
        'company'        => 'JSmith LLC',
        'email'          => 'john@jsmith.com',
        'leadsource'     => 'Office Meetup',
        'website'        => 'jsmith.com',
        'leadstatus'     => 'Hot',
    ]);
    print_r($johnSmith);
} else {
    echo 'John Smith\'s record already exists!' . PHP_EOL;
}

// The output
Array
(
    [salutationtype] => Mr.
    [firstname] => John
    [lead_no] => LEA6
    [phone] => +1 919 000 0000
    [lastname] => Smith
    [mobile] =>
    [company] => JSmith LLC
    [fax] => +1 919 000 0000
    [email] => john@jsmith.com
    [leadsource] => Office Meetup
    [website] => jsmith.com
    [leadstatus] => Hot
    [annualrevenue] => 0.00000000
    ...
    [id] => 200x5
)
```

`createOne()` assigns the record to the logged-in API user unless `assigned_user_id` is in the payload.

> `getNumericID()` returns `-1` when no record matches. Check for it, or use `getID()`, which returns `null`.

## Look up an existing record

Check whether a contact exists, using the first and last name as constraints. If John Smith is among your contacts, the code below prints only the listed fields — useful when you do not need everything the record holds. `findOne()` returns `null` when nothing matches.

```php
// Look for John Smith in Contacts
// and print out only some fields
$johnSmith = $client->entities->findOne('Contacts', [
    'firstname' => 'John',
    'lastname'  => 'Smith',
], [
    'id',
    'salutationtype',
    'firstname',
    'lastname',
    'email',
    'phone',
]);

if ($johnSmith !== null) {
    print_r($johnSmith);
} else {
    echo 'John Smith\'s record doesn\'t exist!' . PHP_EOL;
}

// The output
Array
(
    [id] => 12x3
    [salutationtype] => Mr.
    [firstname] => John
    [lastname] => Smith
    [email] => john@jsmith.com
    [phone] => +1 919 000 0000
)
```

## Next steps

- [Invoke a custom operation](recipes/custom-operations.md), [retrieve related records](recipes/related-records.md) or [sync changes since a date](recipes/sync-changes.md).
- [Vtiger Webservices tutorials](https://wiki.vtiger.com/index.php/Webservices_tutorials) for the underlying API.
- Questions, bugs and feature requests go to the [issue tracker](https://github.com/laranail/crm-tools-vtiger-client/issues).

---

[← Docs index](../README.md#documentation)

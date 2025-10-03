MongoDB connectivity
====================

[![Build status on GitHub](https://github.com/xp-forge/mongodb/workflows/Tests/badge.svg)](https://github.com/xp-forge/mongodb/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.4+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_4plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/mongodb/version.svg)](https://packagist.org/packages/xp-forge/mongodb)

This library implements MongoDB connectivity via its binary protocol. It has no dependencies on the PHP extension.

* ✅ Local MongoDB installations
* ✅ [MongoDB Atlas](https://www.mongodb.com/docs/atlas/) and [Atlas Search](https://www.mongodb.com/docs/atlas/atlas-search/)
* ✅ [Azure Cosmos DB for MongoDB](https://learn.microsoft.com/en-us/azure/cosmos-db/mongodb/introduction)

Examples
--------
Finding documents inside a collection:

```php
use com\mongodb\{MongoConnection, ObjectId};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');
$id= new ObjectId('...');

// Find all documents
$cursor= $c->collection('test.products')->query();

// Find document with the specified ID
$cursor= $c->collection('test.products')->query($id);

// Find all documents with a name of "Test"
$cursor= $c->collection('test.products')->query(['name' => 'Test']);

// Use aggregation pipelines
$cursor= $c->collection('test.products')->query([
  ['$match' => ['color' => 'green', 'state' => 'ACTIVE']],
  ['$lookup' => [
    'from'         => 'users',
    'localField'   => 'owner.id',
    'foreignField' => '_id',
    'as'           => 'owner',
  ]],
  ['$addFields' => ['owner' => ['$first' => '$owner']]],
]);

foreach ($cursor as $document) {
  Console::writeLine('>> ', $document);
}
```

Inserting a document into a collection:

```php
use com\mongodb\{MongoConnection, Document};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');

$result= $c->collection('test.products')->insert(new Document([
  'name' => 'Test',
  'qty'  => 10,
  'tags' => ['new', 'tested'],
]));
Console::writeLine('>> ', $result);
```

Updating documents:

```php
use com\mongodb\{MongoConnection, ObjectId};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');
$id= new ObjectId('...');

// Select a single document for updating by its ID
$result= $c->collection('test.products')->update($id, ['$inc' => ['qty' => 1]]);

// Apply to all documents matchig a given filter
$result= $c->collection('test.products')->update(['name' => 'Test'], ['$inc' => ['qty' => 1]]);

Console::writeLine('>> ', $result);

// Return document after modification
$result= $c->collection('test.products')->modify($id, ['$inc' => ['qty' => 1]]);
Console::writeLine('>> ', $result->kind(), ' ', $result->document());
```

[Upserting](https://www.mongodb.com/docs/manual/reference/command/update/#std-label-update-command-upsert) documents:

```php
use com\mongodb\{MongoConnection, Document};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');

$result= $c->collection('test.products')->upsert(['slug' => 'test'], new Document([
  'slug' => 'test',
  'name' => 'Test',
  'qty'  => 10,
  'tags' => ['new', 'tested'],
]));

Console::writeLine('>> ', $result);
```

Deleting documents:

```php
use com\mongodb\{MongoConnection, ObjectId};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');
$id= new ObjectId('...');

// Select a single document to be removed
$result= $c->collection('test.products')->delete($id);

// Remove all documents matchig a given filter
$result= $c->collection('test.products')->delete(['name' => 'Test']);

Console::writeLine('>> ', $result);

// Return deleted document
$result= $c->collection('test.products')->remove($id);
Console::writeLine('>> ', $result->kind(), ' ', $result->document());
```

*Note: All of the above have used the `collection()` shortcut which is equivalent to chaining `database('test')->collection('products')`.*

Authentication
--------------
To authenticate, pass username and password via the connection string, e.g. `mongodb://user:pass@localhost`. The authentication source defaults to *admin* but can be set by supplying a path, e.g. `mongodb://user:pass@localhost/test`.

Both *SCRAM-SHA-256* and *SCRAM-SHA-1* are supported as authentication mechanisms. Which one is used is negotiated upon connecting with the server / cluster. To explicitely select the authentication mechanism, pass it as part of the connection string, e.g. `mongodb://user:pass@localhost?authMechanism=SCRAM-SHA-256`.

SSL / TLS
---------
To connect via SSL / TLS, pass `ssl=true` as connection string parameters, e.g.:

```php
use com\mongodb\MongoConnection;

$c= new MongoConnection('mongodb://localhost?ssl=true');

// Explicit call to connect, can be omitted when using collection()
$c->connect();
```

Aggregation
-----------
The `Collection` class also features aggregation methods:

* `count($filter= [])`
* `distinct($key, $filter= [])`
* `aggregate($pipeline)`

See https://docs.mongodb.com/manual/reference/command/nav-aggregation/

Commands
--------
To run commands on a given collection, use the *run()* method:

```php
use com\mongodb\MongoConnection;

$c= new MongoConnection('mongodb+srv://server.example.com');

// A simple connection-wide command without arguments
$result= $c->run('ping')->value();

// A command might return a cursor
$indexes= $c->collection('app.sessions')->run('listIndexes', [], 'read');
foreach ($indexes->cursor() as $index) {
  // ...
}
```

See https://www.mongodb.com/docs/manual/reference/command/ for a list of commands.

DNS Seed List Connection
------------------------
Adding in DNS to specify clusters adds another level of flexibility to deployment. Given the following DNS entries:

```
Record                            TTL   Class    Priority Weight Port  Target
_mongodb._tcp.server.example.com. 86400 IN SRV   0        5      27317 mongodb1.example.com.
_mongodb._tcp.server.example.com. 86400 IN SRV   0        5      27017 mongodb2.example.com.
```

...the following code will connect to one of the above:

```php
use com\mongodb\MongoConnection;

$c= new MongoConnection('mongodb+srv://server.example.com');
$c->connect();
```

Sessions
--------
Using a causally consistent session, an application can read its own writes and is guaranteed monotonic reads, even when reading from replica set secondaries.

```php
use com\mongodb\{MongoConnection, ObjectId};
use util\cmd\Console;

$c= new MongoConnection('mongodb+srv://server.example.com?readPreference=secondary');
$session= $c->session();

$id= new ObjectId('...');

// Will write to primary
$collection= $c->collection('test.products');
$collection->update($id, ['$set' => ['qty' => 1]], $session);

// Will read the updated document
$updated= $collection->find($id, $session);

$session->close();
```

Handling errors
---------------
All operations raise instances of the `com.mongodb.Error` class. Connection and authentication errors can be handled by checking for *CannotConnect*:

```php
use com\mongodb\{MongoConnection, Error, CannotConnect};
use util\cmd\Console;

$c= new MongoConnection('mongodb+srv://user:pass@mongo.example.com');
try {
  $c->connect();
} catch (CannotConnect $e) {
  // Error during authentication phase, e.g.:
  // - DNS errors
  // - None of the replica set members is reachable
  // - Authentication failed
} catch (Error $e) {
  // Any other error
}
```

Type mapping
------------
All builtin types are mapped to their BSON equivalents. In addition, the following type mappings are used:

* `util.Date` => UTC datetime
* `util.Bytes` => Binary data
* `util.UUID` => UUID binary data
* `com.mongodb.Int64` => 64-bit integer
* `com.mongodb.Decimal128` => 128-bit decimal
* `com.mongodb.ObjectId` => Object ID
* `com.mongodb.Timestamp` => Timestamp
* `com.mongodb.Regex` => Regular expression
* `com.mongodb.Encrypted` => Encrypted values

The deprecated types of the BSON spec are not supported, see http://bsonspec.org/spec.html